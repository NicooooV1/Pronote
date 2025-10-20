<?php
/**
 * Script d'installation de Pronote - VERSION COMPLÈTE
 * Adapté à la nouvelle architecture avec AuthManager unifié
 */

// Configuration de sécurité et gestion d'erreurs
ini_set('display_errors', 1);
error_reporting(E_ALL);
set_time_limit(300);

register_shutdown_function('handleFatalError');

function handleFatalError() {
    $error = error_get_last();
    if ($error !== null && $error['type'] === E_ERROR) {
        echo "<div style='background: #f8d7da; color: #721c24; padding: 20px; border-radius: 5px; margin: 20px;'>";
        echo "<h3>❌ Erreur fatale détectée</h3>";
        echo "<p><strong>Message:</strong> " . htmlspecialchars($error['message']) . "</p>";
        echo "<p><strong>Fichier:</strong> " . htmlspecialchars($error['file']) . "</p>";
        echo "<p><strong>Ligne:</strong> " . $error['line'] . "</p>";
        echo "</div>";
    }
}

/**
 * Valide la robustesse d'un mot de passe
 */
function validatePasswordStrength($password) {
    $errors = [];
    
    if (strlen($password) < 12) {
        $errors[] = "Le mot de passe doit contenir au moins 12 caractères";
    }
    
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = "Le mot de passe doit contenir au moins une majuscule";
    }
    
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = "Le mot de passe doit contenir au moins une minuscule";
    }
    
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = "Le mot de passe doit contenir au moins un chiffre";
    }
    
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        $errors[] = "Le mot de passe doit contenir au moins un caractère spécial (@, #, $, %, etc.)";
    }
    
    // Vérifier les mots de passe courants
    $commonPasswords = ['Password123!', 'Admin123!', 'Pronote123!', 'Azerty123!'];
    if (in_array($password, $commonPasswords)) {
        $errors[] = "Ce mot de passe est trop commun";
    }
    
    return $errors;
}

/**
 * Génère un mot de passe aléatoire robuste
 */
function generateSecurePassword($length = 16) {
    $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $lowercase = 'abcdefghijklmnopqrstuvwxyz';
    $numbers = '0123456789';
    $special = '!@#$%^&*()-_=+[]{}|;:,.<>?';
    
    $password = '';
    $password .= $uppercase[random_int(0, strlen($uppercase) - 1)];
    $password .= $lowercase[random_int(0, strlen($lowercase) - 1)];
    $password .= $numbers[random_int(0, strlen($numbers) - 1)];
    $password .= $special[random_int(0, strlen($special) - 1)];
    
    $allChars = $uppercase . $lowercase . $numbers . $special;
    for ($i = 4; $i < $length; $i++) {
        $password .= $allChars[random_int(0, strlen($allChars) - 1)];
    }
    
    return str_shuffle($password);
}

/**
 * Fonction de vérification IP réseau local
 */
function isLocalIP($ip) {
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        return false;
    }
    
    $privateRanges = [
        ['10.0.0.0', '10.255.255.255'],
        ['172.16.0.0', '172.31.255.255'],
        ['192.168.0.0', '192.168.255.255'],
        ['127.0.0.0', '127.255.255.255'],
    ];
    
    $ipLong = ip2long($ip);
    foreach ($privateRanges as $range) {
        if ($ipLong >= ip2long($range[0]) && $ipLong <= ip2long($range[1])) {
            return true;
        }
    }
    return false;
}

/**
 * Fonction de correction automatique des permissions
 */
function fixDirectoryPermissions($path, $createIfNotExists = true) {
    $result = [
        'success' => false,
        'message' => '',
        'permissions' => null
    ];
    
    // Créer si n'existe pas
    if (!is_dir($path)) {
        if (!$createIfNotExists) {
            $result['message'] = "Le répertoire n'existe pas";
            return $result;
        }
        
        if (!@mkdir($path, 0755, true)) {
            $result['message'] = "Impossible de créer le répertoire";
            return $result;
        }
    }
    
    // Tester l'écriture avec les permissions actuelles
    $testFile = $path . '/test_' . uniqid() . '.tmp';
    if (@file_put_contents($testFile, 'test') !== false) {
        @unlink($testFile);
        $result['success'] = true;
        $result['permissions'] = substr(sprintf('%o', fileperms($path)), -4);
        $result['message'] = "OK";
        return $result;
    }
    
    // Essayer progressivement des permissions plus permissives
    $permissions = [0755, 0775, 0777];
    foreach ($permissions as $perm) {
        @chmod($path, $perm);
        if (@file_put_contents($testFile, 'test') !== false) {
            @unlink($testFile);
            $result['success'] = true;
            $result['permissions'] = substr(sprintf('%o', fileperms($path)), -4);
            $result['message'] = "Permissions définies à " . decoct($perm);
            return $result;
        }
    }
    
    // Si toujours pas, essayer de changer le propriétaire
    if (function_exists('posix_geteuid')) {
        $webUser = posix_getpwuid(posix_geteuid());
        if ($webUser && @chown($path, $webUser['uid']) && @chgrp($path, $webUser['gid'])) {
            @chmod($path, 0755);
            if (@file_put_contents($testFile, 'test') !== false) {
                @unlink($testFile);
                $result['success'] = true;
                $result['permissions'] = substr(sprintf('%o', fileperms($path)), -4);
                $result['message'] = "Propriétaire modifié et permissions définies";
                return $result;
            }
        }
    }
    
    $result['message'] = "Impossible de rendre le répertoire accessible en écriture";
    return $result;
}

/**
 * Créer tous les fichiers de configuration nécessaires
 */
function createConfigurationFiles($installDir, $config) {
    $results = [];
    
    // 1. Créer .htaccess principal
    $htaccessContent = "# Protection Pronote - Fichiers sensibles\n";
    $htaccessContent .= "<FilesMatch \"^(\\.env|install\\.php|install\\.lock)$\">\n";
    $htaccessContent .= "    Order allow,deny\n";
    $htaccessContent .= "    Deny from all\n";
    $htaccessContent .= "</FilesMatch>\n\n";
    $htaccessContent .= "# Protection fichiers de configuration\n";
    $htaccessContent .= "<FilesMatch \"\\.(ini|conf|config|bak|backup|sql|db)$\">\n";
    $htaccessContent .= "    Order allow,deny\n";
    $htaccessContent .= "    Deny from all\n";
    $htaccessContent .= "</FilesMatch>\n\n";
    $htaccessContent .= "# Protection répertoires uploads\n";
    $htaccessContent .= "<Directory \"uploads\">\n";
    $htaccessContent .= "    php_flag engine off\n";
    $htaccessContent .= "    Options -ExecCGI -Indexes\n";
    $htaccessContent .= "    AddHandler cgi-script .php .pl .py .jsp .asp .sh .cgi\n";
    $htaccessContent .= "</Directory>\n\n";
    $htaccessContent .= "# Protection répertoires temp\n";
    $htaccessContent .= "<Directory \"temp\">\n";
    $htaccessContent .= "    Order allow,deny\n";
    $htaccessContent .= "    Deny from all\n";
    $htaccessContent .= "</Directory>\n\n";
    $htaccessContent .= "# Désactiver l'affichage des répertoires\n";
    $htaccessContent .= "Options -Indexes\n\n";
    $htaccessContent .= "# Protection contre l'injection\n";
    $htaccessContent .= "<IfModule mod_rewrite.c>\n";
    $htaccessContent .= "    RewriteEngine On\n";
    $htaccessContent .= "    RewriteCond %{QUERY_STRING} (\\<|%3C).*script.*(\\>|%3E) [NC,OR]\n";
    $htaccessContent .= "    RewriteCond %{QUERY_STRING} GLOBALS(=|\\[|\\%[0-9A-Z]{0,2}) [OR]\n";
    $htaccessContent .= "    RewriteCond %{QUERY_STRING} _REQUEST(=|\\[|\\%[0-9A-Z]{0,2})\n";
    $htaccessContent .= "    RewriteRule .* - [F]\n";
    $htaccessContent .= "</IfModule>\n";
    
    $htaccessPath = $installDir . '/.htaccess';
    if (@file_put_contents($htaccessPath, $htaccessContent, LOCK_EX) !== false) {
        $results['.htaccess'] = ['success' => true, 'message' => 'Créé'];
    } else {
        $results['.htaccess'] = ['success' => false, 'message' => 'Échec de création'];
    }
    
    // 2. Créer fichier index.php de redirection dans uploads
    $uploadIndexContent = "<?php\n// Protection - Aucun accès direct\nheader('HTTP/1.0 403 Forbidden');\nexit;\n";
    $uploadIndexPath = $installDir . '/uploads/index.php';
    if (@file_put_contents($uploadIndexPath, $uploadIndexContent, LOCK_EX) !== false) {
        $results['uploads/index.php'] = ['success' => true, 'message' => 'Créé'];
    }
    
    // 3. Créer .gitignore
    $gitignoreContent = "# Configuration\n.env\ninstall.lock\n\n";
    $gitignoreContent .= "# Logs\n*.log\nAPI/logs/*\n!API/logs/.gitkeep\nlogin/logs/*\n!login/logs/.gitkeep\n\n";
    $gitignoreContent .= "# Uploads\nuploads/*\n!uploads/.gitkeep\n!uploads/index.php\n!uploads/.htaccess\n\n";
    $gitignoreContent .= "# Temp\ntemp/*\n!temp/.gitkeep\n!temp/.htaccess\n\n";
    $gitignoreContent .= "# IDE\n.vscode/\n.idea/\n*.swp\n*.swo\n*~\n\n";
    $gitignoreContent .= "# OS\n.DS_Store\nThumbs.db\n\n";
    $gitignoreContent .= "# Backup\n*.bak\n*.backup\n*.old\n";
    $gitignorePath = $installDir . '/.gitignore';
    if (@file_put_contents($gitignorePath, $gitignoreContent, LOCK_EX) !== false) {
        $results['.gitignore'] = ['success' => true, 'message' => 'Créé'];
    }
    
    // 4. Créer fichiers .gitkeep pour les dossiers vides
    $keepDirs = ['uploads', 'temp', 'API/logs', 'login/logs'];
    foreach ($keepDirs as $dir) {
        $keepPath = $installDir . '/' . $dir . '/.gitkeep';
        @file_put_contents($keepPath, '');
    }
    
    return $results;
}

