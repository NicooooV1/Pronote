/**
 * /assets/js/shared.js — Utilitaires partagés (Messagerie Fronote)
 * Fonctions communes utilisées par inbox.js, conversation.js, notifications.js, forms.js
 */

// ═══════════════════════════════════════════════════
// SÉCURITÉ & RÉSEAU
// ═══════════════════════════════════════════════════

/**
 * Récupère le token CSRF depuis la balise meta
 * @returns {string}
 */
function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content || window.csrfToken || '';
}

/**
 * Construit l'URL de base de l'API centralisée (API/endpoints/)
 * Depuis les pages messagerie/ → remonte d'un niveau vers la racine
 * @returns {string}
 */
function getApiBase() {
    return window.location.pathname.split('/').slice(0, -2).join('/') + '/API/endpoints';
}

/**
 * Options fetch par défaut avec credentials et CSRF
 * @param {string} [method='GET'] — Méthode HTTP
 * @returns {Object}
 */
function getFetchOptions(method = 'GET') {
    const opts = {
        method,
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    };
    if (method !== 'GET') {
        opts.headers['X-CSRF-Token'] = getCsrfToken();
    }
    return opts;
}

/**
 * Fetch JSON avec CSRF, abort-signal et gestion d'erreurs
 * @param {string} url
 * @param {Object} [options={}]
 * @returns {Promise<Object>}
 */
function apiFetch(url, options = {}) {
    const defaults = getFetchOptions(options.method || 'GET');
    const merged = {
        ...defaults,
        ...options,
        headers: { ...defaults.headers, ...(options.headers || {}) }
    };

    if (window.activeConnections?.abortController) {
        merged.signal = window.activeConnections.abortController.signal;
    }

    return fetch(url, merged).then(r => {
        if (!r.ok) throw new Error(`Erreur réseau: ${r.status}`);
        return r.json();
    });
}

// ═══════════════════════════════════════════════════
// ÉCHAPPEMENT & FORMATAGE
// ═══════════════════════════════════════════════════

/**
 * Échappe les caractères HTML
 * @param {string} text
 * @returns {string}
 */
function escapeHTML(text) {
    if (!text) return '';
    return text
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

/**
 * Convertit les retours à la ligne en <br>
 * @param {string} text
 * @returns {string}
 */
function nl2br(text) {
    if (!text) return '';
    return text.replace(/\n/g, '<br>');
}

/**
 * Libellé lisible du type de participant
 * @param {string} type
 * @returns {string}
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
 * Formate une date de message en relatif / absolu
 * @param {Date} date
 * @returns {string}
 */
function formatMessageDate(date) {
    const now = new Date();
    const diffMs = now - date;
    const diffSecs = Math.floor(diffMs / 1000);
    const diffMins = Math.floor(diffSecs / 60);
    const diffHours = Math.floor(diffMins / 60);
    const diffDays = Math.floor(diffHours / 24);

    if (diffSecs < 60) return "À l'instant";
    if (diffMins < 60) return `Il y a ${diffMins} minute${diffMins > 1 ? 's' : ''}`;
    if (diffHours < 24) return `Il y a ${diffHours} heure${diffHours > 1 ? 's' : ''}`;
    if (diffDays < 2) {
        return `Hier à ${date.getHours().toString().padStart(2, '0')}:${date.getMinutes().toString().padStart(2, '0')}`;
    }
    return `${date.getDate().toString().padStart(2, '0')}/${(date.getMonth() + 1).toString().padStart(2, '0')}/${date.getFullYear()} à ${date.getHours().toString().padStart(2, '0')}:${date.getMinutes().toString().padStart(2, '0')}`;
}

/**
 * Formate une taille de fichier en unités lisibles
 * @param {number} bytes
 * @returns {string}
 */
function formatFileSize(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1048576) return Math.round(bytes / 1024) + ' KB';
    return Math.round(bytes / 1048576 * 10) / 10 + ' MB';
}

// ═══════════════════════════════════════════════════
// NOTIFICATIONS / TOASTS
// ═══════════════════════════════════════════════════

/**
 * Notification d'erreur centrée à l'écran (XSS-safe)
 * @param {string} message
 * @param {number} [duration=5000]
 * @returns {HTMLElement}
 */
