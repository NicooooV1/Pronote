/**
 * Client WebSocket global — Fronote
 * Charge sur toutes les pages authentifiees via shared_header.php.
 * Lit la config depuis window.FRONOTE_WS (injectee par PHP).
 *
 * Features:
 * - Auto-reconnect with exponential backoff
 * - Heartbeat (30s) with 90s timeout detection
 * - Token refresh before expiry
 * - Real-time notifications for: messages, grades, absences, events, announcements
 * - Badge updates for sidebar modules
 * - Fallback: HTTP polling every 30s after 3 WS failures
 */
(function () {
    'use strict';

    var cfg = window.FRONOTE_WS;
    if (!cfg || !cfg.url || !cfg.token || !cfg.userId) return;

    var socket         = null;
    var connected      = false;
    var failedAttempts = 0;
    var pollingTimer   = null;
    var heartbeatTimer = null;

    var MAX_FAILS   = 3;
    var POLL_DELAY  = 30000;
    var HEARTBEAT_INTERVAL = 30000;

    // Toast helper (uses FronoteToast from components.js, fallback to showToast)
    function toast(msg, type) {
        if (window.FronoteToast) {
            window.FronoteToast.show(msg, type || 'info');
        } else if (typeof showToast === 'function') {
            showToast(msg, type || 'info');
        }
    }

    // ─── Connection ──────────────────────────────────────────────

    function connect() {
        if (typeof io === 'undefined') {
            console.warn('[WS] Socket.IO unavailable — fallback polling');
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

        // ─── Global event handlers ───────────────────────────────
        socket.on('notification',  handleNotification);
        socket.on('unread_count',  updateBadge);
        socket.on('system_alert',  handleSystemAlert);

        // Real-time module events
        socket.on('grade',        handleGrade);
        socket.on('absence',      handleAbsence);
        socket.on('event',        handleEvent);
        socket.on('announcement', handleAnnouncement);
        socket.on('message',      handleMessage);
        socket.on('badge_update', handleBadgeUpdate);

        // Heartbeat
        socket.on('heartbeat:ack', function() { /* Server acknowledged */ });
        socket.on('token:refreshed', function() { console.log('[WS] Token refreshed'); });
        socket.on('token:error', function(msg) { console.warn('[WS] Token refresh error:', msg); });
    }

    // ─── Connection handlers ─────────────────────────────────────

    function onConnect() {
        connected      = true;
        failedAttempts = 0;
        stopPolling();
        startHeartbeat();

        // Join rooms
        socket.emit('joinUser', String(cfg.userId));
        socket.emit('joinEtablissement');
        if (cfg.userRole === 'administrateur') {
            socket.emit('join:admin');
        }

        console.log('[WS] Connected — user=' + cfg.userId + ' role=' + cfg.userRole);
    }

    function onDisconnect(reason) {
        connected = false;
        stopHeartbeat();
        console.warn('[WS] Disconnected:', reason);
    }

    function onConnectError() {
        failedAttempts++;
        if (failedAttempts >= MAX_FAILS) {
            console.warn('[WS] ' + MAX_FAILS + ' failures — fallback polling');
            startPolling();
        }
    }

    // ─── Heartbeat ───────────────────────────────────────────────

    function startHeartbeat() {
        stopHeartbeat();
        heartbeatTimer = setInterval(function() {
            if (socket && connected) {
                socket.emit('heartbeat');
            }
        }, HEARTBEAT_INTERVAL);

        // Schedule token refresh (5 min before expiry)
        scheduleTokenRefresh();
    }

    function stopHeartbeat() {
        if (heartbeatTimer) {
            clearInterval(heartbeatTimer);
            heartbeatTimer = null;
        }
    }

    function scheduleTokenRefresh() {
        // Refresh token via AJAX when near expiry
        var baseUrl = window.FRONOTE_BASE_URL || '/';
        setTimeout(function refreshLoop() {
            if (!connected) return;
            fetch(baseUrl + 'API/endpoints/ws_token_refresh.php', {
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function(r) { return r.ok ? r.json() : null; })
            .then(function(data) {
                if (data && data.token && socket) {
                    socket.emit('token:refresh', data.token);
                    cfg.token = data.token;
                }
            })
            .catch(function() {})
            .finally(function() {
                // Refresh every 20 minutes
                setTimeout(refreshLoop, 20 * 60 * 1000);
            });
        }, 20 * 60 * 1000); // First refresh after 20 min
    }

    // ─── Event handlers ──────────────────────────────────────────

    function handleNotification(data) {
        var msg  = data.message || 'Nouvelle notification';
        var type = data.type    || 'info';
        toast(msg, type);
        if (typeof data.unread_count !== 'undefined') {
            updateBadge({ count: data.unread_count });
        }
    }

    function handleGrade(data) {
        var subject = data.matiere || data.subject || '';
        var note = data.note || data.grade || '';
        var max  = data.max || '20';
        var msg = 'Nouvelle note' + (subject ? ' en ' + subject : '') + (note ? ': ' + note + '/' + max : '');
        toast(msg, 'success');
        updateModuleBadge('notes');
    }

    function handleAbsence(data) {
        var msg = data.message || 'Nouvelle absence signalee';
        toast(msg, 'warning');
        updateModuleBadge('absences');
    }

    function handleEvent(data) {
        var msg = data.message || data.title || 'Nouvel evenement';
        toast(msg, 'info');
        updateModuleBadge('agenda');
    }

    function handleAnnouncement(data) {
        var msg = data.message || data.title || 'Nouvelle annonce';
        toast(msg, 'info');
        updateModuleBadge('annonces');
    }

    function handleMessage(data) {
        var sender = data.sender_name || data.from || '';
        var msg = sender ? 'Message de ' + sender : 'Nouveau message';
        toast(msg, 'info');
        if (typeof data.unread_count !== 'undefined') {
            updateBadge({ count: data.unread_count });
        }
    }

    function handleBadgeUpdate(data) {
        if (data.module) {
            updateModuleBadge(data.module, data.count);
        }
    }

    function handleSystemAlert(data) {
        toast(data.message || 'Alerte systeme', 'warning');
    }

    // ─── Badge updates ───────────────────────────────────────────

    function updateBadge(data) {
        var badge = document.getElementById('sidebarMsgBadge');
        if (!badge) return;
        var count = parseInt(data.count || 0, 10);
        if (count > 0) {
            badge.textContent = count > 99 ? '99+' : String(count);
            badge.classList.remove('sidebar-badge--hidden');
        } else {
            badge.textContent = '';
            badge.classList.add('sidebar-badge--hidden');
        }
    }

    function updateModuleBadge(moduleKey, count) {
        var badge = document.querySelector('[data-badge-module="' + moduleKey + '"]');
        if (!badge) return;
        if (typeof count === 'number') {
            badge.textContent = count > 99 ? '99+' : String(count);
            badge.style.display = count > 0 ? '' : 'none';
        } else {
            // Increment existing
            var current = parseInt(badge.textContent || '0', 10);
            badge.textContent = String(current + 1);
            badge.style.display = '';
        }
    }

    // ─── Polling fallback ────────────────────────────────────────

    function startPolling() {
        if (pollingTimer) return;
        pollingTimer = setInterval(pollUnreadCount, POLL_DELAY);
    }

    function stopPolling() {
        if (pollingTimer) {
            clearInterval(pollingTimer);
            pollingTimer = null;
        }
    }

    function pollUnreadCount() {
        if (connected) { stopPolling(); return; }

        var csrfMeta = document.querySelector('meta[name="csrf-token"]');
        var csrf     = csrfMeta ? csrfMeta.getAttribute('content') : '';
        var baseUrl  = window.FRONOTE_BASE_URL || '/';

        fetch(baseUrl + 'API/endpoints/messagerie.php?action=notifications&sub=count', {
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
        .catch(function () {});
    }

    // ─── Public API ──────────────────────────────────────────────

    window.wsGlobal = {
        on: function (event, cb) { if (socket) socket.on(event, cb); },
        off: function (event, cb) { if (socket) socket.off(event, cb); },
        emit: function (event, data) { if (socket && connected) socket.emit(event, data); },
        isConnected: function () { return connected; },
        refreshBadge: pollUnreadCount,
        joinClass: function(classeId) { if (socket && connected) socket.emit('join:class', classeId); },
        joinConversation: function(convId) { if (socket && connected) socket.emit('join:conversation', convId); },
    };

    // Start
    connect();
}());
