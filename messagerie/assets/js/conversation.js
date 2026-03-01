/**
 * /assets/js/conversation.js — Scripts pour les conversations
 * v2 : CSRF, XSS-safe, polling unifié, edit/delete/pin/reactions, load more
 * Dépend de shared.js (chargé avant)
 */

document.addEventListener('DOMContentLoaded', function() {
    // Actions communes (archive/supprimer/restaurer/modals) — shared.js
    setupConversationActions();

    // Scroll auto en bas des messages
    const messagesContainer = document.querySelector('.messages-container');
    if (messagesContainer) messagesContainer.scrollTop = messagesContainer.scrollHeight;

    // Initialiser la configuration des connections actives
    setupActiveConnections();
    
    // Initialisation du système de lecture des messages
    initReadTracker();
    
    // Polling unifié — démarré en fallback si WebSocket indisponible
    // (voir setupWebSocketForConversation → startPollingFallback)
    window._setupUnifiedPolling = setupUnifiedPolling;
    
    // Si pas de WebSocket, démarrer le polling immédiatement
    if (!window.wsClient) {
        setupUnifiedPolling();
    }
    
    // Validation du formulaire de message
    setupMessageValidation();
    
    // Initialisation de l'envoi AJAX
    setupAjaxMessageSending();
    
    // Initialiser la sidebar rétractable
    initSidebarCollapse();
    
    // Nettoyage des ressources lors de la navigation
    setupBeforeUnloadHandler();
    
    // Indicateur de frappe
    setupTypingIndicator();
    
    // Dropdown menus pour actions messages
    setupMessageDropdowns();

    // WebSocket (désactive polling si connecté)
    setupWebSocketForConversation();
});

/**
 * Configure les connections actives
 */
function setupActiveConnections() {
    // Variable globale pour suivre l'état des connexions
    // Initialiser à TRUE par défaut pour permettre le démarrage immédiat du polling
    window.activeConnections = {
        messagePolling: true,  // Pour les messages
        readStatusPolling: true, // Pour les status de lecture
        abortController: new AbortController()
    };
    
    // Réactiver le polling si l'onglet devient visible
    document.addEventListener('visibilitychange', function() {
        if (document.visibilityState === 'visible') {
            window.activeConnections.messagePolling = true;
            window.activeConnections.readStatusPolling = true;
        }
    });
    
    // Réactiver également le polling au focus de la fenêtre
    window.addEventListener('focus', function() {
        window.activeConnections.messagePolling = true;
        window.activeConnections.readStatusPolling = true;
    });
}

/**
 * Configure un gestionnaire pour nettoyer les ressources avant la navigation
 */
function setupBeforeUnloadHandler() {
    // Gestionnaire d'événement pour la navigation
    window.addEventListener('beforeunload', cleanupResources);
    window.addEventListener('pagehide', cleanupResources);
    
    // Fonction pour nettoyer les ressources
    function cleanupResources() {
        // Annuler les requêtes fetch en cours
        if (window.activeConnections && window.activeConnections.abortController) {
            window.activeConnections.abortController.abort();
            window.activeConnections.abortController = new AbortController();
        }
        
        // Indiquer que le polling doit s'arrêter (utiliser des flags séparés)
        if (window.activeConnections) {
            window.activeConnections.messagePolling = false;
            window.activeConnections.readStatusPolling = false;
        }
    }
}

// getFetchOptions, apiFetch, getApiBase → shared.js

/**
 * Initialise le système de détection et de suivi des messages lus
 * (simplifié — le polling est géré par setupUnifiedPolling)
 */
function initReadTracker() {
    // Configuration de l'IntersectionObserver pour le marquage auto
    const messageObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting && entry.intersectionRatio >= 0.7) {
                const messageEl = entry.target;
                const messageId = parseInt(messageEl.dataset.id, 10);
                
                if (!messageEl.classList.contains('read') && !messageEl.classList.contains('self')) {
                    markMessageAsRead(messageId);
                }
            }
        });
    }, {
        root: document.querySelector('.messages-container'),
        threshold: 0.7,
        rootMargin: '0px 0px -20% 0px'
    });
    
    // Observer tous les messages qui ne sont pas de l'utilisateur
    document.querySelectorAll('.message:not(.self)').forEach(message => {
        messageObserver.observe(message);
    });
    
    // Exposer l'observer pour les nouveaux messages
    window.messageObserver = messageObserver;
}

/**
 * Met à jour l'affichage du statut de lecture d'un message
 */
function updateReadStatus(readStatus) {
    if (!readStatus || !readStatus.message_id) return;
    
    const messageEl = document.querySelector(`.message[data-id="${readStatus.message_id}"]`);
    if (!messageEl) return;
    
    const statusEl = messageEl.querySelector('.message-read-status');
    if (!statusEl) return;
    
    if (readStatus.all_read) {
        statusEl.innerHTML = `<div class="all-read"><i class="fas fa-check-double"></i> Vu</div>`;
        messageEl.classList.add('read');
    } else if (readStatus.read_by_count > 0) {
        const readerNames = readStatus.readers?.map(r => r.nom_complet).join(', ') || '';
        statusEl.innerHTML = `
            <div class="partial-read">
                <i class="fas fa-check"></i>
                <span class="read-count">${readStatus.read_by_count}/${readStatus.total_participants - 1}</span>
                ${readerNames ? `<span class="read-tooltip" title="${escapeHTML(readerNames)}"><i class="fas fa-info-circle"></i></span>` : ''}
            </div>`;
    }
}