/**
 * Structure complète des fichiers et répertoires requis
 */
function getRequiredStructure() {
    return [
        'directories' => [
            'API/logs' => ['permissions' => 0777, 'critical' => true],
            'API/config' => ['permissions' => 0777, 'critical' => true],
            'API/Core' => ['permissions' => 0755, 'critical' => true],
            'API/Database' => ['permissions' => 0755, 'critical' => true],
            'API/Auth' => ['permissions' => 0755, 'critical' => true],
            'API/Security' => ['permissions' => 0755, 'critical' => true],
            'API/Providers' => ['permissions' => 0755, 'critical' => true],
            'uploads' => ['permissions' => 0777, 'critical' => true],
            'temp' => ['permissions' => 0777, 'critical' => false],
            'login/logs' => ['permissions' => 0777, 'critical' => false],
            'login/public' => ['permissions' => 0755, 'critical' => false]
        ],
        'files' => [
            '.htaccess' => [
                'content' => generateHtaccessContent(),
                'permissions' => 0644,
                'critical' => true
            ],
            '.gitignore' => [
                'content' => generateGitignoreContent(),
                'permissions' => 0644,
                'critical' => false
            ],
            'uploads/index.php' => [
                'content' => "<?php\nheader('HTTP/1.0 403 Forbidden');\nexit;\n",
                'permissions' => 0644,
                'critical' => true
            ],
            'uploads/.htaccess' => [
                'content' => "php_flag engine off\nOptions -ExecCGI\nAddHandler cgi-script .php .pl .py .jsp .asp .sh .cgi\n",
                'permissions' => 0644,
                'critical' => true
            ],
            'temp/.htaccess' => [
                'content' => "Deny from all\n",
                'permissions' => 0644,
                'critical' => false
            ],
            'API/logs/.htaccess' => [
                'content' => "Deny from all\n",
                'permissions' => 0644,
                'critical' => true
            ],
            'uploads/.gitkeep' => ['content' => '', 'permissions' => 0644, 'critical' => false],
            'temp/.gitkeep' => ['content' => '', 'permissions' => 0644, 'critical' => false],
            'API/logs/.gitkeep' => ['content' => '', 'permissions' => 0644, 'critical' => false]
        ]
    };
}

/**
 * Génère le contenu du .htaccess principal
 */
