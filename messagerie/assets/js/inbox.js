/**
 * /assets/js/inbox.js — Logique spécifique à la page boîte de réception
 * Dépend de shared.js (chargé avant)
 */

document.addEventListener('DOMContentLoaded', function() {
    // Actions communes (archive, supprimer, modals…) — depuis shared.js
    setupConversationActions();

    // Scroll auto en bas des messages (si présent)
    const messagesContainer = document.querySelector('.messages-container');
    if (messagesContainer) messagesContainer.scrollTop = messagesContainer.scrollHeight;

    // Gestion des formulaires
    setupFormValidation();

    // Actions en masse
    setupBulkActions();

    // Gestionnaires d'erreurs globaux
    initErrorHandlers();
});

/**
 * Configuration des validations de formulaire
 */
function setupFormValidation() {
    // Validation des formulaires de message
    const messageForm = document.getElementById('messageForm');
    if (messageForm) {
        const textArea = messageForm.querySelector('textarea[name="contenu"]');
        const submitBtn = messageForm.querySelector('button[type="submit"]');
        
        if (textArea) {
            // Compteur de caractères
            const counter = document.createElement('div');
            counter.id = 'char-counter';
            counter.className = 'text-muted small';
            counter.style.color = '#6c757d';
            textArea.parentNode.insertBefore(counter, textArea.nextSibling);
            
            const maxLength = 10000;
            
            // Mise à jour en temps réel
            textArea.addEventListener('input', function() {
                const currentLength = this.value.length;
                counter.textContent = `${currentLength}/${maxLength} caractères`;
                
                if (currentLength > maxLength) {
                    counter.style.color = '#dc3545';
                    submitBtn.disabled = true;
                } else {
                    counter.style.color = '#6c757d';
                    submitBtn.disabled = false;
                }
            });
            
            // Déclencher l'événement au chargement
            textArea.dispatchEvent(new Event('input'));
        }
        
        // Empêcher la soumission si vide
        messageForm.addEventListener('submit', function(e) {
            const textareaContent = textArea.value.trim();
            if (textareaContent === '') {
                e.preventDefault();
                afficherNotificationErreur('Le message ne peut pas être vide');
            }
        });
    }
    
    // Gestion des pièces jointes
    const fileInput = document.getElementById('attachments');
    if (fileInput) {
        fileInput.addEventListener('change', updateFileList);
    }
}

/**
 * Mise à jour de la liste des fichiers sélectionnés
 */
function updateFileList() {
    const fileList = document.getElementById('file-list');
    if (!fileList) return;
    
    fileList.innerHTML = '';
    
    if (this.files.length > 0) {
        for (let i = 0; i < this.files.length; i++) {
            const file = this.files[i];
            const fileSize = formatFileSize(file.size);
            
            const fileInfo = document.createElement('div');
            fileInfo.className = 'file-info';
            const fileIcon = document.createElement('i');
            fileIcon.className = 'fas fa-file';
            const fileSpan = document.createElement('span');
            fileSpan.textContent = `${file.name} (${fileSize})`;
            fileInfo.appendChild(fileIcon);
            fileInfo.appendChild(document.createTextNode(' '));
            fileInfo.appendChild(fileSpan);
            fileList.appendChild(fileInfo);
        }
    }
}

/**
 * Configuration des actions en masse
 */
