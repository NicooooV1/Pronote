/**
 * Push Manager — Gestion des notifications push côté client.
 * Inscription/désinscription Web Push via VAPID.
 */
(function() {
    'use strict';

    var PUSH_API_URL = (window.FRONOTE_BASE_URL || '') + 'API/endpoints/push_subscribe.php';

    window.FronotePush = {
        /**
         * Vérifie si les push sont supportées et activées.
         */
        isSupported: function() {
            return 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
        },

        /**
         * Demande la permission et inscrit l'utilisateur.
         */
        subscribe: function(vapidPublicKey) {
            if (!this.isSupported()) {
                console.warn('Push notifications non supportées.');
                return Promise.reject(new Error('Not supported'));
            }

            return Notification.requestPermission().then(function(permission) {
                if (permission !== 'granted') {
                    return Promise.reject(new Error('Permission denied'));
                }

                return navigator.serviceWorker.ready.then(function(reg) {
                    return reg.pushManager.subscribe({
                        userVisibleOnly: true,
                        applicationServerKey: urlBase64ToUint8Array(vapidPublicKey)
                    });
                }).then(function(subscription) {
                    return sendSubscriptionToServer(subscription, 'subscribe');
                });
            });
        },

        /**
         * Désinscrit l'utilisateur.
         */
        unsubscribe: function() {
            return navigator.serviceWorker.ready.then(function(reg) {
                return reg.pushManager.getSubscription();
            }).then(function(subscription) {
                if (subscription) {
                    return sendSubscriptionToServer(subscription, 'unsubscribe')
                        .then(function() { return subscription.unsubscribe(); });
                }
            });
        },

        /**
         * Vérifie si l'utilisateur est inscrit.
         */
        isSubscribed: function() {
            return navigator.serviceWorker.ready.then(function(reg) {
                return reg.pushManager.getSubscription();
            }).then(function(sub) {
                return !!sub;
            });
        }
    };

    function sendSubscriptionToServer(subscription, action) {
        var key = subscription.getKey('p256dh');
        var auth = subscription.getKey('auth');
        var csrfToken = document.querySelector('meta[name=csrf-token]');

        return fetch(PUSH_API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: action,
                endpoint: subscription.endpoint,
                p256dh: key ? btoa(String.fromCharCode.apply(null, new Uint8Array(key))) : '',
                auth: auth ? btoa(String.fromCharCode.apply(null, new Uint8Array(auth))) : '',
                csrf_token: csrfToken ? csrfToken.content : ''
            })
        });
    }

    function urlBase64ToUint8Array(base64String) {
        var padding = '='.repeat((4 - base64String.length % 4) % 4);
        var base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
        var rawData = atob(base64);
        var outputArray = new Uint8Array(rawData.length);
        for (var i = 0; i < rawData.length; ++i) {
            outputArray[i] = rawData.charCodeAt(i);
        }
        return outputArray;
    }
})();