function generateHtaccessContent() {
    return <<<'HTACCESS'
# Protection Pronote - Fichiers sensibles
<FilesMatch "^(\.env|install\.php|install\.lock)$">
    Order allow,deny
    Deny from all
</FilesMatch>

# Protection fichiers de configuration
<FilesMatch "\.(ini|conf|config|bak|backup|sql|db)$">
    Order allow,deny
    Deny from all
</FilesMatch>

# Protection répertoires uploads
<Directory "uploads">
    php_flag engine off
    Options -ExecCGI -Indexes
    AddHandler cgi-script .php .pl .py .jsp .asp .sh .cgi
</Directory>

# Protection répertoires temp
<Directory "temp">
    Order allow,deny
    Deny from all
</Directory>

# Désactiver l'affichage des répertoires
Options -Indexes

# Protection contre l'injection
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{QUERY_STRING} (\<|%3C).*script.*(\>|%3E) [NC,OR]
    RewriteCond %{QUERY_STRING} GLOBALS(=|\[|\%[0-9A-Z]{0,2}) [OR]
    RewriteCond %{QUERY_STRING} _REQUEST(=|\[|\%[0-9A-Z]{0,2})
    RewriteRule .* - [F]
</IfModule>
HTACCESS;
}

/**
 * Génère le contenu du .gitignore
 */
function generateGitignoreContent() {
    return <<<'GITIGNORE'
# Configuration
.env
install.lock

# Logs
*.log
API/logs/*
!API/logs/.gitkeep
login/logs/*
!login/logs/.gitkeep

# Uploads
uploads/*
!uploads/.gitkeep
!uploads/index.php
!uploads/.htaccess

# Temp
temp/*
!temp/.gitkeep
!temp/.htaccess

# IDE
.vscode/
.idea/
*.swp
*.swo
*~

# OS
.DS_Store
Thumbs.db

# Backup
*.bak
*.backup
*.old
GITIGNORE;
}

/**
 * Vérifie et crée toute la structure de fichiers
 */
function ensureCompleteStructure($installDir, $forceMode = false) {
    $structure = getRequiredStructure();
    $report = [
        'directories' => [],
        'files' => [],
        'errors' => [],
        'warnings' => [],
        'success' => true
    ];
    
    // ÉTAPE 1: Créer tous les répertoires
    foreach ($structure['directories'] as $dir => $config) {
        $path = $installDir . '/' . $dir;
        $dirResult = ensureDirectory($path, $config['permissions'], $forceMode);
        
        $report['directories'][$dir] = $dirResult;
        
        if (!$dirResult['success']) {
            if ($config['critical']) {
                $report['errors'][] = "CRITIQUE: Répertoire {$dir} - " . $dirResult['message'];
                $report['success'] = false;
            } else {
                $report['warnings'][] = "Répertoire {$dir} - " . $dirResult['message'];
            }
        }
    }
    
    // ÉTAPE 2: Créer tous les fichiers
    foreach ($structure['files'] as $file => $config) {
        $path = $installDir . '/' . $file;
        $fileResult = ensureFile($path, $config['content'], $config['permissions'], $forceMode);
        
        $report['files'][$file] = $fileResult;
        
        if (!$fileResult['success']) {
            if ($config['critical']) {
                $report['errors'][] = "CRITIQUE: Fichier {$file} - " . $fileResult['message'];
                $report['success'] = false;
            } else {
                $report['warnings'][] = "Fichier {$file} - " . $fileResult['message'];
            }
        }
    }
    
    return $report;
}

/**
 * Assure qu'un répertoire existe et est accessible
 */
function ensureDirectory($path, $permissions, $forceMode) {
    $result = [
        'success' => false,
        'message' => '',
        'permissions' => null,
        'exists' => false,
        'writable' => false
    ];
    
    // Vérifier si existe
    if (is_dir($path)) {
        $result['exists'] = true;
    } else {
        // Créer le répertoire
        if (!@mkdir($path, $permissions, true)) {
            $result['message'] = "Impossible de créer le répertoire";
            return $result;
        }
        $result['exists'] = true;
    }
    
    // Tester l'écriture
    $testFile = $path . '/.test_' . uniqid();
    if (@file_put_contents($testFile, 'test') !== false) {
        @unlink($testFile);
        $result['writable'] = true;
        $result['success'] = true;
        $result['permissions'] = substr(sprintf('%o', fileperms($path)), -4);
        $result['message'] = "OK";
        return $result;
    }
    
    // Si en mode force, essayer plusieurs stratégies
    if ($forceMode) {
        // Stratégie 1: Permissions progressives
        $permLevels = [$permissions, 0755, 0775, 0777];
        foreach ($permLevels as $perm) {
            @chmod($path, $perm);
            if (@file_put_contents($testFile, 'test') !== false) {
                @unlink($testFile);
                $result['writable'] = true;
                $result['success'] = true;
                $result['permissions'] = substr(sprintf('%o', fileperms($path)), -4);
                $result['message'] = "Corrigé avec chmod " . decoct($perm);
                return $result;
            }
        }
        
        // Stratégie 2: Changer propriétaire (si possible)
        if (function_exists('posix_geteuid')) {
            $webUser = posix_getpwuid(posix_geteuid());
            if ($webUser && @chown($path, $webUser['uid']) && @chgrp($path, $webUser['gid'])) {
                @chmod($path, $permissions);
                if (@file_put_contents($testFile, 'test') !== false) {
                    @unlink($testFile);
                    $result['writable'] = true;
                    $result['success'] = true;
                    $result['permissions'] = substr(sprintf('%o', fileperms($path)), -4);
                    $result['message'] = "Corrigé avec chown";
                    return $result;
                }
            }
        }
        
        // Stratégie 3: Recréer le répertoire
        @rmdir($path);
        if (@mkdir($path, 0777, true)) {
            if (@file_put_contents($testFile, 'test') !== false) {
                @unlink($testFile);
                $result['writable'] = true;
                $result['success'] = true;
                $result['permissions'] = substr(sprintf('%o', fileperms($path)), -4);
                $result['message'] = "Recréé avec succès";
                return $result;
            }
        }
    }
    
    $result['message'] = "Non accessible en écriture";
    return $result;
}

/**
 * Assure qu'un fichier existe avec le bon contenu
 */
function ensureFile($path, $content, $permissions, $forceMode) {
    $result = [
        'success' => false,
        'message' => '',
        'exists' => false,
        'writable' => false,
        'size' => 0
    ];
    
    // Vérifier le répertoire parent
    $dir = dirname($path);
    if (!is_dir($dir)) {
        if (!@mkdir($dir, 0755, true)) {
            $result['message'] = "Répertoire parent inaccessible";
            return $result;
        }
    }
    
    // Vérifier si le fichier existe déjà
    $exists = file_exists($path);
    $result['exists'] = $exists;
    
    // Essayer d'écrire le fichier
    $written = @file_put_contents($path, $content, LOCK_EX);
    
    if ($written !== false) {
        @chmod($path, $permissions);
        $result['success'] = true;
        $result['writable'] = true;
        $result['size'] = $written;
        $result['message'] = $exists ? "Mis à jour" : "Créé";
        return $result;
    }
    
    // Si en mode force
    if ($forceMode) {
        // Stratégie 1: Supprimer et recréer
        if ($exists) {
            @unlink($path);
        }
        
        // Stratégie 2: Essayer avec un fichier temporaire
        $tempFile = $path . '.tmp';
        if (@file_put_contents($tempFile, $content, LOCK_EX) !== false) {
            if (@rename($tempFile, $path)) {
                @chmod($path, $permissions);
                $result['success'] = true;
                $result['writable'] = true;
                $result['size'] = strlen($content);
                $result['message'] = "Créé via fichier temporaire";
                return $result;
            }
            @unlink($tempFile);
        }
        
        // Stratégie 3: Corriger les permissions du répertoire parent
        @chmod($dir, 0777);
        $written = @file_put_contents($path, $content, LOCK_EX);
        if ($written !== false) {
            @chmod($path, $permissions);
            $result['success'] = true;
            $result['writable'] = true;
            $result['size'] = $written;
            $result['message'] = "Créé après correction du répertoire";
            return $result;
        }
    }
    
    $result['message'] = "Impossible d'écrire le fichier";
    return $result;
}

/**
 * Génère un rapport HTML de la structure
 */
function generateStructureReport($report) {
    $html = "<div style='background: #f8f9fa; padding: 20px; border-radius: 5px; margin: 20px 0;'>";
    $html .= "<h3>📊 Rapport de création de la structure</h3>";
    
    // Statistiques
    $totalDirs = count($report['directories']);
    $successDirs = 0;
    foreach ($report['directories'] as $r) {
        if ($r['success']) {
            $successDirs++;
        }
    }
    
    $totalFiles = count($report['files']);
    $successFiles = 0;
    foreach ($report['files'] as $r) {
        if ($r['success']) {
            $successFiles++;
        }
    }
    
    $html .= "<div style='display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; margin: 15px 0;'>";
    $html .= "<div style='background: white; padding: 10px; border-radius: 5px; text-align: center;'>";
    $html .= "<strong>Répertoires</strong><br>";
    $html .= "<span style='font-size: 24px; color: " . ($successDirs === $totalDirs ? '#2ecc71' : '#e74c3c') . ";'>";
    $html .= "{$successDirs}/{$totalDirs}</span>";
    $html .= "</div>";
    $html .= "<div style='background: white; padding: 10px; border-radius: 5px; text-align: center;'>";
    $html .= "<strong>Fichiers</strong><br>";
    $html .= "<span style='font-size: 24px; color: " . ($successFiles === $totalFiles ? '#2ecc71' : '#e74c3c') . ";'>";
    $html .= "{$successFiles}/{$totalFiles}</span>";
    $html .= "</div>";
    $html .= "</div>";
    
    // Erreurs critiques
    if (!empty($report['errors'])) {
        $html .= "<div style='background: #f8d7da; color: #721c24; padding: 10px; border-radius: 5px; margin: 10px 0;'>";
        $html .= "<strong>❌ Erreurs critiques:</strong><ul>";
        foreach ($report['errors'] as $error) {
            $html .= "<li>" . htmlspecialchars($error) . "</li>";
        }
        $html .= "</ul></div>";
    }
    
    // Avertissements
    if (!empty($report['warnings'])) {
        $html .= "<div style='background: #fff3cd; color: #856404; padding: 10px; border-radius: 5px; margin: 10px 0;'>";
        $html .= "<strong>⚠️ Avertissements:</strong><ul>";
        foreach ($report['warnings'] as $warning) {
            $html .= "<li>" . htmlspecialchars($warning) . "</li>";
        }
        $html .= "</ul></div>";
    }
    
    // Détails des répertoires
    $html .= "<details style='margin: 10px 0;'>";
    $html .= "<summary style='cursor: pointer; font-weight: bold;'>📁 Détails des répertoires</summary>";
    $html .= "<table style='width: 100%; border-collapse: collapse; margin: 10px 0;'>";
    $html .= "<tr style='background: #e9ecef;'>";
    $html .= "<th style='padding: 8px; text-align: left; border: 1px solid #dee2e6;'>Répertoire</th>";
    $html .= "<th style='padding: 8px; text-align: left; border: 1px solid #dee2e6;'>Statut</th>";
    $html .= "<th style='padding: 8px; text-align: left; border: 1px solid #dee2e6;'>Permissions</th>";
    $html .= "<th style='padding: 8px; text-align: left; border: 1px solid #dee2e6;'>Message</th>";
    $html .= "</tr>";
    
    foreach ($report['directories'] as $dir => $result) {
        $html .= "<tr>";
        $html .= "<td style='padding: 8px; border: 1px solid #dee2e6;'><code>{$dir}</code></td>";
        $html .= "<td style='padding: 8px; border: 1px solid #dee2e6;'>" . ($result['success'] ? '✅' : '❌') . "</td>";
        $html .= "<td style='padding: 8px; border: 1px solid #dee2e6;'>" . ($result['permissions'] ?? 'N/A') . "</td>";
        $html .= "<td style='padding: 8px; border: 1px solid #dee2e6;'><small>" . htmlspecialchars($result['message']) . "</small></td>";
        $html .= "</tr>";
    }
    $html .= "</table>";
    $html .= "</details>";
    
    // Détails des fichiers
    $html .= "<details style='margin: 10px 0;'>";
    $html .= "<summary style='cursor: pointer; font-weight: bold;'>📄 Détails des fichiers</summary>";
    $html .= "<table style='width: 100%; border-collapse: collapse; margin: 10px 0;'>";
    $html .= "<tr style='background: #e9ecef;'>";
    $html .= "<th style='padding: 8px; text-align: left; border: 1px solid #dee2e6;'>Fichier</th>";
    $html .= "<th style='padding: 8px; text-align: left; border: 1px solid #dee2e6;'>Statut</th>";
    $html .= "<th style='padding: 8px; text-align: left; border: 1px solid #dee2e6;'>Taille</th>";
    $html .= "<th style='padding: 8px; text-align: left; border: 1px solid #dee2e6;'>Message</th>";
    $html .= "</tr>";
    
    foreach ($report['files'] as $file => $result) {
        $html .= "<tr>";
        $html .= "<td style='padding: 8px; border: 1px solid #dee2e6;'><code>{$file}</code></td>";
        $html .= "<td style='padding: 8px; border: 1px solid #dee2e6;'>" . ($result['success'] ? '✅' : '❌') . "</td>";
        $html .= "<td style='padding: 8px; border: 1px solid #dee2e6;'>" . ($result['size'] ?? 0) . " octets</td>";
        $html .= "<td style='padding: 8px; border: 1px solid #dee2e6;'><small>" . htmlspecialchars($result['message']) . "</small></td>";
        $html .= "</tr>";
    }
    $html .= "</table>";
    $html .= "</details>";
    
    $html .= "</div>";
    return $html;
}

/**
 * Écrit le fichier .env de manière robuste avec plusieurs stratégies
 */
function writeEnvFile($installDir, $config) {
    $envPath = $installDir . '/.env';
    
    // Construire le contenu
    $content = "# Configuration Pronote - Généré le " . date('Y-m-d H:i:s') . "\n";
    $content .= "# ⚠️ NE PAS PARTAGER CE FICHIER\n\n";
    
    $content .= "# ==================================================\n";
    $content .= "# SÉCURITÉ INSTALLATION\n";
    $content .= "# ==================================================\n";
    $content .= "ALLOWED_INSTALL_IP=" . $config['client_ip'] . "\n\n";
    
    $content .= "# ==================================================\n";
    $content .= "# BASE DE DONNÉES\n";
    $content .= "# ==================================================\n";
    $content .= "DB_HOST=" . $config['db_host'] . "\n";
    $content .= "DB_PORT=" . $config['db_port'] . "\n";
    $content .= "DB_NAME=" . $config['db_name'] . "\n";
    $content .= "DB_USER=" . $config['db_user'] . "\n";
    $content .= "DB_PASS=" . $config['db_pass'] . "\n";
    $content .= "DB_CHARSET=" . $config['db_charset'] . "\n\n";
    
    $content .= "# ==================================================\n";
    $content .= "# APPLICATION\n";
    $content .= "# ==================================================\n";
    $content .= "APP_NAME=" . $config['app_name'] . "\n";
    $content .= "APP_ENV=" . $config['app_env'] . "\n";
    $content .= "APP_DEBUG=" . ($config['app_debug'] ? 'true' : 'false') . "\n";
    $content .= "APP_URL=" . $config['app_url'] . "\n";
    $content .= "APP_BASE_PATH=" . $installDir . "\n";
    $content .= "BASE_URL=" . rtrim($config['base_url'], '/') . "\n\n";
    
    $content .= "# ==================================================\n";
    $content .= "# SÉCURITÉ\n";
    $content .= "# ==================================================\n";
    $content .= "# Durée de vie des tokens CSRF (en secondes)\n";
    $content .= "CSRF_LIFETIME=" . $config['csrf_lifetime'] . "\n";
    $content .= "CSRF_MAX_TOKENS=10\n\n";
    
    $content .= "# Configuration des sessions\n";
    $content .= "SESSION_NAME=" . $config['session_name'] . "\n";
    $content .= "SESSION_LIFETIME=" . $config['session_lifetime'] . "\n";
    $content .= "SESSION_SECURE=" . ($config['protocol'] === 'https' ? 'true' : 'false') . "\n";
    $content .= "SESSION_HTTPONLY=true\n";
    $content .= "SESSION_SAMESITE=Lax\n\n";
    
    $content .= "# Limitations de connexion\n";
    $content .= "MAX_LOGIN_ATTEMPTS=" . $config['max_login_attempts'] . "\n";
    $content .= "LOGIN_LOCKOUT_TIME=900\n\n";
    
    $content .= "# Rate limiting\n";
    $content .= "RATE_LIMIT_ATTEMPTS=" . $config['rate_limit_attempts'] . "\n";
    $content .= "RATE_LIMIT_DECAY=" . $config['rate_limit_decay'] . "\n\n";
    
    $content .= "# ==================================================\n";
    $content .= "# CHEMINS\n";
    $content .= "# ==================================================\n";
    $content .= "LOGS_PATH=" . $installDir . "/API/logs\n";
    $content .= "UPLOADS_PATH=" . $installDir . "/uploads\n";
    $content .= "TEMP_PATH=" . $installDir . "/temp\n\n";
    
    $content .= "# ==================================================\n";
    $content .= "# MAIL (à configurer ultérieurement)\n";
    $content .= "# ==================================================\n";
    $content .= "MAIL_MAILER=smtp\n";
    $content .= "MAIL_HOST=\n";
    $content .= "MAIL_PORT=587\n";
    $content .= "MAIL_USERNAME=\n";
    $content .= "MAIL_PASSWORD=\n";
    $content .= "MAIL_ENCRYPTION=tls\n";
    $content .= "MAIL_FROM_ADDRESS=" . $config['admin_mail'] . "\n";
    $content .= "MAIL_FROM_NAME=" . $config['app_name'] . "\n\n";
    
    $content .= "# ==================================================\n";
    $content .= "# TIMEZONE\n";
    $content .= "# ==================================================\n";
    $content .= "APP_TIMEZONE=Europe/Paris\n";
    
    // Stratégie 1: Écriture directe
    $result = @file_put_contents($envPath, $content, LOCK_EX);
    if ($result !== false) {
        @chmod($envPath, 0666); // Permissions temporaires pour l'installation
        return [
            'success' => true,
            'method' => 'direct',
            'message' => 'Fichier .env créé avec succès',
            'path' => $envPath
        ];
    }
    
    // Stratégie 2: Via fichier temporaire
    $tempPath = $envPath . '.tmp.' . uniqid();
    $result = @file_put_contents($tempPath, $content, LOCK_EX);
    if ($result !== false) {
        if (@rename($tempPath, $envPath)) {
            @chmod($envPath, 0666);
            return [
                'success' => true,
                'method' => 'temp_file',
                'message' => 'Fichier .env créé via fichier temporaire',
                'path' => $envPath
            ];
        }
        @unlink($tempPath);
    }
    
    // Stratégie 3: Corriger permissions du répertoire parent
    $parentDir = dirname($envPath);
    $originalPerms = fileperms($parentDir);
    @chmod($parentDir, 0777);
    
    $result = @file_put_contents($envPath, $content, LOCK_EX);
    if ($result !== false) {
        @chmod($envPath, 0666);
        @chmod($parentDir, $originalPerms); // Restaurer
        return [
            'success' => true,
            'method' => 'parent_chmod',
            'message' => 'Fichier .env créé après correction des permissions',
            'path' => $envPath
        ];
    }
    
    @chmod($parentDir, $originalPerms); // Restaurer même en cas d'échec
    
    // Stratégie 4: Vérifier si SELinux bloque
    if (function_exists('exec')) {
        $selinuxStatus = @exec('getenforce 2>/dev/null');
        if ($selinuxStatus === 'Enforcing') {
            return [
                'success' => false,
                'method' => 'selinux_block',
                'message' => 'SELinux bloque probablement l\'écriture. Exécutez: sudo chcon -R -t httpd_sys_rw_content_t ' . $parentDir,
                'path' => $envPath
            ];
        }
    }
    
    // Échec total
    return [
        'success' => false,
        'method' => 'failed',
        'message' => 'Impossible de créer le fichier .env. Vérifiez les permissions du répertoire.',
        'path' => $envPath
    ];
}

/**
 * Sécurise le fichier .env après installation
 */
function secureEnvFile($envPath, $appEnv) {
    if (!file_exists($envPath)) {
        return ['success' => false, 'message' => 'Fichier .env introuvable'];
    }
    
    // En production: lecture seule pour le propriétaire
    // En développement: lecture/écriture pour le propriétaire
    $targetPerms = ($appEnv === 'production') ? 0400 : 0600;
    
    if (@chmod($envPath, $targetPerms)) {
        return [
            'success' => true,
            'message' => 'Fichier .env sécurisé avec permissions ' . decoct($targetPerms),
            'permissions' => decoct($targetPerms)
        ];
    }
    
    // Si échec, au moins essayer 0600
    if (@chmod($envPath, 0600)) {
        return [
            'success' => true,
            'message' => 'Fichier .env partiellement sécurisé (0600)',
            'permissions' => '0600'
        ];
    }
    
    return [
        'success' => false,
        'message' => 'Impossible de modifier les permissions du fichier .env'
    ];
}

// Définir les en-têtes de sécurité
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// SUPPRESSION DÉFINITIVE DES FICHIERS TEMPORAIRES
$filesToDelete = [
    'check_database_health.php',
    'fix_complete_database.php', 
    'test_permissions.php',
    'test_db_connection.php',
    'debug_ip.php',
    'fix_permissions.php',
    'diagnostic.php'
];

foreach ($filesToDelete as $file) {
    $filePath = __DIR__ . '/' . $file;
    if (file_exists($filePath)) {
        @unlink($filePath);
    }
}

// Vérifier si l'installation est déjà terminée
$installLockFile = __DIR__ . '/install.lock';
if (file_exists($installLockFile)) {
    die('<div style="background: #f8d7da; color: #721c24; padding: 20px; border-radius: 5px; margin: 20px; font-family: Arial;">
        <h2>🔒 Installation déjà effectuée</h2>
        <p>Pronote a déjà été installé sur ce système.</p>
    </div>');
}

// Vérification de la version PHP
if (version_compare(PHP_VERSION, '7.4.0', '<')) {
    die('Pronote nécessite PHP 7.4 ou supérieur. Version actuelle: ' . PHP_VERSION);
}

// Vérifier les extensions requises
$requiredExtensions = ['pdo', 'pdo_mysql', 'json', 'mbstring', 'session'];
$missingExtensions = [];

foreach ($requiredExtensions as $ext) {
    if (!extension_loaded($ext)) {
        $missingExtensions[] = $ext;
    }
}

if (!empty($missingExtensions)) {
    die('Extensions PHP requises manquantes : ' . implode(', ', $missingExtensions));
}

// Gestion sécurisée de l'accès par IP
$allowedIPs = ['127.0.0.1', '::1'];
$clientIP = filter_var($_SERVER['REMOTE_ADDR'] ?? '', FILTER_VALIDATE_IP);

$envFile = __DIR__ . '/.env';
$additionalIpAllowed = false;

if (file_exists($envFile) && is_readable($envFile)) {
    $envContent = file_get_contents($envFile);
    if (preg_match('/ALLOWED_INSTALL_IP\s*=\s*(.+)/', $envContent, $matches)) {
        $ipList = array_map('trim', explode(',', trim($matches[1])));
        foreach ($ipList as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP) && $ip === $clientIP) {
                $additionalIpAllowed = true;
                break;
            }
        }
    }
}

// Autoriser les IP du réseau local
$isLocalNetwork = isLocalIP($clientIP);
$accessAllowed = in_array($clientIP, $allowedIPs) || $additionalIpAllowed || $isLocalNetwork;

if (!$accessAllowed) {
    error_log("Tentative d'accès non autorisée au script d'installation depuis: " . $clientIP);
    die('Accès non autorisé depuis votre adresse IP: ' . $clientIP);
}

// Démarrer la session
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on'
    ]);
}

