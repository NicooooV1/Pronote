/**
 * /assets/js/notifications.js - Gestion des notifications
 */

document.addEventListener('DOMContentLoaded', function() {
    // ✅ REMPLACER le polling par WebSocket
    setupWebSocketNotifications();
    
    // Check initial
    checkNotifications();
    
    // Gérer les clics sur les notifications
    initNotificationClicks();
    
    // Demander la permission pour les notifications du navigateur si l'utilisateur n'a pas encore décidé
    requestNotificationPermission();
});

/**
 * Configure WebSocket pour les notifications
 */
function setupWebSocketNotifications() {
    if (!window.wsClient) {
        console.warn('WebSocket client non disponible');
        return;
    }
    
    // Écouter les notifications
    window.wsClient.on('notification', (data) => {
        console.log('Notification WebSocket reçue:', data);
        
        // Mettre à jour le badge
        if (data.count !== undefined) {
            updateNotificationBadge(data.count);
        }
        
        // Afficher notification navigateur
        if (data.type === 'message') {
            showBrowserNotification(data.count || 1, {
                expediteur_nom: data.senderName,
                conversation_id: data.convId
            });
        }
        
        // Jouer un son si activé
        const shouldPlaySound = localStorage.getItem('notification_sound') !== 'false';
        if (shouldPlaySound) {
            playNotificationSound();
        }
    });
    
    // ⚠️ SUPPRIMER l'ancien polling setInterval
    // Plus besoin de checkNotifications() toutes les 30s
}

/**
 * Initialise les notifications
 */
function initNotifications() {
    // Vérifier immédiatement au chargement de la page
    checkNotifications();
}

/**
 * Vérifie les nouvelles notifications
 */
function checkNotifications() {
    fetch('api/notifications.php?action=check')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateNotificationBadge(data.count);
                
                // Si l'utilisateur a activé les notifications du navigateur et qu'il y a de nouvelles notifications
                if (data.count > 0 && hasNotificationPermission() && data.latest_notification) {
                    showBrowserNotification(data.count, data.latest_notification);
                }
            }
        })
        .catch(error => console.error('Erreur lors de la vérification des notifications:', error));
}

/**
 * Met à jour le badge de notification
 * @param {number} count - Nombre de notifications non lues
 */
function updateNotificationBadge(count) {
    let badge = document.querySelector('.notification-badge');
    
    if (count > 0) {
        // Créer un badge s'il n'existe pas
        if (!badge) {
            const userInfo = document.querySelector('.user-info');
            if (userInfo) {
                const newBadge = document.createElement('span');
                newBadge.className = 'notification-badge';
                newBadge.textContent = count;
                userInfo.appendChild(newBadge);
            }
        } else {
            // Mettre à jour le badge existant
            badge.textContent = count;
            badge.style.display = 'flex';
        }
    } else if (badge) {
        // Masquer le badge s'il n'y a pas de notification
        badge.style.display = 'none';
    }
}

/**
 * Initialise les clics sur les notifications
 */
function initNotificationClicks() {
    // Délègue les événements pour gérer les notifications ajoutées dynamiquement
    document.addEventListener('click', function(e) {
        // Vérifier si le clic était sur une notification
        if (e.target.closest('.notification-item')) {
            const notificationItem = e.target.closest('.notification-item');
            const notificationId = notificationItem.dataset.id;
            const conversationId = notificationItem.dataset.conversationId;
            
            // Marquer comme lu
            markNotificationRead(notificationId);
            
            // Rediriger vers la conversation
            window.location.href = `conversation.php?id=${conversationId}`;
        }
    });
}

/**
 * Marque une notification comme lue
 * @param {number} notificationId - ID de la notification
 */
function markNotificationRead(notificationId) {
    fetch(`api/notifications.php?action=mark_read&id=${notificationId}`)
        .catch(error => console.error('Erreur lors du marquage de la notification:', error));
}

/**
 * Vérifie si les notifications du navigateur sont autorisées
 * @returns {boolean} True si les notifications sont autorisées
 */
function hasNotificationPermission() {
    return window.Notification && Notification.permission === 'granted';
}

/**
 * Demande l'autorisation pour les notifications du navigateur
 */
