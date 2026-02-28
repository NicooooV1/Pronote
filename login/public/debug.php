<?php
/**
 * Script de débogage pour identifier les problèmes d'authentification et de redirection
 */

// Démarrer ou reprendre une session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Fonctions de débogage
function debugInfo($label, $data) {
    echo "<h3>$label</h3>";
    echo "<pre>";
    print_r($data);
    echo "</pre>";
    echo "<hr>";
}

// Créer un répertoire de logs si nécessaire
$logDir = __DIR__ . '/../logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0755, true);
}

// Journaliser les informations de débogage
$log = date('Y-m-d H:i:s') . " - Debug page accessed\n";
$log .= "SESSION: " . print_r($_SESSION, true) . "\n";
$log .= "COOKIES: " . print_r($_COOKIE, true) . "\n";
$log .= "SERVER: " . print_r($_SERVER, true) . "\n";
$log .= "-------------------------------\n";

file_put_contents($logDir . '/debug_' . date('Y-m-d') . '.log', $log, FILE_APPEND);

// En-tête HTML
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Débogage Fronote</title>
    <link rel="stylesheet" href="assets/css/pronote-login.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body { 
            display: block; 
            padding: 20px;
            background-color: var(--background-color);
        }
        .debug-container {
            max-width: 1000px;
            margin: 20px auto;
            background-color: var(--white);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-medium);
            padding: var(--space-lg);
        }
        h1, h2 { color: var(--primary-color); }
        h3 { 
            color: var(--text-color); 
            background: #f1f1f1; 
            padding: 10px; 
            border-radius: var(--radius-sm);
        }
        pre { 
            background: #f9f9f9; 
            padding: 10px; 
            overflow: auto; 
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
        }
        hr { border: 0; border-top: 1px solid var(--border-color); margin: 20px 0; }
        .action { margin: 20px 0; }
        .action-buttons {
            display: flex;
            gap: var(--space-md);
            flex-wrap: wrap;
        }
        .action-button {
            display: inline-flex;
            align-items: center;
            padding: var(--space-sm) var(--space-md);
            border-radius: var(--radius-sm);
            font-weight: 500;
            text-decoration: none;
            transition: all 0.2s;
        }
        .connected-indicator {
            display: inline-flex;
            align-items: center;
            gap: var(--space-xs);
            padding: var(--space-sm) var(--space-md);
            border-radius: var(--radius-md);
            margin-bottom: var(--space-md);
        }
        .connected-indicator i {
            font-size: 20px;
        }
        .connected-yes {
            background-color: rgba(52, 199, 89, 0.1);
            color: var(--success-color);
        }
        .connected-no {
            background-color: rgba(255, 59, 48, 0.1);
            color: var(--error-color);
        }
    </style>
</head>
<body>
    <div class="debug-container">
        <h1><i class="fas fa-bug"></i> Page de débogage Fronote</h1>
        <p>Cette page affiche des informations techniques pour aider à résoudre les problèmes d'authentification et de redirection.</p>
        
        <div class="action">
            <h2>Actions disponibles</h2>
            <div class="action-buttons">
                <a href="../public/index.php" class="action-button btn-primary">
                    <i class="fas fa-sign-in-alt"></i> Page de connexion
                </a>
                <a href="../../accueil/accueil.php" class="action-button btn-primary">
                    <i class="fas fa-home"></i> Page d'accueil
                </a>
                <?php if (isset($_SESSION['user'])): ?>
                    <a href="../public/logout.php" class="action-button btn-cancel">
                        <i class="fas fa-sign-out-alt"></i> Déconnexion
                    </a>
                <?php endif; ?>
            </div>
        </div>
        
        <h2>État de la session</h2>
        <?php if (isset($_SESSION['user'])): ?>
            <div class="connected-indicator connected-yes">
                <i class="fas fa-check-circle"></i>
                <div>
                    <strong>Utilisateur connecté :</strong> 
                    <?= htmlspecialchars($_SESSION['user']['prenom'] . ' ' . $_SESSION['user']['nom']) ?>
                    (<?= htmlspecialchars($_SESSION['user']['profil']) ?>)
                </div>
            </div>
        <?php else: ?>
            <div class="connected-indicator connected-no">
                <i class="fas fa-exclamation-circle"></i>
                <div><strong>Aucun utilisateur n'est connecté</strong></div>
            </div>
        <?php endif; ?>
        
        <h2>Informations de débogage</h2>
        
        <?php
        // Afficher les informations de la session
        debugInfo("Détails de la SESSION", $_SESSION);
        
        // Afficher les cookies
        debugInfo("COOKIES", $_COOKIE);
        
        // Afficher quelques variables importantes du serveur
        $serverInfo = [
            'REQUEST_URI' => $_SERVER['REQUEST_URI'] ?? 'N/A',
            'SCRIPT_NAME' => $_SERVER['SCRIPT_NAME'] ?? 'N/A',
            'PHP_SELF' => $_SERVER['PHP_SELF'] ?? 'N/A',
            'HTTP_REFERER' => $_SERVER['HTTP_REFERER'] ?? 'N/A',
            'REMOTE_ADDR' => $_SERVER['REMOTE_ADDR'] ?? 'N/A',
            'HTTP_USER_AGENT' => $_SERVER['HTTP_USER_AGENT'] ?? 'N/A'
        ];
        debugInfo("Informations SERVER (sélection)", $serverInfo);
        
        // Vérifier les définitions de constantes importantes
        $constants = [
            'APP_ROOT' => defined('APP_ROOT') ? APP_ROOT : 'Non définie',
            'BASE_URL' => defined('BASE_URL') ? BASE_URL : 'Non définie',
            'APP_URL' => defined('APP_URL') ? APP_URL : 'Non définie',
            'LOGIN_URL' => defined('LOGIN_URL') ? LOGIN_URL : 'Non définie',
            'HOME_URL' => defined('HOME_URL') ? HOME_URL : 'Non définie'
        ];
        debugInfo("Constantes importantes", $constants);
        ?>
    </div>
</body>
</html>
