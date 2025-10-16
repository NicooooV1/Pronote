<?php
/**
 * Script d'installation de Pronote - VERSION COMPLÈTE
 * Gestion robuste de tous les fichiers et répertoires nécessaires
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
        ];
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
 * Analyse approfondie des problèmes de permissions
 */
function analyzePermissionIssues($installDir) {
    $analysis = [
        'system_info' => [],
        'directory_analysis' => [],
        'solutions' => [],
        'commands' => []
    ];
    
    // 1. Informations système
    $analysis['system_info'] = [
        'os' => PHP_OS,
        'php_version' => PHP_VERSION,
        'web_server' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
        'current_user' => get_current_user(),
        'script_owner' => getScriptOwner(__FILE__),
        'is_root' => function_exists('posix_geteuid') && posix_geteuid() === 0,
        'has_posix' => function_exists('posix_getpwuid'),
        'web_server_user' => getWebServerUser(),
        'install_dir' => $installDir,
        'install_dir_perms' => getDetailedPermissions($installDir)
    ];
    
    // 2. Analyse des répertoires problématiques
    $structure = getRequiredStructure();
    foreach ($structure['directories'] as $dir => $config) {
        $path = $installDir . '/' . $dir;
        $analysis['directory_analysis'][$dir] = analyzeDirectory($path, $config);
    }
    
    // 3. Générer les solutions
    $analysis['solutions'] = generateSolutions($analysis);
    
    // 4. Générer les commandes
    $analysis['commands'] = generateFixCommands($analysis, $installDir);
    
    return $analysis;
}

/**
 * Récupère l'utilisateur du serveur web
 */
function getWebServerUser() {
    if (function_exists('posix_geteuid')) {
        $processUser = posix_getpwuid(posix_geteuid());
        return $processUser['name'] ?? 'unknown';
    }
    
    // Fallback: essayer de détecter depuis les variables d'environnement
    if (isset($_SERVER['USER'])) {
        return $_SERVER['USER'];
    }
    
    // Essayer de détecter depuis le serveur web
    $server = strtolower($_SERVER['SERVER_SOFTWARE'] ?? '');
    if (strpos($server, 'apache') !== false) {
        return 'www-data'; // Debian/Ubuntu
    }
    if (strpos($server, 'nginx') !== false) {
        return 'nginx'; // CentOS/RHEL
    }
    
    return 'www-data'; // Défaut
}

/**
 * Récupère le propriétaire d'un fichier
 */
function getScriptOwner($file) {
    if (!file_exists($file)) {
        return 'unknown';
    }
    
    if (function_exists('posix_getpwuid')) {
        $ownerInfo = posix_getpwuid(fileowner($file));
        return $ownerInfo['name'] ?? 'unknown';
    }
    
    return 'unknown';
}

/**
 * Récupère les permissions détaillées d'un chemin
 */
function getDetailedPermissions($path) {
    if (!file_exists($path)) {
        return [
            'exists' => false,
            'readable' => false,
            'writable' => false,
            'executable' => false,
            'perms' => 'N/A',
            'owner' => 'N/A',
            'group' => 'N/A'
        ];
    }
    
    $info = [
        'exists' => true,
        'readable' => is_readable($path),
        'writable' => is_writable($path),
        'executable' => is_executable($path),
        'perms' => substr(sprintf('%o', fileperms($path)), -4),
        'owner' => 'unknown',
        'group' => 'unknown'
    ];
    
    if (function_exists('posix_getpwuid') && function_exists('posix_getgrgid')) {
        $ownerInfo = posix_getpwuid(fileowner($path));
        $groupInfo = posix_getgrgid(filegroup($path));
        $info['owner'] = $ownerInfo['name'] ?? 'unknown';
        $info['group'] = $groupInfo['name'] ?? 'unknown';
    }
    
    return $info;
}

/**
 * Analyse un répertoire spécifique
 */