function setupBulkActions() {
    const selectAllCheckbox = document.getElementById('select-all-conversations');
    const actionButtons = document.querySelectorAll('.bulk-action-btn');
    
    if (!selectAllCheckbox) return;  // Sortir si l'élément n'existe pas
    
    // Sélectionner/désélectionner tous
    selectAllCheckbox.addEventListener('change', function() {

        
        const checkboxes = document.querySelectorAll('.conversation-select');
        checkboxes.forEach(checkbox => {
            checkbox.checked = this.checked;
            
            // Mettre à jour la classe 'selected' sur l'élément parent
            const conversationItem = checkbox.closest('.conversation-item');
            if (conversationItem) {
                if (this.checked) {
                    conversationItem.classList.add('selected');
                } else {
                    conversationItem.classList.remove('selected');
                }
            }
        });
        
        updateBulkActionButtons();
    });
    
    // Fix individual checkbox selection event handling
    document.addEventListener('click', function(e) {
        let target = e.target;
        
        // Check if target is a conversation checkbox
        if (target && target.classList.contains('conversation-select')) {
            e.stopPropagation(); // Prevent bubbling up to parent elements
            
            // Update parent conversation item styling
            const conversationItem = target.closest('.conversation-item');
            if (conversationItem) {
                if (target.checked) {
                    conversationItem.classList.add('selected');
                } else {
                    conversationItem.classList.remove('selected');
                }
            }
            
            updateBulkActionButtons();
        }
    }, true); // Use capture phase to ensure this runs before other handlers
    
    // Make sure the conversation items don't interfere with checkbox clicks
    document.querySelectorAll('.conversation-checkbox').forEach(function(element) {
        element.addEventListener('click', function(e) {
            // Prevent click from affecting parent elements
            e.stopPropagation();
            
            // Get the checkbox inside this element
            const checkbox = this.querySelector('input[type="checkbox"]');
            if (checkbox) {
                // Toggle the checkbox
                checkbox.checked = !checkbox.checked;
                
                // Trigger change event manually
                const event = new Event('change', { 'bubbles': true });
                checkbox.dispatchEvent(event);
                
                // Update styling
                const conversationItem = this.closest('.conversation-item');
                if (conversationItem) {
                    if (checkbox.checked) {
                        conversationItem.classList.add('selected');
                    } else {
                        conversationItem.classList.remove('selected');
                    }
                }
                
                updateBulkActionButtons();
            }
        });
    });
    
    // Configurer les clics sur les boutons d'action
    actionButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const action = this.dataset.action;
            if (!action) return;
            
            const selectedIds = Array.from(
                document.querySelectorAll('.conversation-select:checked')
            ).map(cb => parseInt(cb.dataset.id, 10));
            
            if (selectedIds.length === 0) {
                afficherNotificationErreur('Veuillez sélectionner au moins une conversation');
                return;
            }
            
            performBulkAction(action, selectedIds);
        });
    });
    
    // Exécuter une première fois pour initialiser l'état des boutons
    updateBulkActionButtons();
}

/**
 * Met à jour l'état des boutons d'action en fonction de la sélection
 */
function updateBulkActionButtons() {
    const selectedConvs = document.querySelectorAll('.conversation-select:checked');
    const selectedCount = selectedConvs.length;
    
    // Mettre à jour le texte de tous les boutons avec le nombre sélectionné correct
    const allButtons = document.querySelectorAll('.bulk-action-btn');
    allButtons.forEach(button => {
        const actionText = button.dataset.actionText || 'Appliquer';
        button.textContent = '';
        if (button.dataset.icon) {
            const icon = document.createElement('i');
            icon.className = `fas fa-${button.dataset.icon}`;
            button.appendChild(icon);
            button.appendChild(document.createTextNode(' '));
        }
        button.appendChild(document.createTextNode(`${actionText} (${selectedCount})`));
        
        // Activer/désactiver les boutons en fonction de la sélection
        button.disabled = selectedCount === 0;
        
        // Afficher/masquer les boutons selon la sélection
        if (selectedCount === 0) {
            button.style.display = 'none';
        } else {
            button.style.display = 'inline-flex';
        }
    });
    
    if (selectedCount === 0) return;
    
    // Vérifier si tous les messages sélectionnés sont lus ou non lus
    const btnMarkRead = document.querySelector('button[data-action="mark_read"]');
    const btnMarkUnread = document.querySelector('button[data-action="mark_unread"]');
    
    // Si on est dans un dossier autre que la corbeille, montrer les boutons Lu/Non lu
    const isTrashFolder = window.location.href.includes('folder=corbeille');
    
    if (btnMarkRead && btnMarkUnread && !isTrashFolder) {
        let hasReadMessages = false;
        let hasUnreadMessages = false;
        
        selectedConvs.forEach(checkbox => {
            const isRead = checkbox.dataset.read === '1';
            
            if (isRead) {
                hasReadMessages = true;
            } else {
                hasUnreadMessages = true;
            }
        });
        
        // Ajuster la visibilité des boutons selon la sélection
        if (btnMarkRead) {
            btnMarkRead.disabled = !hasUnreadMessages;
            btnMarkRead.style.display = hasUnreadMessages ? 'inline-flex' : 'none';
        }
        
        if (btnMarkUnread) {
            btnMarkUnread.disabled = !hasReadMessages;
            btnMarkUnread.style.display = hasReadMessages ? 'inline-flex' : 'none';
        }
    }
}

/**
 * Exécute une action en masse sur plusieurs conversations
 * @param {string} action
 * @param {Array} convIds
 */