/**
 * Polling unifié — un seul setInterval pour messages + read_status
 * Smart polling : intervalles adaptatifs (5s actif → 30s inactif), suspension si onglet caché
 */
function setupUnifiedPolling() {
    const convId = new URLSearchParams(window.location.search).get('id');
    if (!convId) return;
    
    let lastTimestamp = 0;
    let readVersionSum = 0;
    let lastReadMessageId = 0;
    let isPolling = false;

    const POLL_ACTIVE = 5000;   // 5s quand onglet visible
    const POLL_INACTIVE = 30000; // 30s quand onglet caché
    let pollTimerId = null;
    
    // Initialiser depuis le dernier message existant
    const lastMsg = document.querySelector('.message:last-child');
    if (lastMsg) {
        lastTimestamp = parseInt(lastMsg.getAttribute('data-timestamp') || '0', 10);
        lastReadMessageId = parseInt(lastMsg.getAttribute('data-id') || '0', 10);
    }
    
    async function poll() {
        if (isPolling || !window.activeConnections?.messagePolling) return;
        isPolling = true;
        
        try {
            // ── Vérification nouveaux messages ──
            const msgData = await apiFetch(
                `${getApiBase()}/messagerie.php?resource=messages&action=check_updates&conv_id=${convId}&last_timestamp=${lastTimestamp}`
            );
            
            if (msgData.success && msgData.has_updates && msgData.new_count > 0) {
                const newData = await apiFetch(
                    `${getApiBase()}/messagerie.php?resource=messages&action=get_new&conv_id=${convId}&last_timestamp=${lastTimestamp}`
                );
                
                if (newData.success && newData.messages?.length > 0) {
                    const container = document.querySelector('.messages-container');
                    const wasAtBottom = isScrolledToBottom(container);
                    
                    newData.messages.forEach(msg => {
                        if (!document.querySelector(`.message[data-id="${msg.id}"]`)) {
                            appendMessageToDOM(msg, container);
                            if (msg.timestamp > lastTimestamp) lastTimestamp = msg.timestamp;
                        }
                    });
                    
                    if (wasAtBottom) scrollToBottom(container);
                    else showNewMessagesIndicator(newData.messages.length);
                }
            }
            
            // ── Vérification read status ──
            const readData = await apiFetch(
                `${getApiBase()}/messagerie.php?resource=messages&action=read_status&conv_id=${convId}&version=${readVersionSum}&since=${lastReadMessageId}`
            );
            
            if (readData.success) {
                readVersionSum = readData.version || 0;
                if (readData.has_updates && readData.updates) {
                    readData.updates.forEach(u => updateReadStatus(u));
                }
            }
        } catch (e) {
            if (e.name !== 'AbortError') console.error('Polling error:', e);
        } finally {
            isPolling = false;
        }
    }

    /** Démarre / redémarre le timer avec l'intervalle approprié */
    function startPollingTimer() {
        stopPollingTimer();
        const interval = document.visibilityState === 'visible' ? POLL_ACTIVE : POLL_INACTIVE;
        pollTimerId = setInterval(poll, interval);
        window.unifiedPollingId = pollTimerId;
    }

    function stopPollingTimer() {
        if (pollTimerId) { clearInterval(pollTimerId); pollTimerId = null; }
    }

    // Adapter l'intervalle quand la visibilité change
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            window.activeConnections.messagePolling = true;
            poll(); // MAJ immédiate au retour
            startPollingTimer(); // repasser à 5s
        } else {
            startPollingTimer(); // repasser à 30s
        }
    });
    
    // Démarrage initial après 1s
    setTimeout(poll, 1000);
    startPollingTimer();
    
    // Scroll: masquer indicateur
    const container = document.querySelector('.messages-container');
    if (container) {
        container.addEventListener('scroll', () => {
            if (isScrolledToBottom(container)) {
                const ind = document.getElementById('new-messages-indicator');
                if (ind) ind.style.display = 'none';
            }
        });
    }
}

/**
 * Affiche un indicateur de nouveaux messages
 */
function showNewMessagesIndicator(count) {
    let indicator = document.getElementById('new-messages-indicator');
    
    if (!indicator) {
        indicator = document.createElement('div');
        indicator.id = 'new-messages-indicator';
        indicator.className = 'new-messages-indicator';
        
        indicator.addEventListener('click', function() {
            scrollToBottom(document.querySelector('.messages-container'));
            this.style.display = 'none';
        });
        
        document.body.appendChild(indicator);
    }
    
    indicator.textContent = `${count} nouveau(x) message(s)`;
    indicator.style.display = 'block';
    
    setTimeout(() => { if (indicator) indicator.style.display = 'none'; }, 5000);
}

/**
 * Configure l'envoi AJAX des messages
 */
