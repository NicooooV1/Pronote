/**
 * Client WebSocket global — Fronote
 * Chargé sur toutes les pages authentifiées via shared_header.php.
 * Lit la config depuis window.FRONOTE_WS (injectée par PHP).
 *
 * Canaux : user_{id}, etablissement, admin (si administrateur)
 * Fallback : polling HTTP toutes les 30s après 3 échecs WS
 */
(function () {
    'use strict';

    var cfg = window.FRONOTE_WS;
    if (!cfg || !cfg.url || !cfg.token || !cfg.userId) return;

    var socket         = null;
    var connected      = false;
    var failedAttempts = 0;
    var pollingTimer   = null;

    var MAX_FAILS   = 3;
    var POLL_DELAY  = 30000; // 30 secondes

    // ─── Connexion ────────────────────────────────────────────────

    function connect() {
        if (typeof io === 'undefined') {
            console.warn('[WS] Socket.IO non disponible — fallback polling activé');
            startPolling();
            return;
        }

        socket = io(cfg.url, {
            auth: { token: cfg.token },
            transports: ['websocket', 'polling'],
            reconnection: true,
            reconnectionDelay: 1000,
            reconnectionDelayMax: 30000,
            randomizationFactor: 0.5,
        });

        socket.on('connect', onConnect);
        socket.on('disconnect', onDisconnect);
        socket.on('connect_error', onConnectError);

        // Événements globaux
        socket.on('notification',  handleNotification);
        socket.on('unread_count',  updateBadge);
        socket.on('system_alert',  handleSystemAlert);
    }

    // ─── Handlers connexion ───────────────────────────────────────

    function onConnect() {
        connected      = true;
        failedAttempts = 0;
        stopPolling();

        // Rejoindre les canaux
        socket.emit('joinUser', String(cfg.userId));
        socket.emit('joinEtablissement');
        if (cfg.userRole === 'administrateur') {
            socket.emit('joinAdmin');
        }

        console.log('[WS] Connecté — user=' + cfg.userId + ' role=' + cfg.userRole);
    }

    function onDisconnect(reason) {
        connected = false;
        console.warn('[WS] Déconnecté :', reason);
    }

    function onConnectError() {
        failedAttempts++;
        if (failedAttempts >= MAX_FAILS) {
            console.warn('[WS] ' + MAX_FAILS + ' échecs — fallback polling activé');
            startPolling();
        }
    }

    // ─── Handlers événements ──────────────────────────────────────

    function handleNotification(data) {
        var msg  = data.message || 'Nouvelle notification';
        var type = data.type    || 'info';

        // Toast via pronote-theme.js (disponible sur toutes les pages)
        if (typeof showToast === 'function') {
            showToast(msg, type);
        }

        if (typeof data.unread_count !== 'undefined') {
            updateBadge({ count: data.unread_count });
        }
    }

    function updateBadge(data) {
        var badge = document.getElementById('sidebarMsgBadge');
        if (!badge) return;

        var count = parseInt(data.count || 0, 10);
        if (count > 0) {
            badge.textContent    = count > 99 ? '99+' : String(count);
            badge.style.display  = 'inline-flex';
        } else {
            badge.textContent    = '';
            badge.style.display  = 'none';
        }
    }

    function handleSystemAlert(data) {
        if (typeof showToast === 'function') {
            showToast(data.message || 'Alerte système', 'warning');
        }
    }

    // ─── Polling fallback ─────────────────────────────────────────

    function startPolling() {
        if (pollingTimer) return; // guard anti-doublon
        pollingTimer = setInterval(pollUnreadCount, POLL_DELAY);
    }

    function stopPolling() {
        if (pollingTimer) {
            clearInterval(pollingTimer);
            pollingTimer = null;
        }
    }

    function pollUnreadCount() {
        // Si WS s'est reconnecté entre-temps, on arrête
        if (connected) { stopPolling(); return; }

        var csrfMeta = document.querySelector('meta[name="csrf-token"]');
        var csrf     = csrfMeta ? csrfMeta.getAttribute('content') : '';

        // Chemin relatif à la racine — fonctionne car les pages sont à profondeur variable
        // On remonte depuis l'URL courante jusqu'à la racine du projet
        var depth   = (window.location.pathname.match(/\//g) || []).length - 1;
        var prefix  = depth > 1 ? Array(depth).join('../') : '';
        var endpoint = prefix + 'API/endpoints/messagerie.php?action=notifications&sub=count';

        fetch(endpoint, {
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-Token': csrf,
            }
        })
        .then(function (r) { return r.ok ? r.json() : null; })
        .then(function (data) {
            if (data && typeof data.count !== 'undefined') {
                updateBadge(data);
            }
        })
        .catch(function () { /* silencieux */ });
    }

    // ─── API publique ─────────────────────────────────────────────

    window.wsGlobal = {
        /** Écouter un événement Socket.IO */
        on: function (event, cb) {
            if (socket) socket.on(event, cb);
        },
        /** Se désabonner d'un événement */
        off: function (event, cb) {
            if (socket) socket.off(event, cb);
        },
        /** État de la connexion */
        isConnected: function () { return connected; },
        /** Forcer un refresh du badge (utile après envoi d'un message) */
        refreshBadge: pollUnreadCount,
    };

    // Démarrer
    connect();

}());
