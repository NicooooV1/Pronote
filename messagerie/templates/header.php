<?php
/**
 * En-tête HTML commun
 */

// Inclure le modèle de notification
require_once __DIR__ . '/../models/notification.php';

// Détection automatique du chemin de base
function getBaseUrl() {
    $scriptDir = dirname($_SERVER['SCRIPT_NAME']);
    $requestUri = $_SERVER['REQUEST_URI'];
    
    // Si nous sommes dans un sous-dossier du serveur web
    if(strpos($requestUri, $scriptDir) === 0) {
        $baseUrl = $scriptDir;
    } else {
        // Cas où nous sommes à la racine ou dans une configuration spéciale
        $pathParts = explode('/', trim($scriptDir, '/'));
        $baseDir = $pathParts[0] ?? '';
        $baseUrl = $baseDir ? "/{$baseDir}/" : '/';
    }
    
    // S'assurer que le chemin se termine par un /
    return rtrim($baseUrl, '/') . '/';
}

// Définir le chemin de base
$baseUrl = getBaseUrl();

// Titre par défaut
$pageTitle = $pageTitle ?? 'Pronote - Messagerie';

// Obtenir la page courante pour activer le menu correspondant
$currentPage = basename($_SERVER['PHP_SELF'], '.php');

// Récupérer le dossier courant pour les menus
$currentFolder = isset($_GET['folder']) ? $_GET['folder'] : 'reception';

// Vérifier si l'utilisateur est défini et s'assurer que son type est défini
if (isset($user)) {
    // Définir le type s'il n'est pas défini
    if (!isset($user['type']) && isset($user['profil'])) {
        $user['type'] = $user['profil'];
    } elseif (!isset($user['type'])) {
        $user['type'] = 'eleve'; // Valeur par défaut
    }

    // Récupérer les initiales de l'utilisateur
    $user_initials = strtoupper(substr($user['prenom'], 0, 1) . substr($user['nom'], 0, 1));
    
    // Compter les notifications non lues seulement si l'utilisateur est défini
    $unreadNotifications = countUnreadNotifications($user['id'], $user['type']);
} else {
    $unreadNotifications = 0;
    $user_initials = '';
}

// Générer le token WebSocket pour l'utilisateur
if (isset($user)) {
    require_once __DIR__ . '/../../API/Core/WebSocket.php';
    $wsToken = \API\Core\WebSocket::generateToken($user['id'], $user['type']);
    $wsUrl = getenv('WEBSOCKET_CLIENT_URL') ?: 'http://localhost:3000';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    
    <!-- Feuilles de style -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= $baseUrl ?>assets/css/main.css">
    <link rel="stylesheet" href="<?= $baseUrl ?>assets/css/sidebar.css">
    
    <?php if (in_array($currentPage, ['conversation'])): ?>
    <link rel="stylesheet" href="<?= $baseUrl ?>assets/css/conversation.css">
    <?php endif; ?>
    
    <?php if (in_array($currentPage, ['new_message', 'new_announcement', 'class_message'])): ?>
    <link rel="stylesheet" href="<?= $baseUrl ?>assets/css/forms.css">
    <?php endif; ?>
    
    <!-- Socket.IO client -->
    <script src="https://cdn.socket.io/4.6.1/socket.io.min.js"></script>
    <script src="<?= $baseUrl ?>assets/js/websocket-client.js"></script>
    
    <?php if (isset($wsToken)): ?>
    <script>
        // Variables globales pour WebSocket
        window.currentUserId = <?= json_encode($user['id']) ?>;
        window.currentUserType = <?= json_encode($user['type']) ?>;
        
        // Initialiser WebSocket au chargement
        document.addEventListener('DOMContentLoaded', () => {
            window.wsClient.init(<?= json_encode($wsUrl) ?>, <?= json_encode($wsToken) ?>);
        });
    </script>
    <?php endif; ?>
</head>
<body data-user-id="<?= $user['id'] ?? '' ?>" data-user-type="<?= $user['type'] ?? '' ?>">
    <div class="app-container">
        <?php include 'sidebar.php'; ?>
        
        <div class="main-content">
            <div class="top-header">
                <div class="page-title">
                    <?php if (isset($showBackButton) && $showBackButton): ?>
                    <a href="index.php" class="back-button">
                        <i class="fas fa-arrow-left"></i> Retour
                    </a>
                    <?php endif; ?>
                    
                    <h1>
                        <?php if (isset($customTitle)): ?>
                            <?= htmlspecialchars($customTitle) ?>
                        <?php else: ?>
                            Messagerie
                            <?php if (isset($currentFolder) && !empty($currentFolder)): ?>
                            - <?= ucfirst(htmlspecialchars($currentFolder)) ?>
                            <?php endif; ?>
                        <?php endif; ?>
                    </h1>
                </div>
                
                <div class="header-actions">
                    <?php if (isset($user)): ?>
                    <div class="user-avatar">
                        <?= $user_initials ?? substr($user['prenom'], 0, 1) . substr($user['nom'], 0, 1) ?>
                    </div>
                    
                    <a href="<?= isset($logoutUrl) ? $logoutUrl : '../login/public/logout.php' ?>" class="logout-button" title="Déconnexion">
                        <i class="fas fa-sign-out-alt"></i>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="content-container">