function setupAjaxMessageSending() {
    const form = document.getElementById('messageForm');
    if (!form) return;
    
    form.addEventListener('submit', function(e) {
        e.preventDefault(); // Empêcher la soumission normale
        
        // Vérifier le contenu du message
        const textarea = form.querySelector('textarea[name="contenu"]');
        const messageContent = textarea.value.trim();
        if (messageContent === '') {
            alert('Le message ne peut pas être vide');
            return;
        }
        
        // Récupérer l'ID de conversation de l'URL
        const urlParams = new URLSearchParams(window.location.search);
        const convId = urlParams.get('id');
        
        // Créer un objet FormData pour l'envoi des données, y compris les fichiers
        const formData = new FormData(form);
        formData.append('conversation_id', convId);
        formData.append('action', 'send_message');
        
        // Afficher un indicateur de chargement
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalBtnText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Envoi en cours...';
        
        // Envoyer la requête AJAX avec CSRF
        const apiPath = `${window.location.origin}${getApiBase()}/messagerie.php?resource=messages&action=send_message`;
        
        // Ajouter le CSRF token au FormData
        const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
        formData.append('_csrf_token', token);
        
        fetch(apiPath, {
            method: 'POST',
            body: formData,
            signal: window.activeConnections.abortController.signal,
            credentials: 'same-origin'
        })
        .then(response => {
            // Vérifier si la réponse est ok avant de continuer
            if (!response.ok) {
                throw new Error(`Erreur HTTP: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                // Vider le formulaire
                textarea.value = '';
                
                // Vider l'aperçu des pièces jointes
                const fileList = document.getElementById('file-list');
                if (fileList) fileList.innerHTML = '';
                
                // Réinitialiser l'input de fichiers
                const fileInput = document.getElementById('attachments');
                if (fileInput) fileInput.value = '';
                
                // Mettre à jour l'interface utilisateur
                if (data.message) {
                    // Ajouter le nouveau message à la conversation
                    const messagesContainer = document.querySelector('.messages-container');
                    if (messagesContainer) {
                        appendMessageToDOM(data.message, messagesContainer);
                        scrollToBottom(messagesContainer);
                    }
                }
                
                // Réinitialiser le formulaire de réponse si c'est une réponse
                const replyInterface = document.getElementById('reply-interface');
                if (replyInterface && replyInterface.style.display !== 'none') {
                    document.getElementById('parent-message-id').value = '';
                    replyInterface.style.display = 'none';
                }
            } else {
                // Afficher l'erreur
                alert('Erreur lors de l\'envoi du message: ' + (data.error || 'Erreur inconnue'));
            }
        })
        .catch(error => {
            // Ne pas afficher d'erreur si la requête a été annulée (navigation)
            if (error.name !== 'AbortError') {
                console.error('Erreur:', error);
                alert('Erreur lors de l\'envoi du message. Veuillez réessayer.');
            }
        })
        .finally(() => {
            // Réactiver le bouton quoi qu'il arrive
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnText;
        });
    });
}

/**
 * Ajoute un message au DOM (v2 : XSS-safe, reactions, edit/delete, secure attachments)
 * @param {Object} message - Objet message
 * @param {HTMLElement} container - Conteneur
 */
function appendMessageToDOM(message, container) {
    const messageElement = document.createElement('div');
    
    let classes = ['message'];
    if (message.is_self) classes.push('self');
    if (message.est_lu === 1 || message.est_lu === true) classes.push('read');
    if (message.is_pinned) classes.push('pinned');
    if (message.deleted_at) classes.push('deleted');
    if (message.status && message.status !== 'normal') classes.push(message.status);
    
    messageElement.className = classes.join(' ');
    messageElement.setAttribute('data-id', message.id);
    messageElement.setAttribute('data-timestamp', message.timestamp);
    messageElement.id = `message-${message.id}`;
    
    const messageDate = new Date(message.timestamp * 1000);
    const formattedDate = formatMessageDate(messageDate);
    
    // Construction XSS-safe via textContent + DOM API
    // Header
    const header = document.createElement('div');
    header.className = 'message-header';
    
    const senderDiv = document.createElement('div');
    senderDiv.className = 'sender';
    const strong = document.createElement('strong');
    strong.textContent = message.expediteur_nom || 'Inconnu';
    const typeSpan = document.createElement('span');
    typeSpan.className = 'sender-type';
    typeSpan.textContent = getParticipantType(message.sender_type);
    senderDiv.appendChild(strong);
    senderDiv.appendChild(typeSpan);
    
    const metaDiv = document.createElement('div');
    metaDiv.className = 'message-meta';
    
    if (message.status && message.status !== 'normal') {
        const tag = document.createElement('span');
        tag.className = `importance-tag ${escapeHTML(message.status)}`;
        tag.textContent = message.status;
        metaDiv.appendChild(tag);
    }
    
    if (message.edited_at) {
        const editTag = document.createElement('span');
        editTag.className = 'edited-tag';
        editTag.innerHTML = '<i class="fas fa-pencil-alt"></i> modifié';
        metaDiv.appendChild(editTag);
    }
    
    const dateSpan = document.createElement('span');
    dateSpan.className = 'date';
    dateSpan.textContent = formattedDate;
    metaDiv.appendChild(dateSpan);
    
    // Dropdown menu actions (seulement si pas supprimé)
    if (!message.deleted_at) {
        const dropdown = document.createElement('div');
        dropdown.className = 'message-dropdown';
        dropdown.innerHTML = `<button class="btn-icon message-menu-btn" title="Actions"><i class="fas fa-ellipsis-v"></i></button>`;
        const dropContent = document.createElement('div');
        dropContent.className = 'message-dropdown-content';
        
        if (message.is_self) {
            // Auteur peut éditer dans les 5 minutes
            const age = Math.floor(Date.now() / 1000) - message.timestamp;
            if (age < 300) {
                const editBtn = document.createElement('button');
                editBtn.innerHTML = '<i class="fas fa-edit"></i> Modifier';
                editBtn.addEventListener('click', () => editMessage(message.id));
                dropContent.appendChild(editBtn);
            }
            const delBtn = document.createElement('button');
            delBtn.innerHTML = '<i class="fas fa-trash"></i> Supprimer';
            delBtn.addEventListener('click', () => deleteMessage(message.id));
            dropContent.appendChild(delBtn);
        }
        
        if (!message.is_self) {
            const replyBtn = document.createElement('button');
            replyBtn.innerHTML = '<i class="fas fa-reply"></i> Répondre';
            replyBtn.addEventListener('click', () => replyToMessage(message.id, message.expediteur_nom));
            dropContent.appendChild(replyBtn);
        }
        
        dropdown.appendChild(dropContent);
        metaDiv.appendChild(dropdown);
    }
    
    header.appendChild(senderDiv);
    header.appendChild(metaDiv);
    messageElement.appendChild(header);
    
    // Reply quote
    if (message.parent_message_id) {
        const quote = document.createElement('div');
        quote.className = 'reply-quote';
        quote.innerHTML = '<i class="fas fa-reply"></i> En réponse';
        quote.addEventListener('click', () => scrollToMessage(message.parent_message_id));
        messageElement.appendChild(quote);
    }
    
    // Content (XSS-safe: textContent puis nl2br)
    const contentDiv = document.createElement('div');
    contentDiv.className = 'message-content';
    contentDiv.id = `msg-content-${message.id}`;
    const body = message.body || message.contenu || '';
    contentDiv.innerHTML = nl2br(escapeHTML(body));
    messageElement.appendChild(contentDiv);
    
    // Pièces jointes (SECURE: via download.php)
    if (message.pieces_jointes && message.pieces_jointes.length > 0 && !message.deleted_at) {
        const attachDiv = document.createElement('div');
        attachDiv.className = 'attachments';
        message.pieces_jointes.forEach(piece => {
            const link = document.createElement('a');
            link.href = `download.php?id=${piece.id || 0}`;
            link.className = 'attachment';
            link.target = '_blank';
            link.innerHTML = '<i class="fas fa-paperclip"></i> ';
            const nameSpan = document.createElement('span');
            nameSpan.textContent = piece.nom_fichier || piece.file_name || 'Fichier';
            link.appendChild(nameSpan);
            attachDiv.appendChild(link);
        });
        messageElement.appendChild(attachDiv);
    }
    
    // Reactions
    if (message.reactions && message.reactions.length > 0 && !message.deleted_at) {
        const reactDiv = document.createElement('div');
        reactDiv.className = 'message-reactions';
        message.reactions.forEach(r => {
            const btn = document.createElement('button');
            btn.className = `reaction-badge ${r.user_reacted ? 'active' : ''}`;
            btn.innerHTML = `${r.emoji} <span class="reaction-count">${r.count}</span>`;
            btn.addEventListener('click', () => toggleReaction(message.id, r.emoji));
            reactDiv.appendChild(btn);
        });
        messageElement.appendChild(reactDiv);
    }
    
    // Reaction add button
    if (!message.deleted_at) {
        const reactAdd = document.createElement('div');
        reactAdd.className = 'message-reactions-add';
        const addBtn = document.createElement('button');
        addBtn.className = 'btn-icon reaction-add-btn';
        addBtn.title = 'Ajouter une réaction';
        addBtn.innerHTML = '<i class="far fa-smile"></i>';
        addBtn.addEventListener('click', () => showReactionPicker(message.id));
        reactAdd.appendChild(addBtn);
        messageElement.appendChild(reactAdd);
    }
    
    // Footer
    const footer = document.createElement('div');
    footer.className = 'message-footer';
    
    if (message.is_self) {
        const statusHtml = (message.est_lu === 1 || message.est_lu === true) 
            ? '<div class="all-read"><i class="fas fa-check-double"></i> Vu</div>' 
            : `<div class="partial-read"><i class="fas fa-check"></i> <span class="read-count">0/${Math.max(0, (document.querySelectorAll('.participants-list li:not(.left)').length || 2) - 1)}</span></div>`;
        footer.innerHTML = `<div class="message-status"><div class="message-read-status" data-message-id="${message.id}">${statusHtml}</div></div>`;
    } else {
        const actionsDiv = document.createElement('div');
        actionsDiv.className = 'message-actions';
        const replyBtn = document.createElement('button');
        replyBtn.className = 'btn-icon';
        replyBtn.innerHTML = '<i class="fas fa-reply"></i> Répondre';
        replyBtn.addEventListener('click', () => replyToMessage(message.id, message.expediteur_nom));
        actionsDiv.appendChild(replyBtn);
        footer.appendChild(actionsDiv);
    }
    
    messageElement.appendChild(footer);
    container.appendChild(messageElement);
    
    // Observer le nouveau message pour le marquage auto
    if (!message.is_self && window.messageObserver) {
        window.messageObserver.observe(messageElement);
    }
}

/**
 * Fait défiler un élément jusqu'en bas
 * @param {HTMLElement} element - Élément à faire défiler jusqu'en bas
 */
function scrollToBottom(element) {
    if (element) {
        element.scrollTop = element.scrollHeight;
    }
}

/**
 * Vérifie si l'élément est défilé jusqu'en bas
 * @param {HTMLElement} element - Élément à vérifier
 * @returns {boolean} True si l'élément est défilé jusqu'en bas
 */
function isScrolledToBottom(element) {
    if (!element) return false;
    
    const tolerance = 50; // pixels de tolérance
    const scrollPosition = element.scrollTop + element.clientHeight;
    const scrollHeight = element.scrollHeight;
    
    return scrollPosition >= scrollHeight - tolerance;
}

// initConversationActions, showAddParticipantModal, closeAddParticipantModal → shared.js (setupConversationActions)
function initSidebarCollapse() {
    // Créer le bouton de toggle s'il n'existe pas déjà
    let sidebarToggle = document.getElementById('sidebar-toggle');
    const conversationPage = document.querySelector('.conversation-page');
    
    if (!conversationPage) return;
    
    // Créer le bouton s'il n'existe pas
    if (!sidebarToggle) {
        sidebarToggle = document.createElement('button');
        sidebarToggle.id = 'sidebar-toggle';
        sidebarToggle.className = 'sidebar-toggle';
        sidebarToggle.title = 'Afficher/masquer la liste des participants';
        sidebarToggle.innerHTML = '<i class="fas fa-bars"></i>';
        sidebarToggle.style.display = 'flex'; // Assurer que le bouton est visible
        sidebarToggle.style.zIndex = '1200';  // Mettre au premier plan
        
        // Insérer le bouton comme premier enfant de conversation-page
        conversationPage.prepend(sidebarToggle);
    }
    
    // Vérifier s'il y a une préférence sauvegardée
    const sidebarCollapsed = localStorage.getItem('conversation_sidebar_collapsed') === 'true';
    
    // Initialiser l'état en fonction de la préférence
    if (sidebarCollapsed) {
        conversationPage.setAttribute('data-sidebar-collapsed', 'true');
        sidebarToggle.innerHTML = '<i class="fas fa-bars"></i>';
    } else {
        conversationPage.setAttribute('data-sidebar-collapsed', 'false');
        sidebarToggle.innerHTML = '<i class="fas fa-times"></i>';
    }
    
    // Toggle la visibilité de la sidebar
    sidebarToggle.addEventListener('click', function() {
        const isCurrentlyCollapsed = conversationPage.getAttribute('data-sidebar-collapsed') === 'true';
        const newState = !isCurrentlyCollapsed;
        
        conversationPage.setAttribute('data-sidebar-collapsed', newState);
        
        // Mettre à jour l'icône du bouton
        this.innerHTML = newState ? 
            '<i class="fas fa-bars"></i>' : 
            '<i class="fas fa-times"></i>';
        
        // Sauvegarder la préférence
        localStorage.setItem('conversation_sidebar_collapsed', newState);
        
        // Déclencher un événement resize pour ajuster les composants
        window.dispatchEvent(new Event('resize'));
    });
}

// showAddParticipantModal, closeAddParticipantModal → shared.js (showAddParticipantModal + closeModal)

/**
 * Répond à un message spécifique
 * @param {number} messageId - ID du message
 * @param {string} senderName - Nom de l'expéditeur
 */
function replyToMessage(messageId, senderName) {
    // Montrer l'interface de réponse
    const replyInterface = document.getElementById('reply-interface');
    const replyTo = document.getElementById('reply-to');
    const textarea = document.querySelector('textarea[name="contenu"]');
    
    if (replyInterface && replyTo && textarea) {
        replyInterface.style.display = 'block';
        replyTo.textContent = 'Répondre à ' + senderName;
        
        // Stocker l'ID du message parent
        document.getElementById('parent-message-id').value = messageId;
        
        // Faire défiler vers le bas et mettre le focus sur le textarea
        textarea.focus();
        window.scrollTo(0, document.body.scrollHeight);
    }
}

/**
 * Annule une réponse à un message spécifique
 */
function cancelReply() {
    const replyInterface = document.getElementById('reply-interface');
    if (replyInterface) {
        replyInterface.style.display = 'none';
        document.getElementById('parent-message-id').value = '';
    }
}

/**
 * Promeut un participant au rôle de modérateur
 * @param {number} participantId - ID du participant
 */
function promoteToModerator(participantId) {
    if (confirm('Êtes-vous sûr de vouloir promouvoir ce participant en modérateur ?')) {
        document.getElementById('promote_participant_id').value = participantId;
        document.getElementById('promoteForm').submit();
    }
}

/**
 * Rétrograde un modérateur
 * @param {number} participantId - ID du participant
 */
function demoteFromModerator(participantId) {
    if (confirm('Êtes-vous sûr de vouloir rétrograder ce modérateur ?')) {
        document.getElementById('demote_participant_id').value = participantId;
        document.getElementById('demoteForm').submit();
    }
}

/**
 * Supprime un participant de la conversation
 * @param {number} participantId - ID du participant
 */
function removeParticipant(participantId) {
    if (confirm('Êtes-vous sûr de vouloir supprimer ce participant de la conversation ? Il n\'aura plus accès à cette conversation.')) {
        document.getElementById('remove_participant_id').value = participantId;
        document.getElementById('removeForm').submit();
    }
}

/**
 * Charge les participants disponibles selon le type sélectionné
 */
function loadParticipants() {
    const type = document.getElementById('participant_type').value;
    const select = document.getElementById('participant_id');
    const convId = new URLSearchParams(window.location.search).get('id');
    
    if (!type || !select || !convId) return;
    
    // Vider la liste actuelle
    select.innerHTML = '<option value="">Chargement...</option>';
    
    // Faire une requête AJAX pour récupérer les participants (via API v2 unifiée)
    apiFetch(`${getApiBase()}/messagerie.php?resource=participants&action=available&type=${type}&conv_id=${convId}`)
        .then(data => {
            select.innerHTML = '';
            
            const participants = data.participants || data;
            
            if (!participants || participants.length === 0) {
                select.innerHTML = '<option value="">Aucun participant disponible</option>';
                return;
            }
            
            select.innerHTML = '<option value="">Sélectionner un participant</option>';
            
            participants.forEach(participant => {
                const option = document.createElement('option');
                option.value = participant.id;
                option.textContent = participant.nom_complet;
                select.appendChild(option);
            });
        })
        .catch(error => {
            // Ne pas afficher d'erreur si la requête a été annulée (navigation)
            if (error.name !== 'AbortError') {
                select.innerHTML = '<option value="">Erreur lors du chargement</option>';
                console.error('Erreur:', error);
            }
        });
}

// escapeHTML, nl2br, getParticipantType, formatMessageDate → shared.js

/**
 * Validation du formulaire de message
 */
function setupMessageValidation() {
    const form = document.getElementById('messageForm');
    if (!form) return;
    
    const textarea = form.querySelector('textarea[name="contenu"]');
    const submitBtn = form.querySelector('button[type="submit"]');
    
    if (textarea) {
        textarea.addEventListener('input', function() {
            const isEmpty = this.value.trim() === '';
            if (submitBtn) {
                submitBtn.disabled = isEmpty;
            }
        });
        
        // Vérifier l'état initial
        textarea.dispatchEvent(new Event('input'));
    }
}

// afficherNotificationErreur + afficherNotification → shared.js

/**
 * Marquer un message comme lu via l'API v2
 */
function markMessageAsRead(messageId) {
    const convId = new URLSearchParams(window.location.search).get('id');
    
    apiFetch(`${getApiBase()}/messagerie.php?resource=messages&action=mark_read`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ messageId })
    })
    .then(data => {
        if (data.success) {
            const messageEl = document.querySelector(`.message[data-id="${messageId}"]`);
            if (messageEl) {
                messageEl.classList.add('read');
            }
            if (data.readStatus) updateReadStatus(data.readStatus);
        }
    })
    .catch(e => { if (e.name !== 'AbortError') console.error('Mark read error:', e); });
}

// ═══════════════════════════════════════════════════
// NOUVELLES FEATURES : Edit, Delete, Pin, Reactions
// ═══════════════════════════════════════════════════

/**
 * Modifier un message
 */
function editMessage(messageId) {
    const contentEl = document.getElementById(`msg-content-${messageId}`);
    if (!contentEl) return;
    
    const currentText = contentEl.innerText;
    
    // Remplacer le contenu par un textarea
    const editArea = document.createElement('textarea');
    editArea.className = 'edit-message-textarea';
    editArea.value = currentText;
    editArea.rows = 3;
    
    const editActions = document.createElement('div');
    editActions.className = 'edit-message-actions';
    
    const saveBtn = document.createElement('button');
    saveBtn.className = 'btn primary btn-sm';
    saveBtn.textContent = 'Sauvegarder';
    saveBtn.addEventListener('click', () => {
        const newBody = editArea.value.trim();
        if (!newBody) return;
        
        apiFetch(`${getApiBase()}/messagerie.php?resource=messages&action=edit`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ message_id: messageId, body: newBody })
        })
        .then(data => {
            if (data.success) {
                contentEl.innerHTML = nl2br(escapeHTML(newBody));
                // Ajouter badge "modifié"
                const meta = contentEl.closest('.message')?.querySelector('.message-meta');
                if (meta && !meta.querySelector('.edited-tag')) {
                    const editTag = document.createElement('span');
                    editTag.className = 'edited-tag';
                    editTag.innerHTML = '<i class="fas fa-pencil-alt"></i> modifié';
                    meta.insertBefore(editTag, meta.querySelector('.date'));
                }
                afficherNotification('Message modifié', 'success');
            } else {
                afficherNotificationErreur(data.error || 'Erreur lors de la modification');
            }
        })
        .catch(e => afficherNotificationErreur(e.message));
    });
    
    const cancelBtn = document.createElement('button');
    cancelBtn.className = 'btn secondary btn-sm';
    cancelBtn.textContent = 'Annuler';
    cancelBtn.addEventListener('click', () => {
        contentEl.innerHTML = nl2br(escapeHTML(currentText));
    });
    
    editActions.appendChild(saveBtn);
    editActions.appendChild(cancelBtn);
    
    contentEl.innerHTML = '';
    contentEl.appendChild(editArea);
    contentEl.appendChild(editActions);
    editArea.focus();
}

/**
 * Supprimer un message (soft delete)
 */
function deleteMessage(messageId) {
    if (!confirm('Supprimer ce message ?')) return;
    
    apiFetch(`${getApiBase()}/messagerie.php?resource=messages&action=delete`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ message_id: messageId })
    })
    .then(data => {
        if (data.success) {
            const msgEl = document.querySelector(`.message[data-id="${messageId}"]`);
            if (msgEl) {
                msgEl.classList.add('deleted');
                const content = msgEl.querySelector('.message-content');
                if (content) content.textContent = '[Message supprimé]';
                // Retirer les actions
                const dropdown = msgEl.querySelector('.message-dropdown');
                if (dropdown) dropdown.remove();
                const reactions = msgEl.querySelector('.message-reactions');
                if (reactions) reactions.remove();
                const reactAdd = msgEl.querySelector('.message-reactions-add');
                if (reactAdd) reactAdd.remove();
            }
            afficherNotification('Message supprimé', 'success');
        } else {
            afficherNotificationErreur(data.error || 'Erreur lors de la suppression');
        }
    })
    .catch(e => afficherNotificationErreur(e.message));
}

/**
 * Épingler / Désépingler un message
 */
function togglePinMessage(messageId) {
    apiFetch(`${getApiBase()}/messagerie.php?resource=messages&action=pin`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ message_id: messageId })
    })
    .then(data => {
        if (data.success) {
            const msgEl = document.querySelector(`.message[data-id="${messageId}"]`);
            if (msgEl) {
                if (data.is_pinned) {
                    msgEl.classList.add('pinned');
                    if (!msgEl.querySelector('.pinned-badge')) {
                        const badge = document.createElement('div');
                        badge.className = 'pinned-badge';
                        badge.innerHTML = '<i class="fas fa-thumbtack"></i> Épinglé';
                        msgEl.prepend(badge);
                    }
                } else {
                    msgEl.classList.remove('pinned');
                    msgEl.querySelector('.pinned-badge')?.remove();
                }
            }
            afficherNotification(data.is_pinned ? 'Message épinglé' : 'Message désépinglé', 'success');
        } else {
            afficherNotificationErreur(data.error || 'Erreur');
        }
    })
    .catch(e => afficherNotificationErreur(e.message));
}

/**
 * Toggle une réaction emoji sur un message
 */
function toggleReaction(messageId, emoji) {
    apiFetch(`${getApiBase()}/messagerie.php?resource=reactions&action=toggle`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ message_id: messageId, reaction: emoji })
    })
    .then(data => {
        if (data.success && data.reactions) {
            // Mettre à jour les badges de réaction
            const msgEl = document.querySelector(`.message[data-id="${messageId}"]`);
            if (!msgEl) return;
            
            let reactDiv = msgEl.querySelector('.message-reactions');
            if (!reactDiv) {
                reactDiv = document.createElement('div');
                reactDiv.className = 'message-reactions';
                const reactAdd = msgEl.querySelector('.message-reactions-add');
                if (reactAdd) msgEl.insertBefore(reactDiv, reactAdd);
                else msgEl.appendChild(reactDiv);
            }
            
            reactDiv.innerHTML = '';
            data.reactions.forEach(r => {
                const btn = document.createElement('button');
                btn.className = `reaction-badge ${r.user_reacted ? 'active' : ''}`;
                btn.innerHTML = `${r.emoji} <span class="reaction-count">${r.count}</span>`;
                btn.addEventListener('click', () => toggleReaction(messageId, r.emoji));
                reactDiv.appendChild(btn);
            });
        }
    })
    .catch(e => { if (e.name !== 'AbortError') console.error('Reaction error:', e); });
}

/**
 * Afficher le sélecteur de réactions
 */
function showReactionPicker(messageId) {
    // Fermer tout picker existant
    document.querySelectorAll('.reaction-picker').forEach(p => p.remove());
    
    const reactions = ['👍', '❤️', '😂', '😮', '😢', '👏'];
    
    const picker = document.createElement('div');
    picker.className = 'reaction-picker';
    
    reactions.forEach(emoji => {
        const btn = document.createElement('button');
        btn.className = 'reaction-picker-btn';
        btn.textContent = emoji;
        btn.addEventListener('click', () => {
            toggleReaction(messageId, emoji);
            picker.remove();
        });
        picker.appendChild(btn);
    });
    
    const msgEl = document.querySelector(`.message[data-id="${messageId}"]`);
    if (msgEl) {
        const reactAddDiv = msgEl.querySelector('.message-reactions-add');
        if (reactAddDiv) reactAddDiv.appendChild(picker);
    }
    
    // Fermer au clic en dehors
    setTimeout(() => {
        document.addEventListener('click', function closePicker(e) {
            if (!picker.contains(e.target) && !e.target.closest('.reaction-add-btn')) {
                picker.remove();
                document.removeEventListener('click', closePicker);
            }
        });
    }, 0);
}

/**
 * Scroll vers un message spécifique
 */
function scrollToMessage(messageId) {
    const el = document.getElementById(`message-${messageId}`) || document.querySelector(`.message[data-id="${messageId}"]`);
    if (el) {
        el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        el.classList.add('highlight');
        setTimeout(() => el.classList.remove('highlight'), 2000);
    }
}

/**
 * Charger les messages plus anciens
 */
function loadOlderMessages() {
    const convId = new URLSearchParams(window.location.search).get('id');
    const container = document.querySelector('.messages-container');
    const firstMsg = container?.querySelector('.message');
    if (!firstMsg || !convId) return;
    
    const beforeId = parseInt(firstMsg.getAttribute('data-id'), 10);
    const btn = document.querySelector('#load-more-messages button');
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Chargement...'; }
    
    apiFetch(`${getApiBase()}/messagerie.php?resource=messages&action=list&conv_id=${convId}&before=${beforeId}&limit=50`)
    .then(data => {
        if (data.success && data.messages?.length > 0) {
            const scrollBefore = container.scrollHeight;
            
            // Insérer au début
            data.messages.reverse().forEach(msg => {
                if (!document.querySelector(`.message[data-id="${msg.id}"]`)) {
                    const el = buildMessageElement(msg);
                    container.prepend(el);
                    if (!msg.is_self && window.messageObserver) window.messageObserver.observe(el);
                }
            });
            
            // Maintenir la position de scroll
            container.scrollTop += container.scrollHeight - scrollBefore;
            
            if (!data.has_more) {
                document.getElementById('load-more-messages')?.remove();
            }
        } else {
            document.getElementById('load-more-messages')?.remove();
        }
    })
    .catch(e => afficherNotificationErreur(e.message))
    .finally(() => {
        if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-history"></i> Charger les messages précédents'; }
    });
}

/**
 * Construit un élément message mais ne l'ajoute pas au DOM
 * (utilisé par loadOlderMessages pour prepend)
 */
function buildMessageElement(message) {
    // Réutiliser la logique de appendMessageToDOM avec un container temporaire
    const temp = document.createElement('div');
    appendMessageToDOM(message, temp);
    return temp.firstChild;
}

/**
 * Indicateur de frappe (typing indicator)
 */
function setupTypingIndicator() {
    const textarea = document.querySelector('textarea[name="contenu"]');
    const convId = new URLSearchParams(window.location.search).get('id');
    if (!textarea || !convId || !window.wsClient) return;
    
    let typingTimeout = null;
    let isTyping = false;
    
    textarea.addEventListener('input', () => {
        if (!isTyping) {
            isTyping = true;
            window.wsClient.emit?.('typing', { conversationId: convId, isTyping: true });
        }
        
        clearTimeout(typingTimeout);
        typingTimeout = setTimeout(() => {
            isTyping = false;
            window.wsClient.emit?.('typing', { conversationId: convId, isTyping: false });
        }, 2000);
    });
    
    // Écouter les indicateurs des autres
    window.wsClient.on?.('typing', (data) => {
        if (data.userId == window.currentUserId && data.userType == window.currentUserType) return;
        
        let indicator = document.getElementById('typing-indicator');
        if (data.isTyping) {
            if (!indicator) {
                indicator = document.createElement('div');
                indicator.id = 'typing-indicator';
                indicator.className = 'typing-indicator';
                const container = document.querySelector('.reply-box') || document.querySelector('.messages-container');
                if (container) container.parentNode.insertBefore(indicator, container.nextSibling);
            }
            indicator.textContent = `${data.userName || 'Quelqu\'un'} est en train d'écrire...`;
            indicator.style.display = 'block';
        } else {
            if (indicator) indicator.style.display = 'none';
        }
    });
}

/**
 * Setup dropdown menus on message action buttons
 */
function setupMessageDropdowns() {
    document.addEventListener('click', (e) => {
        const menuBtn = e.target.closest('.message-menu-btn');
        if (menuBtn) {
            e.stopPropagation();
            // Fermer les autres
            document.querySelectorAll('.message-dropdown-content.show').forEach(d => {
                if (d !== menuBtn.nextElementSibling) d.classList.remove('show');
            });
            menuBtn.nextElementSibling?.classList.toggle('show');
        } else {
            // Fermer tous les dropdowns
            document.querySelectorAll('.message-dropdown-content.show').forEach(d => d.classList.remove('show'));
        }
    });
}

/**
 * Configure WebSocket pour la conversation en temps réel
 * WebSocket-first: si WS connecté → pas de polling. Sinon, fallback polling après 5s.
 */
function setupWebSocketForConversation() {
    const convId = new URLSearchParams(window.location.search).get('id');
    if (!convId || !window.wsClient) return;
    
    // Rejoindre le canal de la conversation
    window.wsClient.joinConversation?.(convId);
    
    // Écouter les nouveaux messages
    window.wsClient.on?.('newMessage', (message) => {
        const isOwnMessage = message.sender_id == window.currentUserId && 
                            message.sender_type == window.currentUserType;
        
        if (!isOwnMessage) {
            const container = document.querySelector('.messages-container');
            if (container && !document.querySelector(`.message[data-id="${message.id}"]`)) {
                appendMessageToDOM(message, container);
                
                if (isScrolledToBottom(container)) scrollToBottom(container);
                else showNewMessagesIndicator(1);
            }
        }
    });
    
    // Écouter les mises à jour de read status
    window.wsClient.on?.('messageRead', (data) => {
        updateReadStatus(data);
    });
    
    // Après 5s, si WS pas connecté → démarrer le polling en fallback
    setTimeout(() => {
        if (!window.wsClient.connected && !window.unifiedPollingId) {
            console.log('WebSocket non connecté — démarrage du polling fallback');
            window._setupUnifiedPolling?.();
        }
    }, 5000);

    // Réactiver le polling si WebSocket se déconnecte
    window.wsClient.on?.('disconnect', () => {
        console.log('WebSocket déconnecté — réactivation du polling');
        if (!window.unifiedPollingId) {
            window._setupUnifiedPolling?.();
        }
    });

    // Si WS se (re)connecte, arrêter le polling
    window.wsClient.on?.('connect', () => {
        if (window.unifiedPollingId) {
            clearInterval(window.unifiedPollingId);
            window.unifiedPollingId = null;
            console.log('WebSocket connecté — polling désactivé');
        }
    });
}

// afficherNotification → shared.js