function analyzeDirectory($path, $config) {
    $analysis = [
        'path' => $path,
        'exists' => is_dir($path),
        'permissions' => getDetailedPermissions($path),
        'parent_permissions' => getDetailedPermissions(dirname($path)),
        'issues' => [],
        'cause' => null,
        'fix_level' => null
    ];
    
    // Déterminer les problèmes
    if (!$analysis['exists']) {
        $analysis['issues'][] = "Le répertoire n'existe pas";
        $analysis['cause'] = 'missing_directory';
        $analysis['fix_level'] = 'easy';
    } else {
        $perms = $analysis['permissions'];
        
        if (!$perms['writable']) {
            $analysis['issues'][] = "Pas de permission d'écriture";
            
            // Analyser la cause
            if ($perms['owner'] !== getWebServerUser()) {
                $analysis['cause'] = 'wrong_owner';
                $analysis['issues'][] = "Propriétaire incorrect: {$perms['owner']} (devrait être " . getWebServerUser() . ")";
                $analysis['fix_level'] = 'medium';
            } else {
                $analysis['cause'] = 'wrong_permissions';
                $analysis['issues'][] = "Permissions insuffisantes: {$perms['perms']} (requis: " . decoct($config['permissions']) . ")";
                $analysis['fix_level'] = 'easy';
            }
        }
        
        if (!$perms['readable']) {
            $analysis['issues'][] = "Pas de permission de lecture";
            $analysis['fix_level'] = 'medium';
        }
    }
    
    return $analysis;
}

/**
 * Génère les solutions en fonction de l'analyse
 */
function generateSolutions($analysis) {
    $solutions = [];
    
    // Solution 1: Permissions du répertoire d'installation
    $installPerms = $analysis['system_info']['install_dir_perms'];
    if (!$installPerms['writable']) {
        $solutions[] = [
            'priority' => 'critical',
            'title' => 'Le répertoire d\'installation principal n\'est pas accessible en écriture',
            'description' => "Le répertoire {$analysis['system_info']['install_dir']} appartient à {$installPerms['owner']} mais le serveur web s'exécute sous " . getWebServerUser(),
            'action' => 'Changer le propriétaire du répertoire racine'
        ];
    }
    
    // Solution 2: Conflit de propriétaire
    $webUser = $analysis['system_info']['web_server_user'];
    $scriptOwner = $analysis['system_info']['script_owner'];
    
    if ($webUser !== $scriptOwner && $scriptOwner !== 'unknown') {
        $solutions[] = [
            'priority' => 'high',
            'title' => 'Conflit de propriétaire détecté',
            'description' => "Les fichiers appartiennent à {$scriptOwner} mais le serveur web s'exécute sous {$webUser}",
            'action' => 'Uniformiser le propriétaire de tous les fichiers'
        ];
    }
    
    // Solution 3: Permissions strictes
    $hasPermissionIssues = false;
    foreach ($analysis['directory_analysis'] as $dir => $dirAnalysis) {
        if ($dirAnalysis['cause'] === 'wrong_permissions') {
            $hasPermissionIssues = true;
            break;
        }
    }
    
    if ($hasPermissionIssues) {
        $solutions[] = [
            'priority' => 'medium',
            'title' => 'Permissions trop restrictives',
            'description' => 'Certains répertoires ont des permissions qui empêchent l\'écriture',
            'action' => 'Appliquer les permissions recommandées (755 ou 777)'
        ];
    }
    
    // Solution 4: SELinux ou AppArmor
    if (file_exists('/etc/selinux/config')) {
        $solutions[] = [
            'priority' => 'info',
            'title' => 'SELinux peut bloquer les écritures',
            'description' => 'Votre système utilise SELinux qui peut empêcher Apache/Nginx d\'écrire',
            'action' => 'Configurer le contexte SELinux approprié'
        ];
    }
    
    return $solutions;
}

/**
 * Génère les commandes de correction
 */
