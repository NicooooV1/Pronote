<?php
/**
 * En-tête HTML commun - Messagerie
 * Utilise les templates partagés Fronote
 */

// Inclure le modèle de notification
require_once __DIR__ . '/../models/notification.php';
require_once __DIR__ . '/../core/csrf.php';

// Titre par défaut
$pageTitle = $pageTitle ?? 'Messagerie';

// Obtenir la page courante pour activer le menu correspondant
$currentPage = basename($_SERVER['PHP_SELF'], '.php');

// Récupérer le dossier courant pour les menus
$currentFolder = isset($_GET['folder']) ? $_GET['folder'] : 'reception';

// Vérifier si l'utilisateur est défini et s'assurer que son type est défini
if (isset($user)) {
    if (!isset($user['type']) && isset($user['profil'])) {
        $user['type'] = $user['profil'];
    } elseif (!isset($user['type'])) {
        $user['type'] = 'eleve';
    }
    $user_initials = strtoupper(substr($user['prenom'], 0, 1) . substr($user['nom'], 0, 1));
    $user_fullname = ($user['prenom'] ?? '') . ' ' . ($user['nom'] ?? '');
    $unreadNotifications = countUnreadNotifications($user['id'], $user['type']);
} else {
    $unreadNotifications = 0;
    $user_initials = '';
    $user_fullname = '';
}

// Générer le token WebSocket pour l'utilisateur
if (isset($user)) {
    require_once __DIR__ . '/../../API/Core/WebSocket.php';
    $wsToken = \API\Core\WebSocket::generateToken($user['id'], $user['type']);
    $wsUrl = getenv('WEBSOCKET_CLIENT_URL') ?: 'http://localhost:3000';
}

// Variables pour les templates partagés
$activePage = 'messagerie';
$isAdmin = isset($user) && ($user['type'] ?? '') === 'administrateur';
$rootPrefix = '../';

// CSS supplémentaires spécifiques à la messagerie (unified styles.css)
$extraCss = [
    'assets/css/styles.css',
    'assets/css/sidebar.css',
];
if (in_array($currentPage, ['conversation'])) {
    $extraCss[] = 'assets/css/conversation.css';
}

// Head HTML supplémentaire (CSRF, WebSocket, Dark mode)
ob_start();
?>
    <?= csrf_meta() ?>
    <!-- Socket.IO client -->
    <script src="https://cdn.socket.io/4.6.1/socket.io.min.js"></script>
    <script src="<?= $rootPrefix ?>messagerie/assets/js/websocket-client.js"></script>
    <!-- Dark mode detection -->
    <script>
        (function() {
            const saved = localStorage.getItem('messagerie_theme');
            if (saved === 'dark' || (!saved && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.setAttribute('data-theme', 'dark');
            }
        })();
    </script>
    <?php if (isset($wsToken)): ?>
    <script>
        window.currentUserId = <?= json_encode($user['id']) ?>;
        window.currentUserType = <?= json_encode($user['type']) ?>;
        document.addEventListener('DOMContentLoaded', () => {
            window.wsClient.init(<?= json_encode($wsUrl) ?>, <?= json_encode($wsToken) ?>);
        });
    </script>
    <?php endif; ?>
<?php
$extraHeadHtml = ob_get_clean();

// Contenu supplémentaire sidebar : dossiers + actions messagerie
ob_start();
include __DIR__ . '/sidebar_content.php';
$sidebarExtraContent = ob_get_clean();

// Actions supplémentaires dans le header
ob_start();
?>
                <?php if (isset($user)): ?>
                <button id="dark-mode-toggle" class="btn-icon" title="Changer de thème" onclick="toggleDarkMode()">
                    <i class="fas fa-moon"></i>
                </button>
                <?php endif; ?>
<?php
$headerExtraActions = ob_get_clean();

// Custom page title for topbar
if (isset($customTitle)) {
    $pageTitle = $customTitle;
} elseif (isset($currentFolder) && !empty($currentFolder)) {
    $pageTitle = 'Messagerie - ' . ucfirst($currentFolder);
}

// Inclure les templates partagés
include __DIR__ . '/../../templates/shared_header.php';
include __DIR__ . '/../../templates/shared_sidebar.php';
include __DIR__ . '/../../templates/shared_topbar.php';
?>

            <div class="content-container">