function afficherNotificationErreur(message, duration = 5000) {
    let container = document.getElementById('error-notification-container');

    if (!container) {
        container = document.createElement('div');
        container.id = 'error-notification-container';
        Object.assign(container.style, {
            position: 'fixed', top: '50%', left: '50%',
            transform: 'translate(-50%, -50%)', zIndex: '10000',
            width: 'auto', maxWidth: '80%'
        });
        document.body.appendChild(container);
    }

    const notif = document.createElement('div');
    notif.className = 'error-notification';
    Object.assign(notif.style, {
        backgroundColor: '#f8d7da', color: '#721c24',
        padding: '15px 20px', margin: '10px', borderRadius: '5px',
        boxShadow: '0 4px 10px rgba(0,0,0,.2)',
        display: 'flex', justifyContent: 'space-between', alignItems: 'center',
        minWidth: '300px'
    });

    const content = document.createElement('div');
    const icon = document.createElement('i');
    icon.className = 'fas fa-exclamation-circle';
    content.appendChild(icon);
    content.appendChild(document.createTextNode(' ' + message));

    const closeBtn = document.createElement('button');
    closeBtn.innerHTML = '&times;';
    Object.assign(closeBtn.style, {
        background: 'none', border: 'none', color: '#721c24',
        fontSize: '20px', cursor: 'pointer', marginLeft: '15px'
    });

    notif.appendChild(content);
    notif.appendChild(closeBtn);
    container.appendChild(notif);

    function removeNotif() {
        if (notif.parentNode === container) {
            container.removeChild(notif);
            if (container.children.length === 0 && container.parentNode) {
                container.parentNode.removeChild(container);
            }
        }
    }
    closeBtn.addEventListener('click', removeNotif);
    setTimeout(removeNotif, duration);

    return notif;
}

/**
 * Notification toast (succès / info)
 * @param {string} message
 * @param {string} [type='info']
 * @param {number} [duration=3000]
 * @returns {HTMLElement}
 */
function afficherNotification(message, type = 'info', duration = 3000) {
    const notif = document.createElement('div');
    notif.className = `notification-toast ${type}`;
    notif.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : 'info-circle'}"></i>
        <span>${escapeHTML(message)}</span>
        <button class="notif-close">&times;</button>
    `;
    document.body.appendChild(notif);

    notif.querySelector('.notif-close').addEventListener('click', () => notif.remove());
    setTimeout(() => { if (notif.parentNode) notif.remove(); }, duration);
    return notif;
}

// ═══════════════════════════════════════════════════
// MODALS
// ═══════════════════════════════════════════════════

/**
 * Ouvre le modal d'ajout de participants
 */
function showAddParticipantModal() {
    const modal = document.getElementById('addParticipantModal');
    if (modal) modal.style.display = 'block';
}

/**
 * Ferme un modal donné
 * @param {HTMLElement} modal
 */
function closeModal(modal) {
    if (modal) modal.style.display = 'none';
}

// ═══════════════════════════════════════════════════
// ACTIONS DE CONVERSATION (archive, supprimer, restaurer)
// Utilisées à la fois sur l'inbox et la conversation
// ═══════════════════════════════════════════════════

/**
 * Attache les listeners communs : boutons archive/delete/restore,
 * modal participants, fermeture modals, menus rapides.
 */
function setupConversationActions() {
    // Archiver
    const archiveBtn = document.getElementById('archive-btn');
    if (archiveBtn) {
        archiveBtn.addEventListener('click', function (e) {
            e.preventDefault();
            if (confirm('Êtes-vous sûr de vouloir archiver cette conversation ?')) {
                document.getElementById('archiveForm').submit();
            }
        });
    }

    // Supprimer
    const deleteBtn = document.getElementById('delete-btn');
    if (deleteBtn) {
        deleteBtn.addEventListener('click', function (e) {
            e.preventDefault();
            if (confirm('Êtes-vous sûr de vouloir supprimer cette conversation ?')) {
                document.getElementById('deleteForm').submit();
            }
        });
    }

    // Restaurer
    const restoreBtn = document.getElementById('restore-btn');
    if (restoreBtn) {
        restoreBtn.addEventListener('click', function (e) {
            e.preventDefault();
            document.getElementById('restoreForm').submit();
        });
    }

    // Bouton ajout participant
    const addParticipantBtn = document.getElementById('add-participant-btn');
    if (addParticipantBtn) {
        addParticipantBtn.addEventListener('click', function (e) {
            e.preventDefault();
            showAddParticipantModal();
        });
    }

    // Fermeture modals (boutons .close)
    document.querySelectorAll('.close').forEach(btn => {
        btn.addEventListener('click', function () {
            closeModal(this.closest('.modal'));
        });
    });

    // Fermeture modal au clic en dehors
    window.addEventListener('click', function (event) {
        document.querySelectorAll('.modal').forEach(modal => {
            if (event.target === modal) closeModal(modal);
        });
    });

    // Fermeture menus rapides au clic en dehors
    document.addEventListener('click', function (event) {
        if (!event.target.closest('.quick-actions')) {
            document.querySelectorAll('.quick-actions-menu').forEach(menu => {
                menu.classList.remove('active');
            });
        }
    });
}
