/**
 * Session Checker — Version refactorisée.
 * URLs relatives, pas de code mort, un seul intervalle de 30 s.
 */
(function () {
    'use strict';

    var CHECK_INTERVAL = 30000; // 30 secondes
    var CHECK_URL = 'check_session.php';
    var LOGIN_URL = 'index.php';

    function checkSession() {
        fetch(CHECK_URL, {
            method: 'GET',
            credentials: 'same-origin',
            headers: { 'Cache-Control': 'no-cache' }
        })
        .then(function (resp) { return resp.json(); })
        .then(function (data) {
            if (!data.valid) {
                window.location.href = LOGIN_URL;
            }
        })
        .catch(function () {
            // Silencieux — on ne déconnecte pas sur une simple erreur réseau.
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        setInterval(checkSession, CHECK_INTERVAL);
    });
})();