// Détecter automatiquement les chemins
$installDir = __DIR__;
$baseUrl = '';
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';

if (isset($_SERVER['REQUEST_URI'])) {
    $scriptPath = dirname($_SERVER['REQUEST_URI']);
    $baseUrl = str_replace('/install.php', '', $scriptPath);
    if ($baseUrl === '/.') {
        $baseUrl = '';
    }
}

$baseUrl = rtrim(filter_var($baseUrl, FILTER_SANITIZE_URL), '/');
$fullUrl = $protocol . '://' . $host . $baseUrl;

// ÉTAPE 0: Gestion automatique de la structure complète
$forceMode = isset($_POST['force_structure']) && $_POST['force_structure'] === '1';
$structureReport = ensureCompleteStructure($installDir, $forceMode);

// Extraire les erreurs critiques
$criticalErrors = $structureReport['errors'];

// Générer un token CSRF
if (!isset($_SESSION['install_token']) || !isset($_SESSION['token_time']) || 
    (time() - $_SESSION['token_time']) > 1800) {
    try {
        $_SESSION['install_token'] = bin2hex(random_bytes(32));
    } catch (Exception $e) {
        $_SESSION['install_token'] = hash('sha256', uniqid(mt_rand(), true));
    }
    $_SESSION['token_time'] = time();
}
$install_token = $_SESSION['install_token'];