function requestNotificationPermission() {
    // Vérifier si le navigateur supporte les notifications
    if (window.Notification && Notification.permission === 'default') {
        // Créer un bouton pour demander la permission
        const container = document.querySelector('main');
        if (container) {
            const notificationPrompt = document.createElement('div');
            notificationPrompt.className = 'notification-prompt alert info';
            notificationPrompt.innerHTML = `
                <p>
                    <i class="fas fa-bell"></i> 
                    Voulez-vous recevoir des notifications pour les nouveaux messages ?
                </p>
                <button id="enable-notifications" class="btn primary">
                    <i class="fas fa-check"></i> Activer les notifications
                </button>
                <button id="dismiss-notification-prompt" class="btn cancel">
                    <i class="fas fa-times"></i> Non merci
                </button>
            `;
            
            // Insérer au début du main
            container.insertBefore(notificationPrompt, container.firstChild);
            
            // Gestionnaires d'événements
            document.getElementById('enable-notifications').addEventListener('click', function() {
                Notification.requestPermission().then(function(permission) {
                    notificationPrompt.remove();
                    
                    // Si l'utilisateur accepte, mettre à jour la préférence dans la base de données
                    if (permission === 'granted') {
                        updateNotificationPreference('browser_notifications', true);
                    }
                });
            });
            
            document.getElementById('dismiss-notification-prompt').addEventListener('click', function() {
                notificationPrompt.remove();
                
                // Stocker que l'utilisateur a refusé pour ne plus lui demander
                localStorage.setItem('notification_prompt_dismissed', 'true');
            });
        }
    }
}

/**
 * Met à jour une préférence de notification
 * @param {string} preference - Nom de la préférence
 * @param {boolean} value - Valeur de la préférence
 */
function updateNotificationPreference(preference, value) {
    const formData = new FormData();
    formData.append('action', 'update_preferences');
    formData.append('preferences[' + preference + ']', value ? '1' : '0');
    
    fetch('api/notifications.php', {
        method: 'POST',
        body: formData
    }).catch(error => console.error('Erreur lors de la mise à jour de la préférence:', error));
}

/**
 * Affiche une notification dans le navigateur
 * @param {number} count - Nombre de notifications
 * @param {Object} latestNotification - Dernière notification
 */
function showBrowserNotification(count, latestNotification) {
    if (!hasNotificationPermission()) {
        return;
    }
    
    // Vérifier les préférences utilisateur stockées en local
    const shouldPlaySound = localStorage.getItem('notification_sound') !== 'false';
    
    // Créer la notification
    const title = "Pronote - Messagerie";
    let expediteurNom = "Expéditeur";
    
    // Récupérer l'expéditeur
    if (latestNotification && latestNotification.expediteur_nom) {
        expediteurNom = latestNotification.expediteur_nom;
    }
    
    const options = {
        body: count === 1 
            ? `Nouveau message de ${expediteurNom}`
            : `${count} nouveaux messages non lus`,
            // icon: '/assets/images/pronote-icon.png'
    };
    
    try {
        const notification = new Notification(title, options);
        
        // Jouer un son si activé
        if (shouldPlaySound) {
            playNotificationSound();
        }
        
        // Rediriger vers la conversation au clic sur la notification
        notification.onclick = function() {
            window.focus();
            if (latestNotification && latestNotification.conversation_id) {
                window.location.href = `conversation.php?id=${latestNotification.conversation_id}`;
            } else {
                window.location.href = 'index.php';
            }
        };
    } catch (e) {
        console.error('Erreur lors de la création de la notification:', e);
    }
}

/**
 * Joue un son de notification
 */
function playNotificationSound() {
    try {
        // Utiliser l'API Web Audio pour créer un son simple
        const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        const oscillator = audioCtx.createOscillator();
        const gainNode = audioCtx.createGain();
        
        oscillator.type = 'sine';
        oscillator.frequency.setValueAtTime(880, audioCtx.currentTime); // La note "La"
        gainNode.gain.setValueAtTime(0.1, audioCtx.currentTime); // Volume bas
        
        oscillator.connect(gainNode);
        gainNode.connect(audioCtx.destination);
        
        // Jouer un bref son
        oscillator.start();
        
        // Arrêter après 200ms
        setTimeout(() => {
            oscillator.stop();
        }, 200);
    } catch (e) {
        console.log("Son de notification non supporté:", e);
    }
}