function generateFixCommands($analysis, $installDir) {
    $commands = array(
        'method1' => array(
            'title' => 'Méthode 1: Changer le propriétaire (RECOMMANDÉ)',
            'description' => 'Change le propriétaire de tous les fichiers pour correspondre à l\'utilisateur du serveur web',
            'requires_root' => true,
            'commands' => array()
        ),
        'method2' => array(
            'title' => 'Méthode 2: Permissions 777 (MOINS SÉCURISÉ)',
            'description' => 'Donne tous les droits à tous les utilisateurs',
            'requires_root' => false,
            'commands' => array()
        ),
        'method3' => array(
            'title' => 'Méthode 3: Ajouter l\'utilisateur au groupe (ALTERNATIVE)',
            'description' => 'Ajoute l\'utilisateur du serveur web au groupe propriétaire',
            'requires_root' => true,
            'commands' => array()
        ),
    );

    $webUser = $analysis['system_info']['web_server_user'] ?? 'www-data';
    $structure = getRequiredStructure();

    // Méthode 1
    $commands['method1']['commands'][] = "cd {$installDir}";
    $commands['method1']['commands'][] = "# Changer le propriétaire de tous les fichiers";
    $commands['method1']['commands'][] = "sudo chown -R {$webUser}:{$webUser} .";
    $commands['method1']['commands'][] = "";
    $commands['method1']['commands'][] = "# Appliquer les permissions correctes";
    foreach ($structure['directories'] as $dir => $config) {
        $perm = decoct($config['permissions']);
        $commands['method1']['commands'][] = "sudo chmod {$perm} {$dir}";
    }

    // Méthode 2
    $commands['method2']['commands'][] = "cd {$installDir}";
    $commands['method2']['commands'][] = "# Donner tous les droits (ATTENTION: moins sécurisé)";
    foreach ($structure['directories'] as $dir => $cfg) {
        if (!empty($cfg['critical'])) {
            $commands['method2']['commands'][] = "chmod -R 777 {$dir}";
        }
    }

    // Méthode 3
    $currentUser = $analysis['system_info']['current_user'] ?? 'current';
    $commands['method3']['commands'][] = "# Ajouter {$webUser} au groupe de l'utilisateur actuel";
    $commands['method3']['commands'][] = "sudo usermod -a -G {$currentUser} {$webUser}";
    $commands['method3']['commands'][] = "";
    $commands['method3']['commands'][] = "# Définir les permissions de groupe";
    $commands['method3']['commands'][] = "cd {$installDir}";
    foreach ($structure['directories'] as $dir => $cfg2) {
        $commands['method3']['commands'][] = "sudo chmod -R 775 {$dir}";
        $commands['method3']['commands'][] = "sudo chgrp -R {$currentUser} {$dir}";
    }
    $commands['method3']['commands'][] = "";
    $commands['method3']['commands'][] = "# Redémarrer le serveur web pour appliquer les changements de groupe";
    $commands['method3']['commands'][] = "sudo systemctl restart apache2  # ou nginx";

    // SELinux (optionnel)
    if (file_exists('/etc/selinux/config')) {
        $commands['selinux'] = array(
            'title' => 'Configuration SELinux (si applicable)',
            'description' => 'Configure le contexte SELinux pour permettre l\'écriture',
            'requires_root' => true,
            'commands' => array(
                "cd {$installDir}",
                "# Autoriser Apache/Nginx à écrire dans ces répertoires",
                "sudo semanage fcontext -a -t httpd_sys_rw_content_t \"{$installDir}(/.*)?\"",
                "sudo restorecon -Rv {$installDir}",
                "",
                "# OU temporairement désactiver SELinux pour tester",
                "sudo setenforce 0  # Temporaire",
                "# Pour désactiver définitivement: éditer /etc/selinux/config"
            )
        );
    }

    return $commands;
}

/**
 * Génère le rapport HTML d'analyse
 */
