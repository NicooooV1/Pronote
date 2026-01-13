/**
 * /assets/js/conversation.js - Scripts pour les conversations
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialiser les actions de conversation
    initConversationActions();
    
    // Initialiser la configuration des connections actives
    setupActiveConnections();
    
    // Initialisation du système de lecture des messages
    initReadTracker();
    
    // Actualisation en temps réel pour les modifications de conversation
    setupRealTimeUpdates();
    
    // Validation du formulaire de message
    setupMessageValidation();
    
    // Initialisation de l'envoi AJAX
    setupAjaxMessageSending();
    
    // Initialiser la sidebar rétractable
    initSidebarCollapse();
    
    // Nettoyage des ressources lors de la navigation
    setupBeforeUnloadHandler();
    
    // ✅ REMPLACER le polling par WebSocket
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

/**
 * Set common fetch options to ensure credentials are sent
 * @returns {Object} Fetch options with credentials
 */
function getFetchOptions() {
    return {
        credentials: 'same-origin',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    };
}

/**
 * Initialise le système de détection et de suivi des messages lus
 */
function initReadTracker() {
    // Variables pour l'état de lecture
    let lastReadMessageId = 0;
    let isMarkingMessage = false;
    let pollingActive = false;
    let pollingInterval = 3000; // Interroger le serveur toutes les 3 secondes
    let versionSum = 0; // Pour suivre la version des statuts de lecture
    
    // Récupérer le dernier message lu lors du chargement initial
    const messageElements = document.querySelectorAll('.message');
    if (messageElements.length > 0) {
        const lastMessage = messageElements[messageElements.length - 1];
        lastReadMessageId = parseInt(lastMessage.dataset.id || '0', 10);
    }
    
    // Configuration améliorée de l'IntersectionObserver
    const messageObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting && entry.intersectionRatio >= 0.7) {
                const messageEl = entry.target;
                const messageId = parseInt(messageEl.dataset.id, 10);
                
                // Éviter les requêtes inutiles pour les messages déjà lus ou envoyés par l'utilisateur
                if (!messageEl.classList.contains('read') && !messageEl.classList.contains('self')) {
                    markMessageAsRead(messageId);
                }
            }
        });
    }, {
        root: document.querySelector('.messages-container'),
        threshold: 0.7, // 70% visible pour être considéré comme lu
        rootMargin: '0px 0px -20% 0px' // Ignorer le bas de l'écran
    });
    
    // Observer tous les messages qui ne sont pas de l'utilisateur
    document.querySelectorAll('.message:not(.self)').forEach(message => {
        messageObserver.observe(message);
    });
    
    /**
     * Marque un message comme lu via l'API
     */
    function markMessageAsRead(messageId) {
        // Éviter les requêtes concurrentes
        if (isMarkingMessage) return;
        
        isMarkingMessage = true;
        
        const convId = new URLSearchParams(window.location.search).get('id');
        const apiPath = `${window.location.origin}${window.location.pathname.split('/').slice(0, -1).join('/')}/api/read_status.php`;

        fetch(`${apiPath}?action=read&conv_id=${convId}`, {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ messageId }),
            signal: window.activeConnections.abortController.signal,
            credentials: 'same-origin'
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`Erreur réseau: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                // Mettre à jour l'interface avec le nouveau statut
                updateReadStatus(data.read_status);
            } else {
                console.warn("Échec du marquage comme lu:", data.error || "Erreur inconnue");
            }
        })
        .catch(error => {
            // Ne pas afficher d'erreur si la requête a été annulée (navigation)
            if (error.name !== 'AbortError') {
                console.error('Erreur lors du marquage comme lu:', error);
                // Réessayer après un délai
                setTimeout(() => {
                    isMarkingMessage = false;
                    markMessageAsRead(messageId);
                }, 2000);
            }
        })
        .finally(() => {
            isMarkingMessage = false;
        });
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
        
        // Mettre à jour le contenu selon l'état de lecture
        if (readStatus.all_read) {
            statusEl.innerHTML = `
                <div class="all-read">
                    <i class="fas fa-check-double"></i> Vu
                </div>
            `;
            // Ajouter la classe 'read' au message
            messageEl.classList.add('read');
        } else if (readStatus.read_by_count > 0) {
            // Créer la liste des noms des lecteurs
            const readerNames = readStatus.readers && readStatus.readers.length > 0 
                ? readStatus.readers.map(r => r.nom_complet).join(', ')
                : 'Personne';
            
            statusEl.innerHTML = `
                <div class="partial-read">
                    <i class="fas fa-check"></i>
                    <span class="read-count">${readStatus.read_by_count}/${readStatus.total_participants - 1}</span>
                    <span class="read-tooltip" title="${readerNames}">
                        <i class="fas fa-info-circle"></i>
                    </span>
                </div>
            `;
        }
    }
    
    /**
     * Démarre le polling AJAX pour les mises à jour de lecture
     * Utilise setInterval au lieu de setTimeout récursif
     */
    function startPolling() {
        console.log('AJAX Read Status Polling: Démarrage');
        
        // Démarrer immédiatement avec un état initial
        pollForUpdates();
        
        // Gestion inefficace des timers avec setInterval
        // Si une requête prend plus de temps que l'intervalle, on risque d'avoir des requêtes simultanées
        const pollingIntervalId = setInterval(() => {
            // Ne déclencher le polling que si l'indicateur est actif
            if (window.activeConnections && window.activeConnections.readStatusPolling) {
                pollForUpdates();
            } else {
                console.log('AJAX Read Status Polling: En pause');
            }
        }, pollingInterval);
        
        // Stocker l'ID d'intervalle pour pouvoir l'annuler plus tard si nécessaire
        window.readStatusPollingId = pollingIntervalId;
        
        function pollForUpdates() {
            const convId = new URLSearchParams(window.location.search).get('id');
            if (!convId) {
                console.log('AJAX Read Status Polling: Pas d\'ID de conversation, arrêt');
                clearInterval(pollingIntervalId);
                return;
            }
            
            // Utiliser le nouvel endpoint de polling au lieu du SSE
            fetch(`api/read_status.php?action=read-polling&conv_id=${convId}&version=${versionSum}&since=${lastReadMessageId}`, {
                signal: window.activeConnections.abortController.signal,
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`Erreur réseau: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        // Mettre à jour la version pour les prochaines requêtes
                        versionSum = data.version || 0;
                        
                        // Si c'est la première requête et qu'on a un état initial
                        if (data.initialState) {
                            // Mettre à jour tous les statuts de lecture
                            Object.entries(data.initialState).forEach(([messageId, readStatus]) => {
                                updateReadStatus(readStatus);
                            });
                        }
                        
                        // Traiter les mises à jour
                        if (data.hasUpdates && data.updates) {
                            data.updates.forEach(update => {
                                updateReadStatus(update.read_status);
                                
                                // Mettre à jour lastReadMessageId si nécessaire
                                if (update.messageId > lastReadMessageId) {
                                    lastReadMessageId = update.messageId;
                                }
                            });
                        }
                    } else {
                        console.error('Erreur de polling des statuts de lecture:', data.error || 'Erreur inconnue');
                    }
                })
                .catch(error => {
                    // Ne pas afficher d'erreur si la requête a été annulée (navigation)
                    if (error.name !== 'AbortError') {
                        console.error('Erreur de polling des statuts de lecture:', error);
                    }
                });
        }
        
        // Gérer les événements de visibilité du document
        document.addEventListener('visibilitychange', function() {
            if (document.visibilityState === 'visible') {
                // Réactiver le polling et actualiser immédiatement
                window.activeConnections.readStatusPolling = true;
                // Réinitialiser l'intervalle quand la page est visible
                pollingInterval = 3000;
                // Forcer une mise à jour immédiate
                pollForUpdates();
            } else {
                // Ralentir le polling quand l'onglet n'est pas actif
                pollingInterval = 10000; // 10 secondes quand inactif
            }
        });
        
        // Réinitialiser l'intervalle quand l'onglet redevient actif
        window.addEventListener('focus', function() {
            pollingInterval = 3000; // Retour à 3 secondes quand actif
            // Forcer une mise à jour immédiate
            pollForUpdates();
        });
    }
    
    // Démarrer le polling AJAX
    startPolling();
    
    // Marque un message comme non lu
    function markMessageAsUnread(messageId) {
        if (isMarkingMessage) return;
        
        isMarkingMessage = true;
        
        fetch(`api/messages.php?id=${messageId}&action=mark_unread`, {
            signal: window.activeConnections.abortController.signal,
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`Erreur réseau: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    // Mettre à jour l'interface utilisateur
                    const message = document.querySelector(`.message[data-id="${messageId}"]`);
                    if (message) {
                        message.classList.remove('read');
                        
                        // Mettre à jour le bouton
                        const unreadBtn = message.querySelector('.mark-unread-btn');
                        if (unreadBtn) {
                            const readBtn = document.createElement('button');
                            readBtn.className = 'btn-icon mark-read-btn';
                            readBtn.setAttribute('data-message-id', messageId);
                            readBtn.innerHTML = '<i class="fas fa-envelope-open"></i> Marquer comme lu';
                            
                            unreadBtn.parentNode.replaceChild(readBtn, unreadBtn);
                        }
                    }
                    
                    // Mettre à jour le statut de lecture
                    if (data.readStatus) {
                        updateReadStatus(data.readStatus);
                    }
                } else {
                    afficherNotificationErreur("Erreur: " + (data.error || "Une erreur est survenue"));
                    console.error('Erreur:', data.error);
                }
            })
            .catch(error => {
                // Ne pas afficher d'erreur si la requête a été annulée (navigation)
                if (error.name !== 'AbortError') {
                    afficherNotificationErreur("Erreur: " + error.message);
                    console.error('Erreur:', error);
                }
            })
            .finally(() => {
                isMarkingMessage = false;
            });
    }
}

/**
 * Configure les mises à jour en temps réel pour la conversation
 */
function setupRealTimeUpdates() {
    // Variables pour la gestion des mises à jour
    const convId = new URLSearchParams(window.location.search).get('id');
    const refreshInterval = 5000; // 5 secondes entre chaque vérification
    let lastTimestamp = 0;
    let isCheckingForUpdates = false; // Flag pour éviter les requêtes concurrentes
    
    // Initialiser le timestamp de départ avec le dernier message
    const lastMessage = document.querySelector('.message:last-child');
    if (lastMessage) {
        lastTimestamp = parseInt(lastMessage.getAttribute('data-timestamp') || '0', 10);
    }
    
    // Ne pas continuer si on n'est pas sur une page de conversation
    if (!convId) return;
    
    // Fonction de vérification des mises à jour
    function checkForUpdates() {
        // Éviter les requêtes concurrentes
        if (isCheckingForUpdates || !window.activeConnections.messagePolling) {
            return;
        }
        
        // Vérifier si l'utilisateur a le focus sur l'onglet et n'est pas en train d'écrire
        const textareaActive = document.querySelector('textarea:focus');
        const modalOpen = document.querySelector('.modal[style*="display: block"]');
        
        if (textareaActive || modalOpen) {
            // L'utilisateur est en train d'écrire ou un modal est ouvert, on reporte la vérification
            return;
        }
        
        isCheckingForUpdates = true;
        const apiPath = `${window.location.origin}${window.location.pathname.split('/').slice(0, -1).join('/')}/api/messages.php`;
        
        // Requête de vérification avec gestion d'erreur améliorée
        fetch(`${apiPath}?conv_id=${convId}&action=check_updates&last_timestamp=${lastTimestamp}`, {
            signal: window.activeConnections.abortController.signal,
            credentials: 'same-origin'  // Add credentials for session management
        })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`Erreur réseau: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success && data.hasUpdates) {
                    // Si des mises à jour sont disponibles, les récupérer
                    fetchNewMessages();
                }
                
                // Vérifier les changements de participants
                if (data.success && data.participantsChanged) {
                    refreshParticipantsList();
                }
                
                // Mettre à jour le timestamp - Utiliser le timestamp du dernier message
                // au lieu du timestamp actuel pour éviter les confusions
                if (data.timestamp) {
                    lastTimestamp = data.timestamp;
                }
                
                isCheckingForUpdates = false;
            })
            .catch(error => {
                // Ne pas afficher d'erreur si la requête a été annulée (navigation)
                if (error.name !== 'AbortError') {
                    console.error('Erreur lors de la vérification des mises à jour:', error);
                    isCheckingForUpdates = false;
                }
            });
    }
    
    /**
     * Affiche un indicateur de nouveaux messages
     * @param {number} count - Nombre de nouveaux messages
     */
    function showNewMessagesIndicator(count) {
        // Créer ou mettre à jour un indicateur flottant
        let indicator = document.getElementById('new-messages-indicator');
        
        if (!indicator) {
            indicator = document.createElement('div');
            indicator.id = 'new-messages-indicator';
            indicator.style.position = 'fixed';
            indicator.style.bottom = '100px';
            indicator.style.right = '20px';
            indicator.style.backgroundColor = '#009b72';
            indicator.style.color = 'white';
            indicator.style.padding = '10px 15px';
            indicator.style.borderRadius = '20px';
            indicator.style.boxShadow = '0 2px 10px rgba(0,0,0,0.2)';
            indicator.style.cursor = 'pointer';
            indicator.style.zIndex = '1000';
            
            indicator.addEventListener('click', function() {
                const messagesContainer = document.querySelector('.messages-container');
                scrollToBottom(messagesContainer);
                this.style.display = 'none';
            });
            
            document.body.appendChild(indicator);
        }
        
        indicator.textContent = `${count} nouveau(x) message(s)`;
        indicator.style.display = 'block';
        
        // Masquer après un délai si non cliqué
        setTimeout(() => {
            if (indicator) indicator.style.display = 'none';
        }, 5000);
    }
    
    /**
     * Joue un son de notification (optionnel)
     */
    function playNotificationSound() {
        // On pourrait implémenter un son de notification ici
        // Par exemple:
        // const audio = new Audio('/assets/sounds/notification.mp3');
        // audio.play();
    }
    
    /**
     * Récupère et ajoute les nouveaux messages à la conversation
     */
    function fetchNewMessages() {
        const apiPath = `${window.location.origin}${window.location.pathname.split('/').slice(0, -1).join('/')}/api/messages.php`;
        
        fetch(`${apiPath}?conv_id=${convId}&action=get_new&last_timestamp=${lastTimestamp}`, {
            signal: window.activeConnections.abortController.signal,
            credentials: 'same-origin'  // Add credentials for session management
        })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`Erreur réseau: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success && data.messages && data.messages.length > 0) {
                    // Mettre à jour la référence du dernier timestamp
                    const messages = data.messages;
                    const messagesContainer = document.querySelector('.messages-container');
                    
                    // On était déjà en bas avant les nouveaux messages?
                    const wasAtBottom = isScrolledToBottom(messagesContainer);
                    
                    // Ajouter chaque nouveau message
                    messages.forEach(message => {
                        appendMessageToDOM(message, messagesContainer);
                        
                        // Mise à jour du lastTimestamp avec le plus récent
                        if (message.timestamp > lastTimestamp) {
                            lastTimestamp = message.timestamp;
                        }
                    });
                    
                    // Faire défiler vers le bas si l'utilisateur était déjà en bas
                    if (wasAtBottom) {
                        scrollToBottom(messagesContainer);
                    } else {
                        // Sinon, indiquer qu'il y a de nouveaux messages
                        showNewMessagesIndicator(messages.length);
                    }
                    
                    // Lecture audio pour notification (optionnelle)
                    playNotificationSound();
                }
            })
            .catch(error => {
                // Ne pas afficher d'erreur si la requête a été annulée (navigation)
                if (error.name !== 'AbortError') {
                    console.error('Erreur lors de la récupération des nouveaux messages:', error);
                }
            });
    }
    
    /**
     * Actualise la liste des participants
     */
    function refreshParticipantsList() {
        const apiPath = `${window.location.origin}${window.location.pathname.split('/').slice(0, -1).join('/')}/api/participants.php`;
        
        fetch(`${apiPath}?conv_id=${convId}&action=get_list`, {
            signal: window.activeConnections.abortController.signal,
            credentials: 'same-origin'  // Add credentials for session management
        })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`Erreur réseau: ${response.status}`);
                }
                return response.text();
            })
            .then(html => {
                const participantsList = document.querySelector('.participants-list');
                if (participantsList) {
                    participantsList.innerHTML = html;
                }
            })
            .catch(error => {
                // Ne pas afficher d'erreur si la requête a été annulée (navigation)
                if (error.name !== 'AbortError') {
                    console.error('Erreur lors de l\'actualisation des participants:', error);
                }
            });
    }
    
    // Utiliser setInterval au lieu de setTimeout récursif pour plus de robustesse
    const messagePollingId = setInterval(() => {
        // Ne déclencher le polling que si l'indicateur est actif
        if (window.activeConnections && window.activeConnections.messagePolling) {
            checkForUpdates();
        }
    }, refreshInterval);
    
    // Stocker l'ID d'intervalle pour pouvoir l'annuler plus tard si nécessaire
    window.messagePollingId = messagePollingId;
    
    // Effectuer une vérification initiale
    setTimeout(checkForUpdates, 1000);
    
    // Gestion du scroll - si l'utilisateur fait défiler vers le bas, masquer l'indicateur
    const messagesContainer = document.querySelector('.messages-container');
    if (messagesContainer) {
        messagesContainer.addEventListener('scroll', function() {
            if (isScrolledToBottom(this)) {
                const indicator = document.getElementById('new-messages-indicator');
                if (indicator) indicator.style.display = 'none';
            }
        });
    }
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
        
        // Envoyer la requête AJAX
        const apiPath = `${window.location.origin}${window.location.pathname.split('/').slice(0, -1).join('/')}/api/messages.php`;
        
        fetch(apiPath, {
            method: 'POST',
            body: formData,
            signal: window.activeConnections.abortController.signal,
            credentials: 'same-origin'  // Add credentials for session management
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
 * Ajoute un message au DOM
 * @param {Object} message - Objet message à ajouter
 * @param {HTMLElement} container - Conteneur où ajouter le message
 */
function appendMessageToDOM(message, container) {
    // Créer un nouvel élément div pour le message
    const messageElement = document.createElement('div');
    
    // Déterminer les classes du message
    let classes = ['message'];
    if (message.is_self) classes.push('self');
    if (message.est_lu === 1 || message.est_lu === true) classes.push('read');
    if (message.status) classes.push(message.status);
    
    messageElement.className = classes.join(' ');
    messageElement.setAttribute('data-id', message.id);
    messageElement.setAttribute('data-timestamp', message.timestamp);
    
    // Formater la date lisible
    const messageDate = new Date(message.timestamp * 1000);
    const formattedDate = formatMessageDate(messageDate);
    
    // Construction du HTML du message
    let messageHTML = `
        <div class="message-header">
            <div class="sender">
                <strong>${escapeHTML(message.expediteur_nom)}</strong>
                <span class="sender-type">${getParticipantType(message.sender_type)}</span>
            </div>
            <div class="message-meta">
    `;
    
    // Ajouter le tag d'importance si non standard
    if (message.status && message.status !== 'normal') {
        messageHTML += `<span class="importance-tag ${message.status}">${message.status}</span>`;
    }
    
    messageHTML += `
                <span class="date">${formattedDate}</span>
            </div>
        </div>
        <div class="message-content">${nl2br(escapeHTML(message.body || message.contenu))}</div>
    `;
    
    // Ajouter les pièces jointes si présentes
    if (message.pieces_jointes && message.pieces_jointes.length > 0) {
        messageHTML += `<div class="attachments">`;
        message.pieces_jointes.forEach(piece => {
            messageHTML += `
                <a href="${piece.chemin}" class="attachment" target="_blank">
                    <i class="fas fa-paperclip"></i> ${escapeHTML(piece.nom_fichier)}
                </a>
            `;
        });
        messageHTML += `</div>`;
    }
    
    messageHTML += `<div class="message-footer">`;
    
    // Ajouter le statut de lecture pour les propres messages de l'utilisateur
    if (message.is_self) {
        messageHTML += `
            <div class="message-status">
                <div class="message-read-status" data-message-id="${message.id}">
                    ${(message.est_lu === 1 || message.est_lu === true) ? 
                        '<div class="all-read"><i class="fas fa-check-double"></i> Vu</div>' : 
                        '<div class="partial-read"><i class="fas fa-check"></i> <span class="read-count">0/' + 
                        (document.querySelectorAll('.participants-list li:not(.left)').length - 1) + 
                        '</span></div>'}
                </div>
            </div>
        `;
    } else {
        // Ajouter les actions pour les messages des autres
        messageHTML += `
            <div class="message-actions">
                ${(message.est_lu === 1 || message.est_lu === true) ? 
                    `<button class="btn-icon mark-unread-btn" data-message-id="${message.id}">
                        <i class="fas fa-envelope"></i> Marquer comme non lu
                    </button>` : 
                    `<button class="btn-icon mark-read-btn" data-message-id="${message.id}">
                        <i class="fas fa-envelope-open"></i> Marquer comme lu
                    </button>`
                }
                <button class="btn-icon" onclick="replyToMessage(${message.id}, '${escapeHTML(message.expediteur_nom)}')">
                    <i class="fas fa-reply"></i> Répondre
                </button>
            </div>
        `;
    }
    
    messageHTML += `</div>`;
    
    // Définir le HTML du message
    messageElement.innerHTML = messageHTML;
    
    // Ajouter le message au conteneur
    container.appendChild(messageElement);
    
    // Observer le nouveau message si ce n'est pas un message de l'utilisateur
    if (!message.is_self) {
        const messageObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting && entry.intersectionRatio >= 0.7) {
                    const messageEl = entry.target;
                    const messageId = parseInt(messageEl.dataset.id, 10);
                    
                    if (!messageEl.classList.contains('read')) {
                        markMessageAsRead(messageId);
                        messageObserver.unobserve(messageEl);
                    }
                }
            });
        }, {
            root: document.querySelector('.messages-container'),
            threshold: 0.7,
            rootMargin: '0px 0px -20% 0px'
        });
        
        messageObserver.observe(messageElement);
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

/**
 * Initialise les actions principales de conversation
 */
function initConversationActions() {
    // Gestion du scroll dans les conversations
    const messagesContainer = document.querySelector('.messages-container');
    if (messagesContainer) {
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }
    
    // Actions sur les conversations et participants
    // Archiver une conversation
    const archiveBtn = document.getElementById('archive-btn');
    if (archiveBtn) {
        archiveBtn.addEventListener('click', function(e) {
            e.preventDefault();
            if (confirm('Êtes-vous sûr de vouloir archiver cette conversation ?')) {
                document.getElementById('archiveForm').submit();
            }
        });
    }
    
    // Supprimer une conversation
    const deleteBtn = document.getElementById('delete-btn');
    if (deleteBtn) {
        deleteBtn.addEventListener('click', function(e) {
            e.preventDefault();
            if (confirm('Êtes-vous sûr de vouloir supprimer cette conversation ?')) {
                document.getElementById('deleteForm').submit();
            }
        });
    }
    
    // Restaurer une conversation
    const restoreBtn = document.getElementById('restore-btn');
    if (restoreBtn) {
        restoreBtn.addEventListener('click', function(e) {
            e.preventDefault();
            document.getElementById('restoreForm').submit();
        });
    }
    
    // Gestion du modal pour l'ajout de participants
    const addParticipantBtn = document.getElementById('add-participant-btn');
    if (addParticipantBtn) {
        addParticipantBtn.addEventListener('click', function(e) {
            e.preventDefault();
            showAddParticipantModal();
        });
    }
    
    // Gestion de la fermeture du modal
    const closeModalBtns = document.querySelectorAll('.close');
    closeModalBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            closeAddParticipantModal();
        });
    });
    
    // Fermeture du modal en cliquant en dehors
    window.addEventListener('click', function(event) {
        const modals = document.querySelectorAll('.modal');
        modals.forEach(modal => {
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        });
    });
}

/**
 * Initialise la fonctionnalité de sidebar rétractable
 */
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

/**
 * Affiche le modal d'ajout de participants
 */
function showAddParticipantModal() {
    const modal = document.getElementById('addParticipantModal');
    if (modal) {
        modal.style.display = 'block';
    }
}

/**
 * Ferme le modal d'ajout de participants
 */
function closeAddParticipantModal() {
    const modal = document.getElementById('addParticipantModal');
    if (modal) {
        modal.style.display = 'none';
    }
}

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
    
    // Faire une requête AJAX pour récupérer les participants
    const apiPath = `${window.location.origin}${window.location.pathname.split('/').slice(0, -1).join('/')}/api/participants.php`;
    
    fetch(`${apiPath}?type=${type}&conv_id=${convId}`, {
        signal: window.activeConnections.abortController.signal,
        credentials: 'same-origin'  // Add credentials for session management
    })
        .then(response => {
            if (!response.ok) {
                throw new Error(`Erreur réseau: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            select.innerHTML = '';
            
            if (data.length === 0) {
                select.innerHTML = '<option value="">Aucun participant disponible</option>';
                return;
            }
            
            select.innerHTML = '<option value="">Sélectionner un participant</option>';
            
            data.forEach(participant => {
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

/**
 * Échappe les caractères HTML
 * @param {string} text - Texte à échapper
 * @returns {string} Texte échappé
 */
function escapeHTML(text) {
    if (!text) return '';
    return text
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

/**
 * Convertit les retours à la ligne en <br>
 * @param {string} text - Texte à convertir
 * @returns {string} Texte avec des <br>
 */
function nl2br(text) {
    if (!text) return '';
    return text.replace(/\n/g, '<br>');
}

/**
 * Renvoie le libellé du type de participant
 * @param {string} type - Type de participant
 * @returns {string} Libellé formaté
 */
function getParticipantType(type) {
    const types = {
        'eleve': 'Élève',
        'parent': 'Parent',
        'professeur': 'Professeur',
        'vie_scolaire': 'Vie scolaire',
        'administrateur': 'Administrateur'
    };
    return types[type] || type;
}

/**
 * Formater la date d'un message
 * @param {Date} date - Date à formater
 * @returns {string} Date formatée
 */
function formatMessageDate(date) {
    const now = new Date();
    const diffMs = now - date;
    const diffSecs = Math.floor(diffMs / 1000);
    const diffMins = Math.floor(diffSecs / 60);
    const diffHours = Math.floor(diffMins / 60);
    const diffDays = Math.floor(diffHours / 24);
    
    if (diffSecs < 60) {
        return "À l'instant";
    } else if (diffMins < 60) {
        return `Il y a ${diffMins} minute${diffMins > 1 ? 's' : ''}`;
    } else if (diffHours < 24) {
        return `Il y a ${diffHours} heure${diffHours > 1 ? 's' : ''}`;
    } else if (diffDays < 2) {
        return `Hier à ${date.getHours().toString().padStart(2, '0')}:${date.getMinutes().toString().padStart(2, '0')}`;
    } else {
        return `${date.getDate().toString().padStart(2, '0')}/${(date.getMonth()+1).toString().padStart(2, '0')}/${date.getFullYear()} à ${date.getHours().toString().padStart(2, '0')}:${date.getMinutes().toString().padStart(2, '0')}`;
    }
}

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

/**
 * Utilitaire pour afficher des notifications d'erreur
 * @param {string} message - Message d'erreur
 * @param {number} duration - Durée d'affichage
 */
function afficherNotificationErreur(message, duration = 5000) {
    // Créer la div de notification si elle n'existe pas
    let notifContainer = document.getElementById('error-notification-container');
    
    if (!notifContainer) {
        notifContainer = document.createElement('div');
        notifContainer.id = 'error-notification-container';
        
        // Styles pour centrer la notification
        notifContainer.style.position = 'fixed';
        notifContainer.style.top = '50%';
        notifContainer.style.left = '50%';
        notifContainer.style.transform = 'translate(-50%, -50%)';
        notifContainer.style.zIndex = '10000';
        notifContainer.style.width = 'auto';
        notifContainer.style.maxWidth = '80%';
        
        document.body.appendChild(notifContainer);
    }
    
    // Créer la notification
    const notification = document.createElement('div');
    notification.className = 'error-notification';
    
    // Styles de la notification
    notification.style.backgroundColor = '#f8d7da';
    notification.style.color = '#721c24';
    notification.style.padding = '15px 20px';
    notification.style.margin = '10px';
    notification.style.borderRadius = '5px';
    notification.style.boxShadow = '0 4px 10px rgba(0, 0, 0, 0.2)';
    notification.style.display = 'flex';
    notification.style.justifyContent = 'space-between';
    notification.style.alignItems = 'center';
    notification.style.minWidth = '300px';
    
    // Créer le contenu de la notification
    const content = document.createElement('div');
    content.innerHTML = `<i class="fas fa-exclamation-circle"></i> ${message}`;
    
    // Créer le bouton de fermeture
    const closeBtn = document.createElement('button');
    closeBtn.innerHTML = '&times;';
    closeBtn.style.background = 'none';
    closeBtn.style.border = 'none';
    closeBtn.style.color = '#721c24';
    closeBtn.style.fontSize = '20px';
    closeBtn.style.cursor = 'pointer';
    closeBtn.style.marginLeft = '15px';
    
    // Ajouter le contenu et le bouton à la notification
    notification.appendChild(content);
    notification.appendChild(closeBtn);
    
    // Ajouter la notification au conteneur
    notifContainer.appendChild(notification);
    
    // Fermer la notification quand on clique sur le bouton
    closeBtn.addEventListener('click', function() {
        notifContainer.removeChild(notification);
        
        // Supprimer le conteneur s'il n'y a plus de notifications
        if (notifContainer.children.length === 0) {
            document.body.removeChild(notifContainer);
        }
    });
    
    // Fermer automatiquement après la durée spécifiée
    setTimeout(function() {
        if (notification.parentNode === notifContainer) {
            notifContainer.removeChild(notification);
            
            // Supprimer le conteneur s'il n'y a plus de notifications
            if (notifContainer.children.length === 0) {
                document.body.removeChild(notifContainer);
            }
        }
    }, duration);
    
    return notification;
}

/**
 * Fonction utilitaire pour marquer un message comme lu
 * Exposée globalement pour être utilisée par d'autres scripts
 * @param {number} messageId - ID du message à marquer comme lu
 */
function markMessageAsRead(messageId) {
    const convId = new URLSearchParams(window.location.search).get('id');
    
    fetch(`api/read_status.php?action=read&conv_id=${convId}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ messageId }),
        signal: window.activeConnections.abortController.signal
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Mettre à jour l'interface avec le nouveau statut
            const messageEl = document.querySelector(`.message[data-id="${messageId}"]`);
            if (messageEl) {
                messageEl.classList.add('read');
                
                // Mettre à jour le bouton si présent
                const readBtn = messageEl.querySelector('.mark-read-btn');
                if (readBtn) {
                    const unreadBtn = document.createElement('button');
                    unreadBtn.className = 'btn-icon mark-unread-btn';
                    unreadBtn.setAttribute('data-message-id', messageId);
                    unreadBtn.innerHTML = '<i class="fas fa-envelope"></i> Marquer comme non lu';
                    
                    readBtn.parentNode.replaceChild(unreadBtn, readBtn);
                }
            }
        }
    })
    .catch(error => {
        if (error.name !== 'AbortError') {
            console.error('Erreur lors du marquage comme lu:', error);
        }
    });
}

/**
 * Configure WebSocket pour la conversation en temps réel
 */
function setupWebSocketForConversation() {
    const convId = new URLSearchParams(window.location.search).get('id');
    if (!convId || !window.wsClient) return;
    
    // Rejoindre le canal de la conversation
    window.wsClient.joinConversation(convId);
    
    // Écouter les nouveaux messages
    window.wsClient.on('newMessage', (message) => {
        console.log('Nouveau message reçu via WebSocket:', message);
        
        // Vérifier si ce n'est pas notre propre message (éviter doublon)
        const isOwnMessage = message.sender_id == window.currentUserId && 
                            message.sender_type == window.currentUserType;
        
        if (!isOwnMessage) {
            const container = document.querySelector('.messages-container');
            if (container) {
                appendMessageToDOM(message, container);
                
                // Auto-scroll si l'utilisateur est en bas
                if (isScrolledToBottom(container)) {
                    scrollToBottom(container);
                } else {
                    showNewMessagesIndicator(1);
                }
            }
        }
    });
    
    // ⚠️ SUPPRIMER l'ancien polling setInterval
    // if (window.messagePollingId) {
    //     clearInterval(window.messagePollingId);
    // }
    
    // Fallback: si WebSocket non connecté après 5s, activer polling
    setTimeout(() => {
        if (!window.wsClient.connected) {
            console.warn('WebSocket non connecté, activation du fallback polling');
            enablePollingFallback();
        }
    }, 5000);
}

/**
 * Fallback polling si WebSocket échoue
 */
function enablePollingFallback() {
    const convId = new URLSearchParams(window.location.search).get('id');
    if (!convId) return;
    
    console.log('Activation du fallback polling (30s)');
    
    // Polling moins fréquent qu'avant (30s au lieu de 5s)
    window.messagePollingId = setInterval(() => {
        checkForUpdates();
    }, 30000);
}