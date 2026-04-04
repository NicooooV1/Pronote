/**
 * Service Worker — Fronote PWA
 * Cache shell statique + stratégie network-first pour les pages dynamiques.
 * Push notifications handler.
 */
const CACHE_NAME = 'fronote-shell-v1';
const SHELL_ASSETS = [
    'assets/css/base.css',
    'assets/css/tokens.css',
    'assets/css/theme-classic.css',
    'assets/js/ws-global.js',
    'assets/icons/icon-192.png',
];

// ─── Install : pré-cache du shell ────────────────────────────────
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => cache.addAll(SHELL_ASSETS))
            .then(() => self.skipWaiting())
    );
});

// ─── Activate : nettoyage des anciens caches ─────────────────────
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => {
            return Promise.all(
                keys.filter((k) => k !== CACHE_NAME)
                    .map((k) => caches.delete(k))
            );
        }).then(() => self.clients.claim())
    );
});

// ─── Fetch : network-first avec fallback cache ─────���─────────────
self.addEventListener('fetch', (event) => {
    const url = new URL(event.request.url);

    // Ne pas intercepter les requêtes non-GET ou cross-origin
    if (event.request.method !== 'GET' || url.origin !== self.location.origin) {
        return;
    }

    // Assets statiques : cache-first
    if (url.pathname.match(/\.(css|js|png|jpg|webp|woff2?|svg|ico)$/)) {
        event.respondWith(
            caches.match(event.request).then((cached) => {
                return cached || fetch(event.request).then((response) => {
                    if (response.ok) {
                        const clone = response.clone();
                        caches.open(CACHE_NAME).then((cache) => cache.put(event.request, clone));
                    }
                    return response;
                });
            })
        );
        return;
    }

    // Pages PHP : network-first
    event.respondWith(
        fetch(event.request)
            .then((response) => response)
            .catch(() => {
                return caches.match(event.request).then((cached) => {
                    return cached || new Response(
                        '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Fronote - Hors ligne</title>' +
                        '<style>body{font-family:system-ui;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;background:#f7fafc;color:#4a5568;text-align:center}' +
                        '.box{padding:40px}.icon{font-size:4em;margin-bottom:20px}h1{font-size:1.4em}p{color:#a0aec0}</style></head>' +
                        '<body><div class="box"><div class="icon">📡</div><h1>Hors ligne</h1><p>Reconnectez-vous à internet pour accéder à Fronote.</p>' +
                        '<button onclick="location.reload()" style="margin-top:20px;padding:10px 24px;background:#667eea;color:#fff;border:none;border-radius:8px;cursor:pointer;font-size:1em">Réessayer</button></div></body></html>',
                        { headers: { 'Content-Type': 'text/html; charset=utf-8' } }
                    );
                });
            })
    );
});

// ─── Push Notifications ──────────────────────────────────────────
self.addEventListener('push', (event) => {
    let data = { title: 'Fronote', body: 'Nouvelle notification', icon: 'assets/icons/icon-192.png' };

    if (event.data) {
        try {
            data = Object.assign(data, event.data.json());
        } catch (e) {
            data.body = event.data.text();
        }
    }

    event.waitUntil(
        self.registration.showNotification(data.title, {
            body: data.body,
            icon: data.icon || 'assets/icons/icon-192.png',
            badge: 'assets/icons/icon-192.png',
            tag: data.tag || 'fronote-default',
            data: { url: data.url || './' },
            vibrate: [200, 100, 200],
        })
    );
});

// ─── Notification click : ouvrir l'URL ──────────────────────────
self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const targetUrl = event.notification.data?.url || './';

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true })
            .then((clients) => {
                // Si un onglet Fronote est déjà ouvert, le focus
                for (const client of clients) {
                    if (client.url.includes(self.location.origin) && 'focus' in client) {
                        client.navigate(targetUrl);
                        return client.focus();
                    }
                }
                return self.clients.openWindow(targetUrl);
            })
    );
});