function generateAnalysisReport($analysis) {
    $html = "<div style='background: #fff3cd; padding: 20px; border-radius: 5px; margin: 20px 0; border-left: 5px solid #ffc107;'>";
    $html .= "<h3>🔍 Analyse détaillée du problème</h3>";
    
    // Informations système
    $html .= "<h4>📋 Informations système</h4>";
    $html .= "<table style='width: 100%; border-collapse: collapse; margin: 10px 0;'>";
    $html .= "<tr><td style='padding: 5px; border: 1px solid #ddd; font-weight: 600;'>Système d'exploitation</td>";
    $html .= "<td style='padding: 5px; border: 1px solid #ddd;'>{$analysis['system_info']['os']}</td></tr>";
    $html .= "<tr><td style='padding: 5px; border: 1px solid #ddd; font-weight: 600;'>Serveur web</td>";
    $html .= "<td style='padding: 5px; border: 1px solid #ddd;'>{$analysis['system_info']['web_server']}</td></tr>";
    $html .= "<tr><td style='padding: 5px; border: 1px solid #ddd; font-weight: 600;'>Utilisateur serveur web</td>";
    $html .= "<td style='padding: 5px; border: 1px solid #ddd;'><code>{$analysis['system_info']['web_server_user']}</code></td></tr>";
    $html .= "<tr><td style='padding: 5px; border: 1px solid #ddd; font-weight: 600;'>Propriétaire des fichiers</td>";
    $html .= "<td style='padding: 5px; border: 1px solid #ddd;'><code>{$analysis['system_info']['script_owner']}</code></td></tr>";
    $html .= "<tr><td style='padding: 5px; border: 1px solid #ddd; font-weight: 600;'>Répertoire d'installation</td>";
    $html .= "<td style='padding: 5px; border: 1px solid #ddd;'><code>{$analysis['system_info']['install_dir']}</code></td></tr>";
    $html .= "</table>";
    
    // Solutions
    if (!empty($analysis['solutions'])) {
        $html .= "<h4>💡 Diagnostic et solutions</h4>";
        foreach ($analysis['solutions'] as $solution) {
            $color = $solution['priority'] === 'critical' ? '#dc3545' : ($solution['priority'] === 'high' ? '#fd7e14' : '#17a2b8');
            $html .= "<div style='background: #f8f9fa; padding: 10px; margin: 10px 0; border-left: 3px solid {$color};'>";
            $html .= "<strong style='color: {$color};'>" . strtoupper($solution['priority']) . ":</strong> ";
            $html .= "<strong>{$solution['title']}</strong><br>";
            $html .= "<small>{$solution['description']}</small><br>";
            $html .= "<em>→ Action: {$solution['action']}</em>";
            $html .= "</div>";
        }
    }
    
    // Commandes de correction
    $html .= "<h4>🛠️ Commandes de correction</h4>";
    foreach ($analysis['commands'] as $method => $info) {
        $html .= "<details style='margin: 10px 0; background: #f8f9fa; padding: 10px; border-radius: 5px;'>";
        $html .= "<summary style='cursor: pointer; font-weight: bold; color: #007bff;'>";
        $html .= $info['title'];
        if ($info['requires_root']) {
            $html .= " <span style='background: #dc3545; color: white; padding: 2px 5px; border-radius: 3px; font-size: 0.8em;'>SUDO REQUIS</span>";
        }
        $html .= "</summary>";
        $html .= "<p style='margin: 10px 0;'><em>{$info['description']}</em></p>";
        $html .= "<pre>" . implode("\n", $info['commands']) . "</pre>";
        $html .= "</details>";
    }
    
    // Diagnostic par répertoire
    $html .= "<details style='margin: 15px 0;'>";
    $html .= "<summary style='cursor: pointer; font-weight: bold;'>🔬 Diagnostic détaillé par répertoire</summary>";
    $html .= "<table style='width: 100%; border-collapse: collapse; margin: 10px 0; font-size: 0.9em;'>";
    $html .= "<tr style='background: #e9ecef;'>";
    $html .= "<th style='padding: 8px; text-align: left; border: 1px solid #dee2e6;'>Répertoire</th>";
    $html .= "<th style='padding: 8px; text-align: left; border: 1px solid #dee2e6;'>Problème</th>";
    $html .= "<th style='padding: 8px; text-align: left; border: 1px solid #dee2e6;'>Cause</th>";
    $html .= "<th style='padding: 8px; text-align: left; border: 1px solid #dee2e6;'>Niveau</th>";
    $html .= "</tr>";
    
    foreach ($analysis['directory_analysis'] as $dir => $dirAnalysis) {
        if (!empty($dirAnalysis['issues'])) {
            $levelColor = $dirAnalysis['fix_level'] === 'easy' ? '#28a745' : ($dirAnalysis['fix_level'] === 'medium' ? '#ffc107' : '#dc3545');
            $html .= "<tr>";
            $html .= "<td style='padding: 8px; border: 1px solid #dee2e6;'><code>{$dir}</code></td>";
            $html .= "<td style='padding: 8px; border: 1px solid #dee2e6;'>" . implode('<br>', $dirAnalysis['issues']) . "</td>";
            $html .= "<td style='padding: 8px; border: 1px solid #dee2e6;'><code>" . ($dirAnalysis['cause'] ?? 'N/A') . "</code></td>";
            $html .= "<td style='padding: 8px; border: 1px solid #dee2e6;'><span style='background: {$levelColor}; color: white; padding: 2px 8px; border-radius: 3px;'>" . strtoupper($dirAnalysis['fix_level'] ?? 'unknown') . "</span></td>";
            $html .= "</tr>";
        }
    }
    $html .= "</table>";
    $html .= "</details>";
    
    $html .= "</div>";
    return $html;
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

$baseUrl = filter_var($baseUrl, FILTER_SANITIZE_URL);
$fullUrl = $protocol . '://' . $host . $baseUrl;

// ÉTAPE 0: Gestion automatique de la structure complète
$forceMode = isset($_POST['force_structure']) && $_POST['force_structure'] === '1';
$structureReport = ensureCompleteStructure($installDir, $forceMode);

// Extraire les erreurs critiques
$criticalErrors = $structureReport['errors'];

// Analyser les problèmes si erreurs critiques
$detailedAnalysis = null;
if (!empty($criticalErrors)) {
    $detailedAnalysis = analyzePermissionIssues($installDir);
}

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
            
            // ÉTAPE 1: Créer la configuration .env
            echo "<div style='background: #d1ecf1; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
            echo "<h3>🔧 Étape 1: Création de la configuration</h3>";
            
            $configFile = $installDir . '/.env';
            $configContent = "# Configuration Pronote - Généré le " . date('Y-m-d H:i:s') . "\n";
            $configContent .= "# ⚠️ NE PAS COMMITTER CE FICHIER DANS GIT\n";
            $configContent .= "# ⚠️ NE PAS PARTAGER CE FICHIER\n\n";
            
            $configContent .= "# ==================================================\n";
            $configContent .= "# SÉCURITÉ INSTALLATION\n";
            $configContent .= "# ==================================================\n";
            $configContent .= "ALLOWED_INSTALL_IP={$clientIP}\n\n";
            
            $configContent .= "# ==================================================\n";
            $configContent .= "# BASE DE DONNÉES\n";
            $configContent .= "# ==================================================\n";
            $configContent .= "DB_HOST={$dbHost}\n";
            $configContent .= "DB_PORT={$dbPort}\n";
            $configContent .= "DB_NAME={$dbName}\n";
            $configContent .= "DB_USER={$dbUser}\n";
            $configContent .= "DB_PASS={$dbPass}\n";
            $configContent .= "DB_CHARSET={$dbCharset}\n\n";
            
            $configContent .= "# ==================================================\n";
            $configContent .= "# APPLICATION\n";
            $configContent .= "# ==================================================\n";
            $configContent .= "APP_NAME=\"{$appName}\"\n";
            $configContent .= "APP_ENV={$appEnv}\n";
            $configContent .= "APP_DEBUG=" . ($appDebug ? 'true' : 'false') . "\n";
            $configContent .= "APP_URL={$appUrl}\n";
            $configContent .= "APP_BASE_PATH={$installDir}\n";
            $configContent .= "BASE_URL=" . rtrim($baseUrlInput, '/') . "\n\n";
            
            $configContent .= "# ==================================================\n";
            $configContent .= "# SÉCURITÉ\n";
            $configContent .= "# ==================================================\n";
            $configContent .= "# Durée de vie des tokens CSRF (en secondes)\n";
            $configContent .= "CSRF_LIFETIME={$csrfLifetime}\n";
            $configContent .= "CSRF_MAX_TOKENS=10\n\n";
            
            $configContent .= "# Configuration des sessions\n";
            $configContent .= "SESSION_NAME={$sessionName}\n";
            $configContent .= "SESSION_LIFETIME={$sessionLifetime}\n";
            $configContent .= "SESSION_SECURE=" . ($protocol === 'https' ? 'true' : 'false') . "\n";
            $configContent .= "SESSION_HTTPONLY=true\n";
            $configContent .= "SESSION_SAMESITE=Lax\n\n";
            
            $configContent .= "# Limitations de connexion\n";
            $configContent .= "MAX_LOGIN_ATTEMPTS={$maxLoginAttempts}\n";
            $configContent .= "LOGIN_LOCKOUT_TIME=900\n\n";
            
            $configContent .= "# Rate limiting\n";
            $configContent .= "RATE_LIMIT_ATTEMPTS={$rateLimitAttempts}\n";
            $configContent .= "RATE_LIMIT_DECAY={$rateLimitDecay}\n\n";
            
            $configContent .= "# ==================================================\n";
            $configContent .= "# CHEMINS\n";
            $configContent .= "# ==================================================\n";
            $configContent .= "LOGS_PATH={$installDir}/API/logs\n";
            $configContent .= "UPLOADS_PATH={$installDir}/uploads\n";
            $configContent .= "TEMP_PATH={$installDir}/temp\n\n";
            
            $configContent .= "# ==================================================\n";
            $configContent .= "# MAIL (à configurer ultérieurement)\n";
            $configContent .= "# ==================================================\n";
            $configContent .= "MAIL_MAILER=smtp\n";
            $configContent .= "MAIL_HOST=\n";
            $configContent .= "MAIL_PORT=587\n";
            $configContent .= "MAIL_USERNAME=\n";
            $configContent .= "MAIL_PASSWORD=\n";
            $configContent .= "MAIL_ENCRYPTION=tls\n";
            $configContent .= "MAIL_FROM_ADDRESS={$adminMail}\n";
            $configContent .= "MAIL_FROM_NAME=\"{$appName}\"\n\n";
            
            $configContent .= "# ==================================================\n";
            $configContent .= "# TIMEZONE\n";
            $configContent .= "# ==================================================\n";
            $configContent .= "APP_TIMEZONE=Europe/Paris\n";
            
            if (@file_put_contents($configFile, $configContent, LOCK_EX) === false) {
                throw new Exception("Impossible d'écrire le fichier .env");
            }
            
            // Protection du .env selon l'environnement
            $chmodSuccess = false;
            $chmodMsg = '';
            if ($appEnv === 'production') {
                // En production, rendre le .env illisible sauf pour le propriétaire
                if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                    // Windows : lecture seule pour l'utilisateur
                    $chmodSuccess = @chmod($configFile, 0600);
                    $chmodMsg = $chmodSuccess
                        ? "Le fichier .env est protégé (lecture seule pour l'utilisateur)."
                        : "Impossible de restreindre les droits du fichier .env (Windows).";
                } else {
                    // Unix/Linux : aucune permission pour les autres
                    $chmodSuccess = @chmod($configFile, 0600) && @chmod($configFile, 0000);
                    $chmodMsg = $chmodSuccess
                        ? "Le fichier .env est protégé (aucune lecture possible par le serveur web)."
                        : "Impossible de restreindre les droits du fichier .env (Linux/Unix).";
                }
            } else {
                // En développement, lecture/écriture pour l'utilisateur uniquement
                $chmodSuccess = @chmod($configFile, 0600);
                $chmodMsg = $chmodSuccess
                    ? "Le fichier .env est protégé (lecture/écriture pour l'utilisateur uniquement)."
                    : "Impossible de restreindre les droits du fichier .env.";
            }

            echo "<p style='color: #2980b9; font-size: 0.95em;'>$chmodMsg</p>";
            
            echo "<p>✅ Fichier .env créé avec toutes les configurations</p>";
            echo "<p style='font-size: 0.9em; color: #666;'>→ Configuration base de données</p>";
            echo "<p style='font-size: 0.9em; color: #666;'>→ Configuration application</p>";
            echo "<p style='font-size: 0.9em; color: #666;'>→ Configuration sécurité</p>";
            echo "<p style='font-size: 0.9em; color: #666;'>→ Configuration chemins</p>";
            
            // Créer les fichiers de configuration supplémentaires
            $configFiles = createConfigurationFiles($installDir, []);
            foreach ($configFiles as $file => $result) {
                if ($result['success']) {
                    echo "<p>✅ {$file} - {$result['message']}</p>";
                }
            }
            
            echo "</div>";
            
            // ÉTAPE 2: Charger l'API avec la nouvelle configuration
            echo "<div style='background: #d1ecf1; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
            echo "<h3>🔧 Étape 2: Initialisation de l'API</h3>";
            
            // Charger le bootstrap de l'API
            $bootstrapPath = __DIR__ . '/API/bootstrap.php';
            if (!file_exists($bootstrapPath)) {
                throw new Exception("Fichier bootstrap.php non trouvé");
            }
            
            $app = require $bootstrapPath;
            echo "<p>✅ API bootstrap chargée</p>";
            
            // Vérifier que les facades sont disponibles
            if (!class_exists('\API\Core\Facades\DB')) {
                throw new Exception("Les facades n'ont pas pu être chargées");
            }
            
            echo "<p>✅ Facades disponibles</p>";
            echo "</div>";
            
            // ÉTAPE 3: Créer/recréer la base de données
            echo "<div style='background: #d1ecf1; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
            echo "<h3>🔧 Étape 3: Gestion de la base de données</h3>";
            
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
                echo "<p>✅ Ancienne base supprimée</p>";
            }
            
            // Créer nouvelle base
            $pdo->exec("CREATE DATABASE `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `{$dbName}`");
            echo "<p>✅ Base de données créée</p>";
            echo "</div>";
            
            // ÉTAPE 4: Créer la structure avec le QueryBuilder
            echo "<div style='background: #d1ecf1; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
            echo "<h3>🔧 Étape 4: Création de la structure</h3>";
            
            createDatabaseStructure($pdo);
            echo "<p>✅ Structure créée</p>";
            
            // Vérifier que la table audit_log existe bien
            $stmt = $pdo->query("SHOW TABLES LIKE 'audit_log'");
            if ($stmt->rowCount() > 0) {
                echo "<p style='color: #28a745;'>✅ Système d'audit opérationnel</p>";
                
                // Compter les colonnes de la table audit_log
                $stmt = $pdo->query("DESCRIBE audit_log");
                $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
                echo "<p style='font-size: 0.9em; color: #666;'>→ Table audit_log: " . count($columns) . " colonnes</p>";
                echo "<p style='font-size: 0.9em; color: #666;'>→ Index créés: idx_action, idx_model, idx_user, idx_created_at</p>";
            } else {
                echo "<p style='color: #e74c3c;'>⚠️ Table audit_log non créée</p>";
            }
            
            echo "</div>";
            
            // ÉTAPE 5: Créer le compte admin
            echo "<div style='background: #d1ecf1; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
            echo "<h3>🔧 Étape 5: Compte administrateur</h3>";
            
            $identifiant = 'admin';
            $hashedPassword = password_hash($adminPassword, PASSWORD_BCRYPT, ['cost' => 12]);
            
            $stmt = $pdo->prepare("
                INSERT INTO administrateurs (nom, prenom, mail, identifiant, mot_de_passe, role, actif) 
                VALUES (?, ?, ?, ?, ?, 'administrateur', 1)
            ");
            
            $stmt->execute([$adminNom, $adminPrenom, $adminMail, $identifiant, $hashedPassword]);
            
            echo "<p>✅ Administrateur créé</p>";
            echo "<p style='font-size: 0.9em; color: #666;'>→ Identifiant: <strong>{$identifiant}</strong></p>";
            echo "<p style='font-size: 0.9em; color: #666;'>→ Nom: {$adminNom} {$adminPrenom}</p>";
            echo "<p style='font-size: 0.9em; color: #666;'>→ Email: {$adminMail}</p>";
            echo "<p style='font-size: 0.9em; color: #28a745;'>→ Mot de passe hashé avec BCRYPT (cost: 12)</p>";
            echo "</div>";
            
            // ÉTAPE 6: Finalisation
            echo "<div style='background: #d1ecf1; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
            echo "<h3>🔧 Étape 6: Finalisation</h3>";
            
            // Log l'installation dans le système d'audit si disponible
            try {
                $stmt = $pdo->prepare(
                    "INSERT INTO audit_log (action, model, user_id, user_type, new_values, ip_address, user_agent, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, NOW())"
                );
                                                                                                                                                                                         $stmt->execute([
                    'system.installed',
                    'system',
                    null,
                    null,
                    json_encode([
                        'version' => '1.0.0',
                        'php_version' => PHP_VERSION,
                        'install_date' => date('Y-m-d H:i:s'),
                        'admin_email' => $adminMail
                    ]),
                    $clientIP,
                    $_SERVER['HTTP_USER_AGENT'] ?? null
                ]);
                echo "<p style='color: #28a745;'>✅ Installation enregistrée dans l'audit</p>";
            } catch (Exception $e) {
                echo "<p style='color: #856404;'>⚠️ Audit log: " . htmlspecialchars($e->getMessage()) . "</p>";
            }
            
            // Créer le fichier lock
            $lockContent = json_encode([
                'installed_at' => date('Y-m-d H:i:s'),
                'version' => '1.0.0',
                'php_version' => PHP_VERSION,
                'structure_report' => $structureReport,
                'features' => [
                    'audit_log' => true,
                    'session_security' => true,
                    'rate_limiting' => true,
                    'csrf_protection' => true
                ]
            ]);
            file_put_contents($installLockFile, $lockContent, LOCK_EX);
            echo "<p>✅ Fichier de verrouillage créé</p>";
            
            // Créer un fichier de logs initial
            $initialLog = $installDir . '/API/logs/' . date('Y-m-d') . '.log';
            $logContent = "[" . date('Y-m-d H:i:s') . "] INFO: Installation complétée avec succès\n";
            $logContent .= "[" . date('Y-m-d H:i:s') . "] INFO: Système d'audit activé\n";
            $logContent .= "[" . date('Y-m-d H:i:s') . "] INFO: Protection CSRF activée\n";
            $logContent .= "[" . date('Y-m-d H:i:s') . "] INFO: Rate limiting configuré\n";
            @file_put_contents($initialLog, $logContent, LOCK_EX);
            echo "<p>✅ Système de logs initialisé</p>";
            
            // Supprimer le fichier fix_permissions.php s'il existe
            $fixPermFile = $installDir . '/fix_permissions.php';
            if (file_exists($fixPermFile)) {
                @unlink($fixPermFile);
                echo "<p>✅ Fichiers temporaires supprimés</p>";
            }
            
            echo "<p>✅ Installation finalisée</p>";
            echo "</div>";
            
            $installed = true;
            
        } catch (Exception $e) {
            $dbError = $e->getMessage();
           

            echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
            echo "<h3>❌ Erreur: " . nl2br(htmlspecialchars($dbError)) . "</h3>";
            echo "</div>";
        }
    }
}