function performBulkAction(action, convIds) {
    // Demander confirmation
    let confirmMessage = '';
    switch(action) {
        case 'delete':
            confirmMessage = `Êtes-vous sûr de vouloir supprimer ${convIds.length} conversation(s) ?`;
            break;
        case 'delete_permanently':
            confirmMessage = `Êtes-vous sûr de vouloir supprimer définitivement ${convIds.length} conversation(s) ? Cette action est irréversible.`;
            break;
        case 'archive':
            confirmMessage = `Êtes-vous sûr de vouloir archiver ${convIds.length} conversation(s) ?`;
            break;
        case 'restore':
            confirmMessage = `Êtes-vous sûr de vouloir restaurer ${convIds.length} conversation(s) ?`;
            break;
        case 'unarchive':
            confirmMessage = `Êtes-vous sûr de vouloir désarchiver ${convIds.length} conversation(s) ?`;
            break;
        case 'mark_read':
            confirmMessage = `Marquer ${convIds.length} conversation(s) comme lues ?`;
            break;
        case 'mark_unread':
            confirmMessage = `Marquer ${convIds.length} conversation(s) comme non lues ?`;
            break;
        default:
            confirmMessage = `Effectuer l'action "${action}" sur ${convIds.length} conversation(s) ?`;
    }
    
    if (confirm(confirmMessage)) {
        // Préparer les données pour l'envoi
        const data = {
            action: action,
            ids: convIds
        };
        
        // Montrer un indicateur de chargement
        document.body.style.cursor = 'wait';
        
        // Désactiver les boutons pendant le traitement
        document.querySelectorAll('.bulk-action-btn').forEach(btn => {
            btn.disabled = true;
        });
        
        // Construire le chemin API via shared.js
        const apiBase = getApiBase();

        // Envoyer la requête via apiFetch avec CSRF
        apiFetch(`${apiBase}/messagerie.php?resource=conversations&action=bulk`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        })
        .then(data => {
            if (data.success) {
                afficherNotificationErreur(`Action réussie sur ${data.count} conversation(s)`, 3000);
                setTimeout(() => window.location.reload(), 1000);
            } else {
                afficherNotificationErreur('Erreur lors de l\'action: ' + data.error);
                document.body.style.cursor = 'default';
                document.querySelectorAll('.bulk-action-btn').forEach(btn => btn.disabled = false);
                updateBulkActionButtons();
            }
        })
        .catch(error => {
            afficherNotificationErreur('Une erreur est survenue lors de l\'exécution de l\'action: ' + error.message);
            document.body.style.cursor = 'default';
            document.querySelectorAll('.bulk-action-btn').forEach(btn => btn.disabled = false);
            updateBulkActionButtons();
        });
    }
}

/**
 * Bascule l'affichage du menu d'actions rapides
 * @param {number} id
 */
function toggleQuickActions(id) {
    const menu = document.getElementById('quick-actions-' + id);
    if (!menu) return;
    
    // Fermer tous les autres menus
    document.querySelectorAll('.quick-actions-menu').forEach(item => {
        if (item !== menu) {
            item.classList.remove('active');
        }
    });
    
    // Basculer l'état du menu actuel
    menu.classList.toggle('active');
    
    // Mettre la conversation parente en avant-plan pendant que le menu est ouvert
    const conversationItem = menu.closest('.conversation-item');
    if (conversationItem) {
        if (menu.classList.contains('active')) {
            conversationItem.classList.add('active');
        } else {
            conversationItem.classList.remove('active');
        }
    }
    
    // Empêcher la propagation du clic pour éviter la navigation
    event.stopPropagation();
    return false;
}

/**
 * Marque une conversation comme lue
 * @param {number} convId
 */
function markConversationAsRead(convId) {
    apiFetch(`${getApiBase()}/messagerie.php?resource=conversations&action=mark_read&id=${convId}`)
        .then(data => {
            if (data.success) {
                window.location.reload();
            } else {
                afficherNotificationErreur('Erreur: ' + data.error);
            }
        })
        .catch(error => afficherNotificationErreur('Erreur: ' + error.message));
}

/**
 * Marque une conversation comme non lue
 * @param {number} convId
 */
function markConversationAsUnread(convId) {
    apiFetch(`${getApiBase()}/messagerie.php?resource=conversations&action=mark_unread&id=${convId}`)
        .then(data => {
            if (data.success) {
                window.location.reload();
            } else {
                afficherNotificationErreur('Erreur: ' + data.error);
            }
        })
        .catch(error => afficherNotificationErreur('Erreur: ' + error.message));
}

// showAddParticipantModal, closeModal, formatFileSize, afficherNotificationErreur → shared.js

/**
 * Ajouter des gestionnaires d'erreurs globaux
 */
function initErrorHandlers() {
    // Intercepter les erreurs non capturées
    window.addEventListener('error', function(event) {
        afficherNotificationErreur('Erreur JavaScript: ' + event.message);
    });
    
    // Intercepter les rejets de promesses non capturés
    window.addEventListener('unhandledrejection', function(event) {
        afficherNotificationErreur('Erreur asynchrone: ' + event.reason);
    });
}