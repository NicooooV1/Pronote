<?php
/**
 * Protection du fichier d'installation
 * Ce script vérifie si l'installation est terminée
 * et protège le fichier d'installation si nécessaire
 */

// Ne pas afficher d'erreur pour éviter les fuites d'information
error_reporting(0);
ini_set('display_errors', 0);

// Vérifier si l'installation est terminée
$installFile = __DIR__ . '/install.php';
$installLockFile = __DIR__ . '/install.lock';
$installDisabledFile = __DIR__ . '/install.php.disabled';

// Si le fichier d'installation existe et que l'installation est terminée (lock file)
if (file_exists($installFile) && file_exists($installLockFile)) {
    try {
        // Vérifier la date du fichier de lock pour éviter les suppressions accidentelles récentes
        $lockFileTime = filemtime($installLockFile);
        $currentTime = time();
        $timeDiff = $currentTime - $lockFileTime;
        
        // Si le fichier de lock a plus de 24 heures
        if ($timeDiff > 86400) {
            // Option 1: Essayer de renommer le fichier
            if (!rename($installFile, $installDisabledFile)) {
                throw new Exception("Impossible de renommer le fichier d'installation");
            }
            
            // Journaliser l'action pour la traçabilité avec une indication de temps
            error_log("[" . date('Y-m-d H:i:s') . "] Le fichier d'installation a été désactivé automatiquement");
            
            // Ajouter une protection supplémentaire au fichier désactivé
            if (file_exists($installDisabledFile)) {
                chmod($installDisabledFile, 0400); // Lecture seule pour le propriétaire
            }
        }
    } catch (Exception $e) {
        // Si le renommage échoue, essayer d'autres méthodes de protection
        try {
            // Option 2: Créer un .htaccess pour bloquer l'accès
            $htaccess = __DIR__ . '/.htaccess';
            $htaccessContent = '';
            
            if (file_exists($htaccess) && is_readable($htaccess)) {
                $htaccessContent = file_get_contents($htaccess);
                
                // Vérifier si la protection n'existe pas déjà
                if (strpos($htaccessContent, '# Protection du fichier d\'installation') === false) {
                    // Ajouter la protection avec un blocage plus robuste
                    $htaccessAddition = "\n# Protection du fichier d'installation\n";
                    $htaccessAddition .= "<Files \"install.php\">\n";
                    $htaccessAddition .= "    Order allow,deny\n";
                    $htaccessAddition .= "    Deny from all\n";
                    $htaccessAddition .= "</Files>\n";
                    
                    // Protéger également le fichier désactivé
                    $htaccessAddition .= "<Files \"install.php.disabled\">\n";
                    $htaccessAddition .= "    Order allow,deny\n";
                    $htaccessAddition .= "    Deny from all\n";
                    $htaccessAddition .= "</Files>\n";
                    
                    // Écrire le fichier modifié
                    if (file_put_contents($htaccess, $htaccessContent . $htaccessAddition, LOCK_EX) === false) {
                        throw new Exception("Impossible d'écrire dans le fichier .htaccess");
                    }
                }
            } else {
                // Créer un nouveau fichier .htaccess
                $htaccessContent = "# Protection du fichier d'installation\n";
                $htaccessContent .= "<Files \"install.php\">\n";
                $htaccessContent .= "    Order allow,deny\n";
                $htaccessContent .= "    Deny from all\n";
                $htaccessContent .= "</Files>\n";
                $htaccessContent .= "<Files \"install.php.disabled\">\n";
                $htaccessContent .= "    Order allow,deny\n";
                $htaccessContent .= "    Deny from all\n";
                $htaccessContent .= "</Files>\n";
                
                if (file_put_contents($htaccess, $htaccessContent, LOCK_EX) === false) {
                    throw new Exception("Impossible de créer le fichier .htaccess");
                }
            }
            
            // Option 3: Créer un fichier PHP de blocage pour les serveurs sans .htaccess
            if (file_exists($installFile) && is_writable($installFile)) {
                $originalContent = file_get_contents($installFile);
                
                // Vérifier si le blocage n'existe pas déjà
                if (strpos($originalContent, 'Installation désactivée') === false) {
                    // Créer un blocage en début de fichier qui affiche un message d'erreur et arrête l'exécution
                    $blocker = "<?php\n";
                    $blocker .= "if(basename(__FILE__) === 'install.php') {\n";
                    $blocker .= "    http_response_code(403);\n";
                    $blocker .= "    header('Content-Type: text/html; charset=UTF-8');\n";
                    $blocker .= "    echo '<!DOCTYPE html><html><head><title>Installation désactivée</title></head>';\n";
                    $blocker .= "    echo '<body><h1>Installation désactivée</h1>';\n";
                    $blocker .= "    echo '<p>L\\'installation a été complétée et ce fichier a été désactivé pour des raisons de sécurité.</p>';\n";
                    $blocker .= "    echo '<p>Pour réinstaller, supprimez le fichier install.lock dans le répertoire racine.</p>';\n";
                    $blocker .= "    echo '</body></html>';\n";
                    $blocker .= "    exit;\n";
                    $blocker .= "}\n?>\n";
                    
                    // Ajouter le blocker au début du fichier
                    if (file_put_contents($installFile, $blocker . $originalContent, LOCK_EX) === false) {
                        throw new Exception("Impossible de modifier le fichier d'installation");
                    }
                }
            }
            
            // Option 4: Créer un fichier vide pour remplacer le fichier d'installation 
            // si aucune autre méthode ne fonctionne
            if (!file_exists($installDisabledFile) && !is_writable($installFile)) {
                // Si le fichier original ne peut pas être modifié, créer un fichier désactivé
                $emptyPhp = "<?php\nhttp_response_code(403);\ndie('Installation désactivée pour des raisons de sécurité.');\n";
                if (file_put_contents($installDisabledFile, $emptyPhp, LOCK_EX) !== false) {
                    // Changer les permissions pour le rendre en lecture seule
                    chmod($installDisabledFile, 0400);
                    
                    // Essayer de créer un lien symbolique
                    if (function_exists('symlink')) {
                        @unlink($installFile); // Supprimer l'original si possible
                        @symlink($installDisabledFile, $installFile); // Créer un lien symbolique
                    }
                }
            }
            
            // Journaliser l'action
            error_log("[" . date('Y-m-d H:i:s') . "] Protection du fichier d'installation mise en place");
        } catch (Exception $e2) {
            // Journaliser l'erreur avec plus de détails pour faciliter le débogage
            error_log("[" . date('Y-m-d H:i:s') . "] Impossible de protéger le fichier d'installation: " . $e2->getMessage());
        }
    }
}

// Vérifier également les permissions du fichier d'installation
if (file_exists($installFile)) {
    // S'assurer que les permissions sont restreintes (644 au lieu de 755)
    @chmod($installFile, 0644);
}

// Vérifier si un fichier .bak existe et le supprimer (sécurité)
$backupFiles = glob(__DIR__ . '/install*.bak');
foreach ($backupFiles as $backupFile) {
    @unlink($backupFile);
}