// Fonction de création de structure
function createDatabaseStructure($pdo) {
    // Charger le fichier SQL complet
    $sqlFile = __DIR__ . '/pronote.sql';
    if (!file_exists($sqlFile)) {
        echo "<p style='color: #e74c3c;'>Fichier pronote.sql introuvable !</p>";
        return;
    }
    $sql = file_get_contents($sqlFile);
    if ($sql === false) {
        echo "<p style='color: #e74c3c;'>Impossible de lire le fichier pronote.sql !</p>";
        return;
    }

    // Exécuter chaque requête sans transaction (DDL non supporté dans transaction)
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
        echo "<p style='color: #28a745;'>→ Structure complète importée depuis pronote.sql</p>";
    } catch (Exception $e) {
        echo "<p style='color: #e74c3c;'>Erreur lors de l'import SQL : " . htmlspecialchars($e->getMessage()) . "</p>";
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
        }
        .btn {
            background: #3498db;
            color: white;
            padding: 15px 30px;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            cursor: pointer;
        }
        .btn:hover {
            background: #2980b9;
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
        .advanced-options {
            margin-top: 10px;
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
                
                <?php 
                // Afficher l'analyse détaillée si disponible
                if ($detailedAnalysis !== null) {
                    echo generateAnalysisReport($detailedAnalysis); 
                }
                ?>
                
            <?php endif; ?>

            <?php if (empty($criticalErrors) && !$installed): ?>
                <?php echo generateStructureReport($structureReport); ?>
            <?php endif; ?>

            <?php if ($installed): ?>
                <div class="success">
                    <h2>🎉 Installation réussie !</h2>
                    <p>Pronote est prêt à être utilisé.</p>
                    
                    <div style="background: #fff; color: #333; padding: 20px; border-radius: 5px; margin: 20px 0; text-align: left;">
                        <h3>📋 Informations de connexion</h3>
                        <div style="background: #f8f9fa; padding: 15px; border-radius: 5px; font-family: monospace;">
                            <strong>Identifiant:</strong> admin<br>
                            <strong>Mot de passe:</strong> (celui que vous avez défini)
                        </div>
                        
                        <h3 style="margin-top: 20px;">✅ Installation complétée</h3>
                        <ul style="list-style: none; padding: 0;">
                            <li>✅ Fichier .env créé avec toutes les configurations</li>
                            <li>✅ Base de données créée et structurée</li>
                            <li>✅ Compte administrateur créé (mot de passe hashé)</li>
                            <li>✅ Permissions des répertoires corrigées</li>
                            <li>✅ Protection .htaccess en place</li>
                            <li>✅ Système de logs initialisé</li>
                            <li>✅ Configuration CSRF et sessions</li>
                            <li>✅ Rate limiting configuré</li>
                            <li>✅ <strong>Système d'audit (Event Sourcing) activé</strong></li>
                            <li>✅ <strong>Traçabilité complète des actions</strong></li>
                            <li>✅ <strong>Sécurité des sessions renforcée</strong></li>
                        </ul>
                        
                        <h3 style="margin-top: 20px;">🔍 Système d'audit</h3>
                        <div style="background: #e7f3ff; padding: 10px; border-radius: 5px; border-left: 3px solid #2196f3;">
                            <p><strong>Le système d'audit enregistre automatiquement :</strong></p>
                            <ul>
                                <li>Toutes les connexions et déconnexions</li>
                                <li>Créations, modifications et suppressions de données</li>
                                <li>Tentatives d'accès non autorisées</li>
                                <li>Violations de sécurité (CSRF, rate limit, etc.)</li>
                                <li>Adresse IP et user agent de chaque action</li>
                            </ul>
                            <p style="margin: 5px 0 0 0;"><em>→ Logs consultables depuis l'interface administrateur</em></p>
                        </div>
                    </div>
                    
                    <div style="margin-top: 20px;">
                        <a href="login/public/index.php" class="btn" style="display: inline-block; text-decoration: none;">
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