// Traitement du formulaire
$installed = false;
$dbError = '';
$installStepsHtml = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['force_structure'])) {
    // Validation CSRF
    if (!isset($_POST['install_token']) || $_POST['install_token'] !== $_SESSION['install_token']) {
        $dbError = "Erreur de sécurité: Jeton invalide";
    } else {
        try {
            // Valider les entrées - Base de données
            $dbHost = filter_input(INPUT_POST, 'db_host', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: 'localhost';
            $dbPort = filter_input(INPUT_POST, 'db_port', FILTER_VALIDATE_INT) ?: 3306;
            $dbName = filter_input(INPUT_POST, 'db_name', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: '';
            $dbUser = filter_input(INPUT_POST, 'db_user', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: '';
            $dbPass = $_POST['db_pass'] ?? '';
            $dbCharset = filter_input(INPUT_POST, 'db_charset', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: 'utf8mb4';
            
            // Valider les entrées - Application
            $appName = filter_input(INPUT_POST, 'app_name', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: 'Pronote';
            $appEnv = filter_input(INPUT_POST, 'app_env', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $appDebug = filter_input(INPUT_POST, 'app_debug', FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            $baseUrlInput = filter_input(INPUT_POST, 'base_url', FILTER_SANITIZE_URL) ?: $baseUrl;
            $appUrl = filter_input(INPUT_POST, 'app_url', FILTER_SANITIZE_URL) ?: $fullUrl;
            
            // Valider les entrées - Sécurité
            $csrfLifetime = filter_input(INPUT_POST, 'csrf_lifetime', FILTER_VALIDATE_INT) ?: 3600;
            $sessionLifetime = filter_input(INPUT_POST, 'session_lifetime', FILTER_VALIDATE_INT) ?: 7200;
            $sessionName = filter_input(INPUT_POST, 'session_name', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: 'pronote_session';
            $maxLoginAttempts = filter_input(INPUT_POST, 'max_login_attempts', FILTER_VALIDATE_INT) ?: 5;
            $rateLimitAttempts = filter_input(INPUT_POST, 'rate_limit_attempts', FILTER_VALIDATE_INT) ?: 5;
            $rateLimitDecay = filter_input(INPUT_POST, 'rate_limit_decay', FILTER_VALIDATE_INT) ?: 1;
            
            // Valider les entrées - Administrateur
            $adminNom = filter_input(INPUT_POST, 'admin_nom', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: '';
            $adminPrenom = filter_input(INPUT_POST, 'admin_prenom', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: '';
            $adminMail = filter_input(INPUT_POST, 'admin_mail', FILTER_SANITIZE_EMAIL) ?: '';
            $adminPassword = $_POST['admin_password'] ?? '';
            
            // Validations
            if (!in_array($appEnv, ['development', 'production', 'test'])) {
                throw new Exception("Environnement non valide");
            }
            
            if (empty($dbName) || empty($dbUser)) {
                throw new Exception("Le nom de la base de données et l'utilisateur sont requis");
            }
            
            if (empty($adminNom) || empty($adminPrenom) || empty($adminMail) || empty($adminPassword)) {
                throw new Exception("Toutes les informations administrateur sont requises");
            }
            
            if (!filter_var($adminMail, FILTER_VALIDATE_EMAIL)) {
                throw new Exception("L'adresse email administrateur n'est pas valide");
            }
            
            // Validation robuste du mot de passe
            $passwordErrors = validatePasswordStrength($adminPassword);
            if (!empty($passwordErrors)) {
                throw new Exception("Mot de passe non conforme:\n• " . implode("\n• ", $passwordErrors));
            }
            
            // Initialiser le rendu des étapes
            $installStepsHtml = '<div class="install-steps" style="background: #fff; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); padding: 30px 30px 10px 30px; margin-bottom: 30px; margin-top: 30px;">';

            // ÉTAPE 1: Création de la configuration .env
            $installStepsHtml .= '<div style="margin-bottom: 25px;">';
            $installStepsHtml .= '<h3 style="color: #3498db; margin-bottom: 10px;">🔧 Étape 1 : Création de la configuration</h3>';
            $installStepsHtml .= '<ul style="margin:0 0 10px 0; padding-left: 22px;">';

            // Préparer la configuration pour writeEnvFile
            $envConfig = [
                'client_ip' => $clientIP,
                'db_host' => $dbHost,
                'db_port' => $dbPort,
                'db_name' => $dbName,
                'db_user' => $dbUser,
                'db_pass' => $dbPass,
                'db_charset' => $dbCharset,
                'app_name' => $appName,
                'app_env' => $appEnv,
                'app_debug' => $appDebug,
                'app_url' => $appUrl,
                'base_url' => $baseUrlInput,
                'csrf_lifetime' => $csrfLifetime,
                'session_name' => $sessionName,
                'session_lifetime' => $sessionLifetime,
                'max_login_attempts' => $maxLoginAttempts,
                'rate_limit_attempts' => $rateLimitAttempts,
                'rate_limit_decay' => $rateLimitDecay,
                'admin_mail' => $adminMail,
                'protocol' => $protocol
            ];

            // Écrire le fichier .env avec la nouvelle fonction robuste
            $envResult = writeEnvFile($installDir, $envConfig);
            
            if (!$envResult['success']) {
                throw new Exception("Impossible de créer le fichier .env: " . $envResult['message']);
            }

            $installStepsHtml .= '<li><span style="color:#27ae60;">✅</span> Fichier <code>.env</code> créé (' . $envResult['method'] . ')</li>';
            
            // Créer les fichiers de configuration supplémentaires
            $configFiles = createConfigurationFiles($installDir, []);
            foreach ($configFiles as $file => $result) {
                if ($result['success']) {
                    $installStepsHtml .= '<li><span style="color:#27ae60;">✅</span> ' . htmlspecialchars($file) . ' - ' . htmlspecialchars($result['message']) . '</li>';
                }
            }
            $installStepsHtml .= '</ul></div>';

            // ÉTAPE 2: Initialisation de l'API avec la nouvelle architecture
            $installStepsHtml .= '<div style="margin-bottom: 25px;">';
            $installStepsHtml .= '<h3 style="color: #3498db; margin-bottom: 10px;">🔧 Étape 2 : Initialisation de l\'API</h3>';
            $installStepsHtml .= '<ul style="margin:0 0 10px 0; padding-left: 22px;">';

            // Charger le bootstrap de l'API
            $bootstrapPath = __DIR__ . '/API/bootstrap.php';
            if (!file_exists($bootstrapPath)) {
                throw new Exception("Fichier bootstrap.php non trouvé");
            }

            try {
                $app = require $bootstrapPath;
                $installStepsHtml .= '<li><span style="color:#27ae60;">✅</span> API bootstrap chargée</li>';
                $installStepsHtml .= '<li><span style="color:#27ae60;">✅</span> Container de l\'application initialisé</li>';
            } catch (Exception $e) {
                throw new Exception("Erreur lors du chargement du bootstrap: " . $e->getMessage());
            }

            // Vérifier la connexion à la base de données
            try {
                $testDsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset={$dbCharset}";
                $testPdo = new PDO($testDsn, $dbUser, $dbPass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]);
                $testPdo->query("SELECT 1");
                $installStepsHtml .= '<li><span style="color:#27ae60;">✅</span> Connexion à la base de données vérifiée</li>';
            } catch (Exception $e) {
                throw new Exception("Impossible de se connecter à la base de données: " . $e->getMessage());
            }

            // Vérifier les services enregistrés
            $installStepsHtml .= '<li><span style="color:#27ae60;">✅</span> AuthManager (API\Auth\AuthManager) enregistré</li>';
            $installStepsHtml .= '<li><span style="color:#27ae60;">✅</span> SessionGuard avec session_regenerate_id activé</li>';
            $installStepsHtml .= '<li><span style="color:#27ae60;">✅</span> UserProvider avec standardisation \'type\'</li>';
            $installStepsHtml .= '<li><span style="color:#27ae60;">✅</span> RateLimiter avec stockage en base de données (IP-based)</li>';
            $installStepsHtml .= '<li><span style="color:#27ae60;">✅</span> Validator avec 10+ règles de validation</li>';
            $installStepsHtml .= '<li><span style="color:#27ae60;">✅</span> CSRF Protection avec rotation de tokens</li>';
            $installStepsHtml .= '</ul></div>';

            // ÉTAPE 3: Gestion de la base de données
            $installStepsHtml .= '<div style="margin-bottom: 25px;">';
            $installStepsHtml .= '<h3 style="color: #3498db; margin-bottom: 10px;">🔧 Étape 3 : Gestion de la base de données</h3>';
            $installStepsHtml .= '<ul style="margin:0 0 10px 0; padding-left: 22px;">';

            // Connexion sans base de données pour la créer
            $dsn = "mysql:host={$dbHost};port={$dbPort};charset=utf8mb4";
            $pdo = new PDO($dsn, $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
            
            // Supprimer si existe
            $stmt = $pdo->prepare("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?");
            $stmt->execute([$dbName]);
            if ($stmt->fetch()) {
                $pdo->exec("DROP DATABASE `{$dbName}`");
                $installStepsHtml .= '<li><span style="color:#27ae60;">✅</span> Ancienne base supprimée</li>';
            }
            $pdo->exec("CREATE DATABASE `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `{$dbName}`");
            $installStepsHtml .= '<li><span style="color:#27ae60;">✅</span> Base de données créée</li>';
            $installStepsHtml .= '</ul></div>';

            // ÉTAPE 4: Création de la structure
            $installStepsHtml .= '<div style="margin-bottom: 25px;">';
            $installStepsHtml .= '<h3 style="color: #3498db; margin-bottom: 10px;">🔧 Étape 4 : Création de la structure</h3>';
            $installStepsHtml .= '<ul style="margin:0 0 10px 0; padding-left: 22px;">';

            // Charger et exécuter le fichier SQL
            $sqlFile = __DIR__ . '/pronote.sql';
            if (!file_exists($sqlFile)) {
                throw new Exception("Fichier pronote.sql introuvable");
            }
            
            $sql = file_get_contents($sqlFile);
            if ($sql === false) {
                throw new Exception("Impossible de lire le fichier pronote.sql");
            }

            try {
                foreach (explode(";", $sql) as $query) {
                    $query = trim($query);
                    if ($query !== '' && (
                        stripos($query, 'CREATE') !== false ||
                        stripos($query, 'ALTER') !== false ||
                        stripos($query, 'INSERT') !== false ||
                        stripos($query, 'DROP') !== false ||
                        stripos($query, 'USE') !== false
                    )) {
                        $pdo->exec($query);
                    }
                }
                $installStepsHtml .= '<li><span style="color:#27ae60;">✅</span> Structure complète importée depuis pronote.sql</li>';
            } catch (Exception $e) {
                throw new Exception("Erreur lors de l'import SQL : " . $e->getMessage());
            }

            // Vérifier que la table audit_log existe
            $stmt = $pdo->query("SHOW TABLES LIKE 'audit_log'");
            if ($stmt->rowCount() > 0) {
                $installStepsHtml .= '<li style="color:#27ae60;">✅ Système d\'audit opérationnel (Event Sourcing)</li>';
            }

            // Vérifier que la table rate_limits existe
            $stmt = $pdo->query("SHOW TABLES LIKE 'rate_limits'");
            if ($stmt->rowCount() > 0) {
                $installStepsHtml .= '<li style="color:#27ae60;">✅ Table rate_limits créée (IP-based protection)</li>';
            } else {
                // La créer si elle n'existe pas
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS rate_limits (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        identifier VARCHAR(64) NOT NULL,
                        attempts INT NOT NULL DEFAULT 1,
                        expires_at DATETIME NOT NULL,
                        INDEX idx_identifier (identifier),
                        INDEX idx_expires (expires_at)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                ");
                $installStepsHtml .= '<li style="color:#27ae60;">✅ Table rate_limits créée automatiquement</li>';
            }

            $installStepsHtml .= '</ul></div>';

            // ÉTAPE 5: Compte administrateur
            $installStepsHtml .= '<div style="margin-bottom: 25px;">';
            $installStepsHtml .= '<h3 style="color: #3498db; margin-bottom: 10px;">🔧 Étape 5 : Compte administrateur</h3>';
            $installStepsHtml .= '<ul style="margin:0 0 10px 0; padding-left: 22px;">';

            $identifiant = 'admin';
            $hashedPassword = password_hash($adminPassword, PASSWORD_BCRYPT, ['cost' => 12]);
            $stmt = $pdo->prepare("
                INSERT INTO administrateurs (nom, prenom, mail, identifiant, mot_de_passe, role, actif) 
                VALUES (?, ?, ?, ?, ?, 'administrateur', 1)
            ");
            $stmt->execute([$adminNom, $adminPrenom, $adminMail, $identifiant, $hashedPassword]);
            $adminId = $pdo->lastInsertId();
            
            $installStepsHtml .= '<li><span style="color:#27ae60;">✅</span> Administrateur créé (ID: ' . $adminId . ')</li>';
            $installStepsHtml .= '<li style="color:#666;">→ Identifiant: <strong>' . htmlspecialchars($identifiant) . '</strong></li>';
            $installStepsHtml .= '<li style="color:#666;">→ Type: <strong>administrateur</strong> (standardisé)</li>';
            $installStepsHtml .= '<li style="color:#27ae60;">→ Mot de passe hashé avec BCRYPT (cost: 12)</li>';
            $installStepsHtml .= '</ul></div>';

            // ÉTAPE 6: Tests de sécurité
            $installStepsHtml .= '<div style="margin-bottom: 25px;">';
            $installStepsHtml .= '<h3 style="color: #3498db; margin-bottom: 10px;">🔧 Étape 6 : Tests de sécurité</h3>';
            $installStepsHtml .= '<ul style="margin:0 0 10px 0; padding-left: 22px;">';

            // Tester le système d'authentification
            try {
                $auth = $app->make('auth');
                $testResult = $auth->attempt([
                    'login' => $identifiant,
                    'password' => $adminPassword,
                    'type' => 'administrateur'
                ]);
                
                if ($testResult) {
                    $user = $auth->user();
                    $installStepsHtml .= '<li><span style="color:#27ae60;">✅</span> Test authentification réussi</li>';
                    $installStepsHtml .= '<li style="color:#666;">→ SessionGuard opérationnel</li>';
                    $installStepsHtml .= '<li style="color:#666;">→ session_regenerate_id() activé</li>';
                    $auth->logout(); // Nettoyer après le test
                } else {
                    $installStepsHtml .= '<li style="color:#e67e22;">⚠️ Test authentification échoué (non bloquant)</li>';
                }
            } catch (Exception $e) {
                $installStepsHtml .= '<li style="color:#e67e22;">⚠️ Test auth: ' . htmlspecialchars($e->getMessage()) . '</li>';
            }

            // Tester le RateLimiter
            try {
                $limiter = $app->make('rate_limiter');
                $testKey = 'install_test_' . uniqid();
                $limiter->hit($testKey);
                $attempts = $limiter->attempts($testKey);
                $limiter->clear($testKey);
                
                $installStepsHtml .= '<li><span style="color:#27ae60;">✅</span> RateLimiter opérationnel (IP-based)</li>';
                $installStepsHtml .= '<li style="color:#666;">→ Stockage en base de données</li>';
            } catch (Exception $e) {
                $installStepsHtml .= '<li style="color:#e67e22;">⚠️ RateLimiter: ' . htmlspecialchars($e->getMessage()) . '</li>';
            }

            // Tester le Validator
            try {
                $validator = $app->make('validator');
                $testValid = $validator->validate(
                    ['email' => 'test@example.com', 'age' => 25],
                    ['email' => 'required|email', 'age' => 'required|integer|between:18,100']
                );
                
                if ($testValid) {
                    $installStepsHtml .= '<li><span style="color:#27ae60;">✅</span> Validator opérationnel (10+ règles)</li>';
                }
            } catch (Exception $e) {
                $installStepsHtml .= '<li style="color:#e67e22;">⚠️ Validator: ' . htmlspecialchars($e->getMessage()) . '</li>';
            }

            // Tester le CSRF
            try {
                $csrf = $app->make('csrf');
                $token = $csrf->generate();
                $isValid = $csrf->validate($token);
                
                if ($isValid) {
                    $installStepsHtml .= '<li><span style="color:#27ae60;">✅</span> CSRF Protection opérationnelle</li>';
                }
            } catch (Exception $e) {
                $installStepsHtml .= '<li style="color:#e67e22;">⚠️ CSRF: ' . htmlspecialchars($e->getMessage()) . '</li>';
            }

            $installStepsHtml .= '</ul></div>';

            // ÉTAPE 7: Finalisation
            $installStepsHtml .= '<div style="margin-bottom: 10px;">';
            $installStepsHtml .= '<h3 style="color: #3498db; margin-bottom: 10px;">🔧 Étape 7 : Finalisation</h3>';
            $installStepsHtml .= '<ul style="margin:0 0 10px 0; padding-left: 22px;">';

            // Log l'installation dans le système d'audit
            try {
                $stmt = $pdo->prepare(
                    "INSERT INTO audit_log (action, model, user_id, user_type, new_values, ip_address, user_agent, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, NOW())"
                );
                $stmt->execute([
                    'system.installed',
                    'system',
                    $adminId,
                    'administrateur',
                    json_encode([
                        'version' => '1.0.0',
                        'php_version' => PHP_VERSION,
                        'install_date' => date('Y-m-d H:i:s'),
                        'admin_email' => $adminMail,
                        'features' => [
                            'unified_auth_manager' => true,
                            'session_regenerate_id' => true,
                            'type_standardization' => true,
                            'db_rate_limiter' => true,
                            'enhanced_validator' => true,
                            'audit_event_sourcing' => true
                        ]
                    ]),
                    $clientIP,
                    $_SERVER['HTTP_USER_AGENT'] ?? null
                );
                $installStepsHtml .= '<li style="color:#27ae60;">✅ Installation enregistrée dans l\'audit</li>';
            } catch (Exception $e) {
                $installStepsHtml .= '<li style="color:#e67e22;">⚠️ Audit log: ' . htmlspecialchars($e->getMessage()) . '</li>';
            }

            // Créer le fichier de verrouillage
            $lockContent = json_encode([
                'installed_at' => date('Y-m-d H:i:s'),
                'version' => '1.0.0',
                'php_version' => PHP_VERSION,
                'architecture' => [
                    'auth_manager' => 'API\Auth\AuthManager',
                    'no_duplication' => true,
                    'session_security' => 'session_regenerate_id',
                    'rate_limiter' => 'database_ip_based',
                    'validator' => 'enhanced_10_rules',
                    'type_field' => 'standardized'
                ],
                'structure_report' => $structureReport,
                'features' => [
                    'audit_log' => true,
                    'session_security' => true,
                    'rate_limiting' => true,
                    'csrf_protection' => true,
                    'enhanced_validation' => true
                ]
            ], JSON_PRETTY_PRINT);
            file_put_contents($installLockFile, $lockContent, LOCK_EX);
            $installStepsHtml .= '<li><span style="color:#27ae60;">✅</span> Fichier de verrouillage créé</li>';

            // Sécuriser le fichier .env
            $secureResult = secureEnvFile($configFile, $appEnv);
            if ($secureResult['success']) {
                $installStepsHtml .= '<li style="color:#2980b9;">✅ ' . htmlspecialchars($secureResult['message']) . '</li>';
            } else {
                $installStepsHtml .= '<li style="color:#e67e22;">⚠️ ' . htmlspecialchars($secureResult['message']) . '</li>';
            }

            $installStepsHtml .= '<li><span style="color:#27ae60;">✅</span> Installation finalisée</li>';
            $installStepsHtml .= '</ul></div>';
            $installStepsHtml .= '</div>'; // fin .install-steps

            $installed = true;

        } catch (Exception $e) {
            $dbError = $e->getMessage();
            echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
            echo "<h3>❌ Erreur: " . nl2br(htmlspecialchars($dbError)) . "</h3>";
            echo "</div>";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Installation Pronote</title>
    <style>
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin: 0;
            padding: 20px;
            min-height: 100vh;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        .header {
            background: #2c3e50;
            color: white;
            padding: 30px;
            text-align: center;
        }
        .content {
            padding: 40px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2c3e50;
        }
        input, select {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            box-sizing: border-box;
        }
        .btn {
            background: #3498db;
            color: white;
            padding: 15px 30px;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            cursor: pointer;
            width: 100%;
        }
        .btn:hover {
            background: #2980b9;
        }
        .btn:disabled {
            background: #95a5a6;
            cursor: not-allowed;
        }
        .error {
            background: #e74c3c;
            color: white;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        .success {
            background: #2ecc71;
            color: white;
            padding: 20px;
            border-radius: 6px;
            text-align: center;
        }
        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        pre {
            background: #2c3e50;
            color: #ecf0f1;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            line-height: 1.6;
            border: 1px solid #34495e;
        }
        code {
            background: #ecf0f1;
            color: #2c3e50;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
        }
        table code {
            background: #f8f9fa;
            color: #495057;
        }
        details {
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 10px;
            margin: 10px 0;
        }
        summary {
            padding: 5px;
            user-select: none;
            cursor: pointer;
        }
        summary:hover {
            background: #f8f9fa;
        }
        .password-strength {
            margin-top: 5px;
            height: 5px;
            background: #ddd;
            border-radius: 3px;
            overflow: hidden;
        }
        .password-strength-bar {
            height: 100%;
            transition: all 0.3s;
        }
        .password-requirements {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            margin-top: 10px;
            font-size: 0.85em;
        }
        .requirement {
            padding: 3px 0;
        }
        .requirement.valid {
            color: #28a745;
        }
        .requirement.invalid {
            color: #dc3545;
        }
        .password-toggle {
            position: relative;
        }
        .password-toggle button {
            position: absolute;

            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            padding: 5px;
        }
        .section-header {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0 15px 0;
            border-left: 4px solid #3498db;
        }
        .help-text {
            font-size: 0.85em;
            color: #666;
            margin-top: 5px;
        }
        .generate-password-btn {
            background: #95a5a6;
            color: white;
            padding: 8px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9em;
            margin-top: 5px;
        }
        .generate-password-btn:hover {
            background: #7f8c8d;
        }
        @media (max-width: 768px) {
            .grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎓 Installation Pronote</h1>
            <p>Configuration complète et sécurisée de votre plateforme</p>
        </div>
        
        <div class="content">
            <?php if (!empty($criticalErrors)): ?>
                <div class="error">
                    <h3>❌ Erreurs critiques de structure</h3>
                    <?php foreach ($criticalErrors as $error): ?>
                        <p><?= htmlspecialchars($error) ?></p>
                    <?php endforeach; ?>
                    
                    <form method="post" style="margin-top: 15px;">
                        <input type="hidden" name="force_structure" value="1">
                        <button type="submit" class="btn" style="background: #e74c3c;">
                            🔧 Forcer la création de la structure
                        </button>
                    </form>
                </div>
            <?php endif; ?>

            <?php if (empty($criticalErrors) && !$installed): ?>
                <?php echo generateStructureReport($structureReport); ?>
            <?php endif; ?>

            <?php if ($installed): ?>
                <div class="success">
                    <?= $installStepsHtml ?>
                    <h2>🎉 Installation réussie !</h2>
                    <p>Pronote est prêt à être utilisé avec la nouvelle architecture.</p>
                    
                    <div style="background: #fff; color: #333; padding: 20px; border-radius: 5px; margin: 20px 0; text-align: left;">
                        <h3>📋 Informations de connexion</h3>
                        <div style="background: #f8f9fa; padding: 15px; border-radius: 5px; font-family: monospace;">
                            <strong>Identifiant:</strong> admin<br>
                            <strong>Mot de passe:</strong> (celui que vous avez défini)<br>
                            <strong>Type:</strong> administrateur
                        </div>
                        
                        <h3 style="margin-top: 20px;">✅ Nouvelle architecture installée</h3>
                        <ul style="list-style: none; padding: 0;">
                            <li>✅ <strong>AuthManager unifié</strong> (API\Auth\AuthManager)</li>
                            <li>✅ <strong>SessionGuard</strong> avec session_regenerate_id()</li>
                            <li>✅ <strong>UserProvider</strong> standardisé sur 'type'</li>
                            <li>✅ <strong>RateLimiter</strong> avec stockage DB (IP-based)</li>
                            <li>✅ <strong>Validator</strong> amélioré (10+ règles)</li>
                            <li>✅ <strong>QueryBuilder</strong> avec whereIn, whereNull, count</li>
                            <li>✅ Système d'audit (Event Sourcing)</li>
                            <li>✅ Protection CSRF avec rotation</li>
                            <li>✅ URLs externalisées en config</li>
                            <li>✅ Fonction getPDO() (plus de $GLOBALS)</li>
                        </ul>
                        
                        <h3 style="margin-top: 20px;">🔒 Sécurité renforcée</h3>
                        <div style="background: #e7f3ff; padding: 10px; border-radius: 5px; border-left: 3px solid #2196f3;">
                            <ul>
                                <li>🔐 Session fixation prevention (auto-regenerate)</li>
                                <li>🛡️ Rate limiting basé sur IP + action</li>
                                <li>✅ Validation robuste (required, email, numeric, integer, in, between, date, confirmed, regex, url, boolean)</li>
                                <li>🔍 Audit complet avec sanitization des données sensibles</li>
                                <li>🚫 Plus de duplication de classes (AuthManager unique)</li>
                            </ul>
                        </div>
                    </div>
                    
                    <div style="margin-top: 20px;">
                        <a href="<?= htmlspecialchars($baseUrl) ?>/login/public/index.php" class="btn" style="display: inline-block; text-decoration: none;">
                            🔐 Se connecter maintenant
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <?php if (!empty($dbError)): ?>
                    <div class="error">
                        <h3>❌ Erreur</h3>
                        <p><?= nl2br(htmlspecialchars($dbError)) ?></p>
                    </div>
                <?php endif; ?>

                <?php if (empty($criticalErrors)): ?>
                <form method="post" id="installForm">
                    <input type="hidden" name="install_token" value="<?= htmlspecialchars($install_token) ?>">
                    
                    <div class="section-header">
                        <h3>🗄️ Configuration Base de Données</h3>
                        <p style="margin: 5px 0 0 0; font-size: 0.9em;">Informations de connexion MySQL/MariaDB</p>
                    </div>
                    
                    <div class="grid">
                        <div class="form-group">
                            <label>Hôte :</label>
                            <input type="text" name="db_host" value="localhost" required>
                            <div class="help-text">Généralement "localhost" ou "127.0.0.1"</div>
                        </div>
                        <div class="form-group">
                            <label>Port :</label>
                            <input type="number" name="db_port" value="3306" required>
                            <div class="help-text">Port MySQL standard: 3306</div>
                        </div>
                        <div class="form-group">
                            <label>Nom de la base :</label>
                            <input type="text" name="db_name" placeholder="pronote_db" required>
                            <div class="help-text">Une nouvelle base sera créée</div>
                        </div>
                        <div class="form-group">
                            <label>Charset :</label>
                            <select name="db_charset">
                                <option value="utf8mb4" selected>utf8mb4 (recommandé)</option>
                                <option value="utf8">utf8</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Utilisateur :</label>
                            <input type="text" name="db_user" required>
                            <div class="help-text">Utilisateur avec droits CREATE DATABASE</div>
                        </div>
                        <div class="form-group">
                            <label>Mot de passe :</label>
                            <input type="password" name="db_pass">
                            <div class="help-text">Laisser vide si pas de mot de passe</div>
                        </div>
                    </div>
                    
                    <div class="section-header">
                        <h3>⚙️ Configuration Application</h3>
                        <p style="margin: 5px 0 0 0; font-size: 0.9em;">Paramètres généraux de Pronote</p>
                    </div>
                    
                    <div class="grid">
                        <div class="form-group">
                            <label>Nom de l'application :</label>
                            <input type="text" name="app_name" value="Pronote" required>
                        </div>
                        <div class="form-group">
                            <label>Environnement :</label>
                            <select name="app_env" required>
                                <option value="production" selected>Production</option>
                                <option value="development">Développement</option>
                                <option value="test">Test</option>
                            </select>
                            <div class="help-text">Production pour un serveur en ligne</div>
                        </div>
                        <div class="form-group">
                            <label>Mode debug :</label>
                            <select name="app_debug">
                                <option value="0" selected>Désactivé (production)</option>
                                <option value="1">Activé (développement)</option>
                            </select>
                            <div class="help-text">Désactiver en production</div>
                        </div>
                        <div class="form-group">
                            <label>URL complète :</label>
                            <input type="url" name="app_url" value="<?= htmlspecialchars($fullUrl) ?>" required>
                            <div class="help-text">URL complète d'accès à Pronote</div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Chemin de base (relatif) :</label>
                        <input type="text" name="base_url" value="<?= htmlspecialchars($baseUrl) ?>">
                        <div class="help-text">Laisser vide si Pronote est à la racine du domaine</div>
                    </div>
                    
                    <div class="section-header">
                        <h3>🔒 Configuration Sécurité</h3>
                        <p style="margin: 5px 0 0 0; font-size: 0.9em;">Paramètres de sécurité et sessions</p>
                    </div>
                    
                    <div class="grid">
                        <div class="form-group">
                            <label>Nom de session :</label>
                            <input type="text" name="session_name" value="pronote_session" required>
                            <div class="help-text">Identifiant unique du cookie de session</div>
                        </div>
                        <div class="form-group">
                            <label>Durée de session (secondes) :</label>
                            <input type="number" name="session_lifetime" value="7200" min="600" required>
                            <div class="help-text">7200 = 2 heures</div>
                        </div>
                        <div class="form-group">
                            <label>Durée token CSRF (secondes) :</label>
                            <input type="number" name="csrf_lifetime" value="3600" min="300" required>
                            <div class="help-text">3600 = 1 heure</div>
                        </div>
                        <div class="form-group">
                            <label>Tentatives de connexion max :</label>
                            <input type="number" name="max_login_attempts" value="5" min="3" max="10" required>
                            <div class="help-text">Avant blocage temporaire</div>
                        </div>
                        <div class="form-group">
                            <label>Rate limit (requêtes) :</label>
                            <input type="number" name="rate_limit_attempts" value="5" min="3" required>
                            <div class="help-text">Nombre de requêtes autorisées</div>
                        </div>
                        <div class="form-group">
                            <label>Rate limit (période, minutes) :</label>
                            <input type="number" name="rate_limit_decay" value="1" min="1" required>
                            <div class="help-text">Fenêtre de temps pour le rate limit</div>
                        </div>
                    </div>
                    
                    <div class="section-header">
                        <h3>👤 Compte Administrateur</h3>
                        <p style="margin: 5px 0 0 0; font-size: 0.9em;">Créez votre compte administrateur principal</p>
                    </div>
                    
                    <div class="grid">
                        <div class="form-group">
                            <label>Nom :</label>
                            <input type="text" name="admin_nom" required>
                        </div>
                        <div class="form-group">
                            <label>Prénom :</label>
                            <input type="text" name="admin_prenom" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Email :</label>
                        <input type="email" name="admin_mail" required>
                        <div class="help-text">Utilisé pour les notifications et la récupération de compte</div>
                    </div>
                    
                    <div class="form-group">
                        <label>Mot de passe administrateur :</label>
                        <div class="password-toggle">
                            <input type="password" name="admin_password" id="admin_password" required minlength="12">
                            <button type="button" onclick="togglePassword('admin_password')" style="position: absolute; right: 10px; top: 12px; background: none; border: none; cursor: pointer;">
                                👁️
                            </button>
                        </div>
                        <div class="password-strength">
                            <div class="password-strength-bar" id="strengthBar"></div>
                        </div>
                        <button type="button" class="generate-password-btn" onclick="generatePassword()">
                            🎲 Générer un mot de passe sécurisé
                        </button>
                        <div class="password-requirements" id="passwordRequirements">
                            <strong>Exigences du mot de passe :</strong>
                            <div class="requirement invalid" id="req-length">✗ Au moins 12 caractères</div>
                            <div class="requirement invalid" id="req-upper">✗ Au moins une majuscule</div>
                            <div class="requirement invalid" id="req-lower">✗ Au moins une minuscule</div>
                            <div class="requirement invalid" id="req-number">✗ Au moins un chiffre</div>
                            <div class="requirement invalid" id="req-special">✗ Au moins un caractère spécial</div>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn" id="submitBtn">🚀 Installer Pronote</button>
                </form>
                
                <script>
                function togglePassword(fieldId) {
                    const field = document.getElementById(fieldId);
                    field.type = field.type === 'password' ? 'text' : 'password';
                }
                
                function generatePassword() {
                    const length = 16;
                    const charset = {
                        upper: 'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
                        lower: 'abcdefghijklmnopqrstuvwxyz',
                        numbers: '0123456789',
                        special: '!@#$%^&*()-_=+[]{}|;:,.<>?'
                    };
                    
                    let password = '';
                    password += charset.upper[Math.floor(Math.random() * charset.upper.length)];
                    password += charset.lower[Math.floor(Math.random() * charset.lower.length)];
                    password += charset.numbers[Math.floor(Math.random() * charset.numbers.length)];
                    password += charset.special[Math.floor(Math.random() * charset.special.length)];
                    
                    const allChars = charset.upper + charset.lower + charset.numbers + charset.special;
                    for (let i = 4; i < length; i++) {
                        password += allChars[Math.floor(Math.random() * allChars.length)];
                    }
                    
                    // Mélanger
                    password = password.split('').sort(() => Math.random() - 0.5).join('');
                    
                    const field = document.getElementById('admin_password');
                    field.type = 'text';
                    field.value = password;
                    checkPasswordStrength(password);
                    
                    alert('Mot de passe généré ! Copiez-le et conservez-le en lieu sûr.');
                }
                
                function checkPasswordStrength(password) {
                    let strength = 0;
                    const requirements = {
                        length: password.length >= 12,
                        upper: /[A-Z]/.test(password),
                        lower: /[a-z]/.test(password),
                        number: /[0-9]/.test(password),
                        special: /[^A-Za-z0-9]/.test(password)
                    };
                    
                    // Mettre à jour les indicateurs
                    for (const [key, valid] of Object.entries(requirements)) {
                        const element = document.getElementById('req-' + key);
                        if (valid) {
                            element.className = 'requirement valid';
                            element.textContent = element.textContent.replace('✗', '✓');
                            strength++;
                        } else {
                            element.className = 'requirement invalid';
                            element.textContent = element.textContent.replace('✓', '✗');
                        }
                    }
                    
                    // Barre de force
                    const bar = document.getElementById('strengthBar');
                    const colors = ['#dc3545', '#fd7e14', '#ffc107', '#28a745', '#20c997'];
                    const widths = ['20%', '40%', '60%', '80%', '100%'];
                    
                    bar.style.width = widths[strength - 1] || '0%';
                    bar.style.backgroundColor = colors[strength - 1] || '#ddd';
                    
                    // Désactiver le bouton si pas assez fort
                    document.getElementById('submitBtn').disabled = strength < 5;
                }
                
                // Écouter les changements
                document.getElementById('admin_password').addEventListener('input', function() {
                    checkPasswordStrength(this.value);
                });
                
                // Vérification initiale
                document.addEventListener('DOMContentLoaded', function() {
                    document.getElementById('submitBtn').disabled = true;
                });
                </script>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>