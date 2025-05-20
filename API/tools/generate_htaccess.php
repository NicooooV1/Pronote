<?php
/**
 * Générateur de fichier .htaccess pour l'application Pronote
 * Ce script crée un fichier .htaccess personnalisé en fonction des paramètres fournis
 */

// Afficher les erreurs uniquement en mode debug
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Vérification de sécurité - Token CSRF
session_start();
$validRequest = isset($_POST['basePath']) && 
                ((isset($_SESSION['diag_token']) && 
                 isset($_POST['diag_token']) && 
                 $_POST['diag_token'] === $_SESSION['diag_token']) ||
                 true); // Pour le développement - À SUPPRIMER en production

if (!$validRequest) {
    http_response_code(403);
    die('Accès non autorisé');
}

// Récupérer les paramètres
$basePath = filter_input(INPUT_POST, 'basePath', FILTER_SANITIZE_URL) ?: '';
$forceHttps = isset($_POST['forceHttps']) && $_POST['forceHttps'] == '1';

// Nettoyer le chemin de base
$basePath = trim($basePath);
if (!empty($basePath) && $basePath[0] !== '/') {
    $basePath = '/' . $basePath;
}

// Créer le contenu du fichier .htaccess
$content = "# .htaccess généré automatiquement le " . date('Y-m-d H:i:s') . "\n\n";

// Activer le module de réécriture
$content .= "# Activation du module de réécriture\n";
$content .= "<IfModule mod_rewrite.c>\n";
$content .= "    RewriteEngine On\n";

// Définir la base URL si nécessaire
if (!empty($basePath) && $basePath !== '/') {
    $content .= "    RewriteBase " . $basePath . "\n";
}

// Forcer HTTPS si demandé
if ($forceHttps) {
    $content .= "\n    # Redirection vers HTTPS\n";
    $content .= "    RewriteCond %{HTTPS} off\n";
    $content .= "    RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]\n";
}

// Protection des fichiers sensibles
$content .= "\n    # Protection des fichiers sensibles\n";
$content .= "    <FilesMatch \"^\\.(htaccess|htpasswd|env|git)\">\n";
$content .= "        Order deny,allow\n";
$content .= "        Deny from all\n";
$content .= "    </FilesMatch>\n";

// Protection du répertoire de configuration
$content .= "\n    # Protection des fichiers de configuration\n";
$content .= "    RewriteRule ^API/config/ - [F,L]\n";

// Protection des dossiers de logs
$content .= "\n    # Protection des journaux\n";
$content .= "    RewriteRule ^API/logs/ - [F,L]\n";
$content .= "    RewriteRule ^.*\\.log$ - [F,L]\n";

// Protection du fichier d'installation
$content .= "\n    # Protection du fichier d'installation une fois exécuté\n";
$content .= "    <Files \"install.php\">\n";
$content .= "        <If \"-f '%{DOCUMENT_ROOT}/install.lock'\">\n";
$content .= "            Order allow,deny\n";
$content .= "            Deny from all\n";
$content .= "        </If>\n";
$content .= "    </Files>\n";

// Règles de sécurité supplémentaires
$content .= "\n    # Règles de sécurité supplémentaires\n";
$content .= "    <IfModule mod_headers.c>\n";
$content .= "        Header set X-Content-Type-Options \"nosniff\"\n";
$content .= "        Header set X-Frame-Options \"SAMEORIGIN\"\n";
$content .= "        Header set X-XSS-Protection \"1; mode=block\"\n";
$content .= "        # Utiliser uniquement en HTTPS\n";
$content .= "        # Header set Strict-Transport-Security \"max-age=31536000; includeSubDomains\"\n";
$content .= "    </IfModule>\n";

// Options PHP courantes
$content .= "\n    # Configuration PHP\n";
$content .= "    <IfModule mod_php7.c>\n";
$content .= "        php_flag display_errors Off\n";
$content .= "        php_flag log_errors On\n";
$content .= "        php_value error_log " . $basePath . "/API/logs/php_errors.log\n";
$content .= "        # Directives de sécurité\n";
$content .= "        php_flag session.use_strict_mode On\n";
$content .= "        php_flag session.use_only_cookies On\n";
$content .= "        php_flag session.use_trans_sid Off\n";
$content .= "        php_flag session.cookie_httponly On\n";
$content .= "    </IfModule>\n";

$content .= "</IfModule>\n";

// Options du serveur
$content .= "\n# Options générales du serveur\n";
$content .= "Options -Indexes\n";
$content .= "ServerSignature Off\n";

// Compressions GZIP si disponible
$content .= "\n# Compression GZIP\n";
$content .= "<IfModule mod_deflate.c>\n";
$content .= "    AddOutputFilterByType DEFLATE text/html text/plain text/xml text/css text/javascript application/javascript application/x-javascript application/json\n";
$content .= "</IfModule>\n";

// Ajout des types MIME manquants
$content .= "\n# Types MIME\n";
$content .= "<IfModule mod_mime.c>\n";
$content .= "    AddType application/javascript .js\n";
$content .= "    AddType text/css .css\n";
$content .= "</IfModule>\n";

// Configuration de mise en cache
$content .= "\n# Configuration de mise en cache\n";
$content .= "<IfModule mod_expires.c>\n";
$content .= "    ExpiresActive On\n";
$content .= "    ExpiresByType image/jpg \"access plus 1 year\"\n";
$content .= "    ExpiresByType image/jpeg \"access plus 1 year\"\n";
$content .= "    ExpiresByType image/png \"access plus 1 year\"\n";
$content .= "    ExpiresByType image/svg+xml \"access plus 1 year\"\n";
$content .= "    ExpiresByType image/gif \"access plus 1 year\"\n";
$content .= "    ExpiresByType text/css \"access plus 1 month\"\n";
$content .= "    ExpiresByType application/javascript \"access plus 1 month\"\n";
$content .= "</IfModule>\n";

// Ajouter des en-têtes à la page
header('Content-Type: text/plain');
header('Content-Disposition: attachment; filename=".htaccess"');
echo $content;
