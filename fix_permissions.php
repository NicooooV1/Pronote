<?php
/**
 * Script UNIVERSEL de correction des permissions Pronote
 * S'adapte automatiquement à TOUS les environnements possibles
 * Version 2.0 - Compatible tous serveurs, tous hébergeurs
 * SUPPRIMER après utilisation
 */

header('Content-Type: text/html; charset=UTF-8');

// Vérifier que ce script est exécuté depuis le bon répertoire
if (!file_exists(__DIR__ . '/install.php')) {
    die('Ce script doit être placé dans le même répertoire que install.php');
}

// Configuration de sécurité
set_time_limit(300);
ini_set('memory_limit', '512M');
error_reporting(E_ALL);

echo "<!DOCTYPE html><html><head><title>Correction Universelle des Permissions Pronote</title><meta charset='UTF-8'>";
echo "<style>
    body { font-family: 'Segoe UI', sans-serif; margin: 20px; background: #f8f9fa; line-height: 1.6; }
    .container { max-width: 1400px; background: white; padding: 25px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
    .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 25px; border-radius: 8px; text-align: center; margin-bottom: 25px; }
    .success { color: #28a745; font-weight: bold; }
    .warning { color: #fd7e14; font-weight: bold; }
    .error { color: #dc3545; font-weight: bold; }
    .info { color: #17a2b8; font-weight: bold; }
    .debug { color: #6c757d; font-size: 0.9em; }
    table { width: 100%; border-collapse: collapse; margin: 20px 0; }
    th, td { padding: 12px; border: 1px solid #dee2e6; text-align: left; }
    th { background: #f8f9fa; font-weight: 600; }
    tr:nth-child(even) { background: #f8f9fa; }
    .result-box { padding: 20px; border-radius: 8px; margin: 20px 0; }
    .result-success { background: #d1ecf1; border-left: 5px solid #28a745; }
    .result-error { background: #f8d7da; border-left: 5px solid #dc3545; }
    .result-warning { background: #fff3cd; border-left: 5px solid #fd7e14; }
    .result-info { background: #d1ecf1; border-left: 5px solid #17a2b8; }
    pre { background: #f8f9fa; padding: 20px; border-radius: 6px; overflow-x: auto; border: 1px solid #dee2e6; }
    .step { margin: 25px 0; padding: 20px; border-left: 4px solid #17a2b8; background: #f8f9fa; border-radius: 0 8px 8px 0; }
    .strategy { background: #e9ecef; padding: 15px; border-radius: 6px; margin: 10px 0; }
    .environment-info { background: #fff3cd; padding: 15px; border-radius: 6px; margin: 15px 0; }
    .progress-bar { width: 100%; height: 20px; background: #e9ecef; border-radius: 10px; overflow: hidden; margin: 10px 0; }
    .progress-fill { height: 100%; background: linear-gradient(90deg, #28a745, #20c997); transition: width 0.3s ease; }
    .badge { padding: 4px 8px; border-radius: 4px; font-size: 0.85em; font-weight: 600; }
    .badge-success { background: #28a745; color: white; }
    .badge-warning { background: #fd7e14; color: white; }
    .badge-danger { background: #dc3545; color: white; }
    .badge-info { background: #17a2b8; color: white; }
    .collapsible { cursor: pointer; padding: 10px; background: #e9ecef; border-radius: 5px; margin: 5px 0; }
    .collapsible:hover { background: #dee2e6; }
    .content { display: none; padding: 15px; background: #f8f9fa; border-radius: 5px; }
    .content.active { display: block; }
</style></head><body>";

echo "<div class='container'>";
echo "<div class='header'>";
echo "<h1>🛠️ Correction Universelle des Permissions Pronote</h1>";
echo "<p>Détection automatique d'environnement et correction adaptée à votre serveur</p>";
echo "</div>";

$directories = [
    'API' => 'Répertoire principal de l\'API',
    'API/config' => 'Fichiers de configuration',
    'API/logs' => 'Journaux d\'application', 
    'uploads' => 'Fichiers téléchargés',
    'temp' => 'Fichiers temporaires'
];

/**
 * Classe principale de diagnostic et correction
 */
class UniversalPermissionsFixer {
    private $baseDir;
    private $environment;
    private $capabilities;
    private $results = [];
    private $strategies = [];
    private $webUsers = ['www-data', 'nginx', 'apache', 'httpd', 'nobody', '_www'];
    
    public function __construct($baseDir) {
        $this->baseDir = $baseDir;
        $this->detectEnvironment();
        $this->detectCapabilities();
        $this->planStrategies();
    }
    
    /**
     * Détection complète de l'environnement
     */
    private function detectEnvironment() {
        $this->environment = [
            'php_user' => get_current_user(),
            'php_uid' => getmyuid(),
            'php_gid' => getmygid(),
            'is_root' => getmyuid() === 0,
            'is_cli' => php_sapi_name() === 'cli',
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'os' => PHP_OS_FAMILY,
            'web_user' => $this->detectWebUser(),
            'hosting_type' => $this->detectHostingType(),
            'selinux' => $this->checkSELinux(),
            'docker' => $this->checkDocker(),
            'shared_hosting' => $this->checkSharedHosting(),
            'current_dir_owner' => $this->getCurrentDirOwner(),
            'umask' => sprintf('%04o', umask()),
            'open_basedir' => ini_get('open_basedir') ?: null,
            'safe_mode' => ini_get('safe_mode'),
            'disable_functions' => array_map('trim', explode(',', ini_get('disable_functions') ?: ''))
        ];
    }
    
    /**
     * Détection du type d'hébergement
     */
    private function detectHostingType() {
        // VPS/Serveur dédié
        if ($this->environment['is_root']) {
            return 'dedicated';
        }
        
        // Docker
        if ($this->checkDocker()) {
            return 'docker';
        }
        
        // Hébergement mutualisé
        if ($this->checkSharedHosting()) {
            return 'shared';
        }
        
        // VPS sans root
        if (function_exists('exec') && !in_array('exec', $this->environment['disable_functions'])) {
            return 'vps';
        }
        
        return 'unknown';
    }
    
    /**
     * Vérifie si on est dans Docker
     */
    private function checkDocker() {
        return file_exists('/.dockerenv') || 
               (file_exists('/proc/1/cgroup') && 
                strpos(file_get_contents('/proc/1/cgroup'), 'docker') !== false);
    }
    
    /**
     * Vérifie si c'est un hébergement mutualisé
     */
    private function checkSharedHosting() {
        return !function_exists('exec') || 
               in_array('exec', $this->environment['disable_functions']) ||
               ini_get('open_basedir') !== false ||
               !$this->environment['is_root'];
    }
    
    /**
     * Vérifie SELinux
     */
    private function checkSELinux() {
        if (!function_exists('exec') || in_array('exec', $this->environment['disable_functions'])) {
            return false;
        }
        
        $output = [];
        @exec('getenforce 2>/dev/null', $output, $return);
        return $return === 0 && isset($output[0]) && 
               in_array(trim($output[0]), ['Enforcing', 'Permissive']);
    }
    
    /**
     * Détecte l'utilisateur web
     */
    private function detectWebUser() {
        foreach ($this->webUsers as $user) {
            $userInfo = posix_getpwnam($user);
            if ($userInfo !== false) {
                return [
                    'name' => $user,
                    'uid' => $userInfo['uid'],
                    'gid' => $userInfo['gid']
                ];
            }
        }
        return null;
    }
    
    /**
     * Obtient les infos du propriétaire du répertoire courant
     */
    private function getCurrentDirOwner() {
        $stat = stat($this->baseDir);
        $owner = posix_getpwuid($stat['uid']);
        $group = posix_getgrgid($stat['gid']);
        
        return [
            'uid' => $stat['uid'],
            'gid' => $stat['gid'],
            'name' => $owner ? $owner['name'] : "UID:{$stat['uid']}",
            'group' => $group ? $group['name'] : "GID:{$stat['gid']}",
            'permissions' => sprintf('%o', $stat['mode'] & 0777)
        ];
    }
    
    /**
     * Détecte les capacités disponibles
     */
    private function detectCapabilities() {
        $this->capabilities = [
            'chmod' => function_exists('chmod'),
            'chown' => function_exists('chown') && $this->environment['is_root'],
            'chgrp' => function_exists('chgrp') && $this->environment['is_root'],
            'mkdir' => function_exists('mkdir'),
            'exec' => function_exists('exec') && !in_array('exec', $this->environment['disable_functions']),
            'shell_exec' => function_exists('shell_exec') && !in_array('shell_exec', $this->environment['disable_functions']),
            'system' => function_exists('system') && !in_array('system', $this->environment['disable_functions']),
            'file_put_contents' => function_exists('file_put_contents'),
            'posix' => extension_loaded('posix'),
            'acl' => $this->checkAclSupport()
        ];
    }
    
    /**
     * Vérifie le support des ACL
     */
    private function checkAclSupport() {
        if (!$this->capabilities['exec'] ?? false) return false;
        
        $output = [];
        @exec('which setfacl 2>/dev/null', $output, $return);
        return $return === 0;
    }
    
    /**
     * Planifie les stratégies selon l'environnement
     */
    private function planStrategies() {
        $this->strategies = [];
        
        // Stratégies selon le type d'hébergement
        switch ($this->environment['hosting_type']) {
            case 'dedicated':
                $this->strategies = [
                    'chown_to_web_user' => 'Changer propriétaire vers utilisateur web',
                    'chown_to_php_user' => 'Changer propriétaire vers utilisateur PHP',
                    'fix_group_permissions' => 'Corriger permissions de groupe',
                    'set_acl' => 'Utiliser les ACL',
                    'chmod_progressive' => 'Permissions progressives'
                ];
                break;
                
            case 'docker':
                $this->strategies = [
                    'chown_to_php_user' => 'Adapter au conteneur Docker',
                    'chmod_progressive' => 'Permissions Docker',
                    'create_directories' => 'Créer répertoires manquants'
                ];
                break;
                
            case 'shared':
                $this->strategies = [
                    'chmod_progressive' => 'Permissions mutualisées',
                    'create_directories' => 'Créer répertoires',
                    'htaccess_protection' => 'Protection .htaccess'
                ];
                break;
                
            case 'vps':
                $this->strategies = [
                    'sudo_operations' => 'Opérations sudo',
                    'chmod_progressive' => 'Permissions VPS',
                    'fix_group_permissions' => 'Groupes VPS'
                ];
                break;
                
            default:
                $this->strategies = [
                    'chmod_progressive' => 'Permissions de base',
                    'create_directories' => 'Création répertoires',
                    'manual_commands' => 'Commandes manuelles'
                ];
        }
        
        // Adaptations spéciales
        if ($this->environment['selinux']) {
            $this->strategies['selinux_context'] = 'Correction contexte SELinux';
        }
        
        if ($this->environment['shared_hosting']) {
            unset($this->strategies['chown_to_web_user']);
            unset($this->strategies['chown_to_php_user']);
        }
    }
    
    /**
     * Diagnostic principal
     */
    public function diagnose() {
        echo "<div class='step'>";
        echo "<h2>🔍 Diagnostic Environnement Complet</h2>";
        
        // Affichage de l'environnement
        $this->displayEnvironmentInfo();
        
        // Analyse des répertoires
        $this->analyzeDirectories();
        
        echo "</div>";
    }
    
    /**
     * Affiche les informations d'environnement
     */
    private function displayEnvironmentInfo() {
        echo "<div class='environment-info'>";
        echo "<h3>📊 Environnement détecté</h3>";
        
        echo "<table>";
        echo "<tr><th>Paramètre</th><th>Valeur</th><th>Impact</th></tr>";
        
        $infos = [
            ['Utilisateur PHP', $this->environment['php_user'] . " (UID: {$this->environment['php_uid']})", 
             $this->environment['is_root'] ? 'Privilèges administrateur' : 'Utilisateur standard'],
            ['Type d\'hébergement', $this->getHostingTypeLabel(), $this->getHostingImpact()],
            ['Serveur Web', $this->environment['server_software'], '-'],
            ['Système', $this->environment['os'], '-'],
            ['Utilisateur Web', $this->environment['web_user'] ? 
             $this->environment['web_user']['name'] . " (UID: {$this->environment['web_user']['uid']})" : 'Non détecté', 
             $this->environment['web_user'] ? 'Cible pour chown' : 'Chown impossible'],
            ['Docker', $this->environment['docker'] ? 'Oui' : 'Non', 
             $this->environment['docker'] ? 'Permissions conteneur' : '-'],
            ['SELinux', $this->environment['selinux'] ? 'Actif' : 'Inactif', 
             $this->environment['selinux'] ? 'Contextes requis' : '-'],
            ['Propriétaire répertoire', $this->environment['current_dir_owner']['name'], 
             $this->environment['current_dir_owner']['uid'] == $this->environment['php_uid'] ? 'Compatible' : 'Conflit possible']
        ];
        
        foreach ($infos as $info) {
            echo "<tr><td>{$info[0]}</td><td>{$info[1]}</td><td>{$info[2]}</td></tr>";
        }
        
        echo "</table>";
        
        echo "<h3>🛠️ Capacités disponibles</h3>";
        echo "<div style='display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px;'>";
        
        foreach ($this->capabilities as $cap => $available) {
            $class = $available ? 'badge-success' : 'badge-danger';
            $text = $available ? 'Disponible' : 'Indisponible';
            echo "<div><span class='badge {$class}'>{$cap}</span> {$text}</div>";
        }
        
        echo "</div>";
        echo "</div>";
    }
    
    /**
     * Analyse détaillée des répertoires
     */
    private function analyzeDirectories() {
        global $directories;
        
        echo "<h3>📁 Analyse des répertoires</h3>";
        
        foreach ($directories as $dir => $description) {
            $path = $this->baseDir . '/' . $dir;
            $analysis = $this->analyzeDirectory($path, $dir);
            $this->results[$dir] = $analysis;
            
            echo "<div class='collapsible' onclick='toggleContent(\"dir-{$dir}\")'>";
            echo "<strong>{$dir}</strong> - {$description} ";
            
            if ($analysis['overall_status'] === 'success') {
                echo "<span class='badge badge-success'>OK</span>";
            } elseif ($analysis['overall_status'] === 'warning') {
                echo "<span class='badge badge-warning'>Attention</span>";
            } else {
                echo "<span class='badge badge-danger'>Problème</span>";
            }
            
            echo "</div>";
            
            echo "<div class='content' id='dir-{$dir}'>";
            $this->displayDirectoryAnalysis($analysis);
            echo "</div>";
        }
    }
    
    /**
     * Analyse un répertoire spécifique
     */
    private function analyzeDirectory($path, $dirName) {
        $analysis = [
            'path' => $path,
            'exists' => is_dir($path),
            'readable' => false,
            'writable' => false,
            'write_test' => false,
            'permissions' => 'N/A',
            'owner' => 'N/A',
            'group' => 'N/A',
            'issues' => [],
            'solutions' => [],
            'overall_status' => 'error'
        ];
        
        if ($analysis['exists']) {
            $stat = stat($path);
            $analysis['permissions'] = sprintf('%o', $stat['mode'] & 0777);
            $analysis['readable'] = is_readable($path);
            $analysis['writable'] = is_writable($path);
            
            $owner = posix_getpwuid($stat['uid']);
            $group = posix_getgrgid($stat['gid']);
            $analysis['owner'] = $owner ? $owner['name'] : "UID:{$stat['uid']}";
            $analysis['group'] = $group ? $group['name'] : "GID:{$stat['gid']}";
            
            // Test d'écriture réel
            $testFile = $path . '/test_' . uniqid() . '.txt';
            $analysis['write_test'] = @file_put_contents($testFile, 'test') !== false;
            if ($analysis['write_test']) {
                @unlink($testFile);
            }
            
            // Diagnostic des problèmes
            if (!$analysis['write_test']) {
                if ($stat['uid'] !== $this->environment['php_uid']) {
                    $analysis['issues'][] = "Propriétaire différent de l'utilisateur PHP";
                    if ($this->capabilities['chown']) {
                        $analysis['solutions'][] = "Changer le propriétaire";
                    }
                }
                
                if (($stat['mode'] & 0200) === 0) {
                    $analysis['issues'][] = "Pas de permission d'écriture pour le propriétaire";
                    $analysis['solutions'][] = "Ajouter permission d'écriture propriétaire";
                }
                
                if (($stat['mode'] & 0020) === 0 && $stat['gid'] === $this->environment['php_gid']) {
                    $analysis['issues'][] = "Pas de permission d'écriture pour le groupe";
                    $analysis['solutions'][] = "Ajouter permission d'écriture groupe";
                }
                
                if (($stat['mode'] & 0002) === 0) {
                    $analysis['issues'][] = "Pas de permission d'écriture pour tous";
                    $analysis['solutions'][] = "Permissions 777 (temporaire)";
                }
            }
            
            // Statut global
            if ($analysis['write_test']) {
                $analysis['overall_status'] = 'success';
            } elseif ($analysis['readable']) {
                $analysis['overall_status'] = 'warning';
            }
        } else {
            $analysis['issues'][] = "Répertoire n'existe pas";
            $analysis['solutions'][] = "Créer le répertoire";
        }
        
        return $analysis;
    }
    
    /**
     * Affiche l'analyse d'un répertoire
     */
    private function displayDirectoryAnalysis($analysis) {
        echo "<table>";
        echo "<tr><th>Propriété</th><th>Valeur</th></tr>";
        echo "<tr><td>Existe</td><td class='" . ($analysis['exists'] ? 'success' : 'error') . "'>" . 
             ($analysis['exists'] ? 'Oui' : 'Non') . "</td></tr>";
        
        if ($analysis['exists']) {
            echo "<tr><td>Permissions</td><td>{$analysis['permissions']}</td></tr>";
            echo "<tr><td>Propriétaire</td><td>{$analysis['owner']}</td></tr>";
            echo "<tr><td>Groupe</td><td>{$analysis['group']}</td></tr>";
            echo "<tr><td>Lisible</td><td class='" . ($analysis['readable'] ? 'success' : 'error') . "'>" . 
                 ($analysis['readable'] ? 'Oui' : 'Non') . "</td></tr>";
            echo "<tr><td>Écritable (PHP)</td><td class='" . ($analysis['writable'] ? 'success' : 'error') . "'>" . 
                 ($analysis['writable'] ? 'Oui' : 'Non') . "</td></tr>";
            echo "<tr><td>Test écriture</td><td class='" . ($analysis['write_test'] ? 'success' : 'error') . "'>" . 
                 ($analysis['write_test'] ? 'Succès' : 'Échec') . "</td></tr>";
        }
        
        echo "</table>";
        
        if (!empty($analysis['issues'])) {
            echo "<h4>⚠️ Problèmes identifiés :</h4>";
            echo "<ul>";
            foreach ($analysis['issues'] as $issue) {
                echo "<li class='error'>{$issue}</li>";
            }
            echo "</ul>";
        }
        
        if (!empty($analysis['solutions'])) {
            echo "<h4>💡 Solutions possibles :</h4>";
            echo "<ul>";
            foreach ($analysis['solutions'] as $solution) {
                echo "<li class='info'>{$solution}</li>";
            }
            echo "</ul>";
        }
    }
    
    /**
     * Correction principale
     */
    public function fix() {
        echo "<div class='step'>";
        echo "<h2>🔧 Correction Automatique</h2>";
        
        $overallSuccess = true;
        $fixedCount = 0;
        $totalCount = count($this->results);
        
        foreach ($this->results as $dirName => $analysis) {
            if ($analysis['overall_status'] !== 'success') {
                echo "<h3>🛠️ Correction de {$dirName}</h3>";
                
                $fixed = $this->fixDirectory($analysis, $dirName);
                if ($fixed) {
                    $fixedCount++;
                    echo "<p class='success'>✅ {$dirName} corrigé avec succès</p>";
                } else {
                    $overallSuccess = false;
                    echo "<p class='error'>❌ Impossible de corriger {$dirName} automatiquement</p>";
                }
            } else {
                $fixedCount++;
            }
            
            // Barre de progression
            $progress = ($fixedCount / $totalCount) * 100;
            echo "<div class='progress-bar'>";
            echo "<div class='progress-fill' style='width: {$progress}%'></div>";
            echo "</div>";
        }
        
        if ($overallSuccess) {
            echo "<div class='result-box result-success'>";
            echo "<h3>🎉 Correction complète réussie !</h3>";
            echo "<p>Tous les répertoires sont maintenant accessibles en écriture.</p>";
            echo "<p><a href='install.php' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>→ Continuer l'installation</a></p>";
            echo "</div>";
        } else {
            $this->generateManualSolutions();
        }
        
        echo "</div>";
    }
    
    /**
     * Correction d'un répertoire spécifique
     */
    private function fixDirectory($analysis, $dirName) {
        $path = $analysis['path'];
        $fixed = false;
        
        // Créer le répertoire s'il n'existe pas
        if (!$analysis['exists']) {
            if (@mkdir($path, 0755, true)) {
                echo "<p class='success'>📁 Répertoire {$dirName} créé</p>";
            } else {
                echo "<p class='error'>❌ Impossible de créer {$dirName}</p>";
                return false;
            }
        }
        
        // Essayer les stratégies selon l'environnement
        foreach ($this->strategies as $strategy => $description) {
            if ($fixed) break;
            
            echo "<p class='info'>🔄 Tentative : {$description}</p>";
            $fixed = $this->applyStrategy($strategy, $path, $dirName);
        }
        
        return $fixed;
    }
    
    /**
     * Application d'une stratégie de correction
     */
    private function applyStrategy($strategy, $path, $dirName) {
        switch ($strategy) {
            case 'chown_to_web_user':
                return $this->chownToWebUser($path);
                
            case 'chown_to_php_user':
                return $this->chownToPhpUser($path);
                
            case 'chmod_progressive':
                return $this->chmodProgressive($path);
                
            case 'fix_group_permissions':
                return $this->fixGroupPermissions($path);
                
            case 'set_acl':
                return $this->setAcl($path);
                
            case 'selinux_context':
                return $this->fixSelinuxContext($path);
                
            case 'sudo_operations':
                return $this->sudoOperations($path);
                
            default:
                return false;
        }
    }
    
    /**
     * Changer propriétaire vers utilisateur web
     */
    private function chownToWebUser($path) {
        if (!$this->capabilities['chown'] || !$this->environment['web_user']) {
            return false;
        }
        
        $webUser = $this->environment['web_user'];
        if (@chown($path, $webUser['uid']) && @chgrp($path, $webUser['gid'])) {
            return $this->testWrite($path);
        }
        
        return false;
    }
    
    /**
     * Changer propriétaire vers utilisateur PHP
     */
    private function chownToPhpUser($path) {
        if (!$this->capabilities['chown']) {
            return false;
        }
        
        if (@chown($path, $this->environment['php_uid']) && 
            @chgrp($path, $this->environment['php_gid'])) {
            return $this->testWrite($path);
        }
        
        return false;
    }
    
    /**
     * Permissions progressives
     */
    private function chmodProgressive($path) {
        $permissions = ['0755', '0775', '0777'];
        
        foreach ($permissions as $perm) {
            if (@chmod($path, octdec($perm))) {
                if ($this->testWrite($path)) {
                    echo "<p class='success'>✅ Permissions {$perm} appliquées</p>";
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Correction permissions de groupe
     */
    private function fixGroupPermissions($path) {
        $stat = stat($path);
        $currentPerms = $stat['mode'] & 0777;
        $newPerms = $currentPerms | 0020; // Ajouter écriture groupe
        
        if (@chmod($path, $newPerms)) {
            return $this->testWrite($path);
        }
        
        return false;
    }
    
    /**
     * Configuration ACL
     */
    private function setAcl($path) {
        if (!$this->capabilities['acl'] || !$this->capabilities['exec']) {
            return false;
        }
        
        $commands = [
            "setfacl -m u:{$this->environment['php_user']}:rwx {$path}",
            "setfacl -m g:{$this->environment['php_user']}:rwx {$path}",
            "setfacl -d -m u:{$this->environment['php_user']}:rwx {$path}",
            "setfacl -d -m g:{$this->environment['php_user']}:rwx {$path}"
        ];
        
        foreach ($commands as $cmd) {
            @exec($cmd . ' 2>/dev/null', $output, $return);
        }
        
        return $this->testWrite($path);
    }
    
    /**
     * Correction contexte SELinux
     */
    private function fixSelinuxContext($path) {
        if (!$this->capabilities['exec'] || !$this->environment['selinux']) {
            return false;
        }
        
        $commands = [
            "chcon -R -t httpd_exec_t {$path}",
            "setsebool -P httpd_can_network_connect 1",
            "setsebool -P httpd_read_user_content 1",
            "restorecon -R {$path}"
        ];
        
        foreach ($commands as $cmd) {
            @exec($cmd . ' 2>/dev/null');
        }
        
        return $this->testWrite($path);
    }
    
    /**
     * Opérations sudo
     */
    private function sudoOperations($path) {
        // Cette méthode génère des commandes pour exécution manuelle
        return false;
    }
    
    /**
     * Test d'écriture
     */
    private function testWrite($path) {
        $testFile = $path . '/test_write_' . uniqid() . '.txt';
        $canWrite = @file_put_contents($testFile, 'test') !== false;
        if ($canWrite) {
            @unlink($testFile);
        }
        return $canWrite;
    }
    
    /**
     * Génère les solutions manuelles
     */
    private function generateManualSolutions() {
        echo "<div class='result-box result-warning'>";
        echo "<h3>🔧 Solutions manuelles requises</h3>";
        echo "<p>Certains problèmes nécessitent une intervention manuelle. Voici les commandes adaptées à votre environnement :</p>";
        
        $solutions = $this->generateEnvironmentSpecificSolutions();
        
        foreach ($solutions as $title => $commands) {
            echo "<h4>{$title}</h4>";
            echo "<pre>";
            foreach ($commands as $cmd) {
                echo htmlspecialchars($cmd) . "\n";
            }
            echo "</pre>";
        }
        
        echo "</div>";
    }
    
    /**
     * Génère les solutions spécifiques à l'environnement
     */
    private function generateEnvironmentSpecificSolutions() {
        global $directories;
        $solutions = [];
        $dirList = implode(' ', array_keys($directories));
        
        switch ($this->environment['hosting_type']) {
            case 'dedicated':
                $solutions['Serveur dédié/VPS avec root'] = [
                    "cd " . $this->baseDir,
                    "# Créer les répertoires",
                    "mkdir -p " . implode(' ', array_keys($directories)),
                    "",
                    "# Méthode 1 - Utilisateur web",
                    $this->environment['web_user'] ? 
                        "chown -R {$this->environment['web_user']['name']}:{$this->environment['web_user']['name']} {$dirList}" :
                        "chown -R www-data:www-data {$dirList}",
                    "chmod -R 755 {$dirList}",
                    "",
                    "# Méthode 2 - Utilisateur PHP",
                    "chown -R {$this->environment['php_user']}:{$this->environment['php_user']} {$dirList}",
                    "chmod -R 755 {$dirList}"
                ];
                break;
                
            case 'docker':
                $solutions['Environnement Docker'] = [
                    "# Depuis le conteneur",
                    "cd " . $this->baseDir,
                    "mkdir -p " . implode(' ', array_keys($directories)),
                    "chown -R {$this->environment['php_user']}:{$this->environment['php_user']} {$dirList}",
                    "chmod -R 755 {$dirList}",
                    "",
                    "# Ou depuis l'hôte Docker",
                    "docker exec -it [CONTAINER_NAME] chown -R {$this->environment['php_user']}:{$this->environment['php_user']} {$dirList}",
                    "docker exec -it [CONTAINER_NAME] chmod -R 755 {$dirList}"
                ];
                break;
                
            case 'shared':
                $solutions['Hébergement mutualisé'] = [
                    "# Via le gestionnaire de fichiers de votre hébergeur",
                    "# 1. Créer les dossiers manquants :",
                    implode(', ', array_keys($directories)),
                    "",
                    "# 2. Définir les permissions à 755 pour chaque dossier",
                    "# 3. Si 755 ne fonctionne pas, essayer 777 (temporairement)",
                    "",
                    "# Via SSH si disponible :",
                    "cd " . $this->baseDir,
                    "mkdir -p " . implode(' ', array_keys($directories)),
                    "chmod 755 {$dirList}",
                    "# Si nécessaire :",
                    "chmod 777 {$dirList}"
                ];
                break;
                
            case 'vps':
                $solutions['VPS sans accès root'] = [
                    "cd " . $this->baseDir,
                    "mkdir -p " . implode(' ', array_keys($directories)),
                    "",
                    "# Essayer avec sudo",
                    "sudo chown -R {$this->environment['php_user']}:{$this->environment['php_user']} {$dirList}",
                    "sudo chmod -R 755 {$dirList}",
                    "",
                    "# Ou contacter l'administrateur système",
                    "# avec ces informations :",
                    "# Utilisateur PHP: {$this->environment['php_user']} (UID: {$this->environment['php_uid']})",
                    "# Répertoires à corriger: {$dirList}"
                ];
                break;
        }
        
        // Solutions SELinux si nécessaire
        if ($this->environment['selinux']) {
            $solutions['Correction SELinux'] = [
                "# Corriger les contextes SELinux",
                "cd " . $this->baseDir,
                "chcon -R -t httpd_exec_t {$dirList}",
                "setsebool -P httpd_can_network_connect 1",
                "restorecon -R {$dirList}"
            ];
        }
        
        // Solution de dernier recours
        $solutions['Solution de dernier recours'] = [
            "cd " . $this->baseDir,
            "# ATTENTION: 777 est moins sécurisé, à utiliser temporairement",
            "chmod -R 777 " . implode(' ', array_keys($directories)),
            "",
            "# Après installation, remettre des permissions plus sécurisées :",
            "chmod -R 755 " . implode(' ', array_keys($directories))
        ];
        
        return $solutions;
    }
    
    /**
     * Labels d'affichage
     */
    private function getHostingTypeLabel() {
        $labels = [
            'dedicated' => 'Serveur dédié/VPS avec root',
            'docker' => 'Environnement Docker',
            'shared' => 'Hébergement mutualisé',
            'vps' => 'VPS sans root',
            'unknown' => 'Type inconnu'
        ];
        
        return $labels[$this->environment['hosting_type']] ?? 'Inconnu';
    }
    
    /**
     * Impact du type d'hébergement
     */
    private function getHostingImpact() {
        $impacts = [
            'dedicated' => 'Contrôle total, toutes corrections possibles',
            'docker' => 'Permissions conteneur, chown limité',
            'shared' => 'Limitations chmod/chown, corrections restreintes',
            'vps' => 'Sudo requis, corrections intermédiaires',
            'unknown' => 'Capacités limitées détectées'
        ];
        
        return $impacts[$this->environment['hosting_type']] ?? 'Impact inconnu';
    }
}

// Instanciation et exécution
$fixer = new UniversalPermissionsFixer(__DIR__);

echo "<script>
function toggleContent(id) {
    const content = document.getElementById(id);
    content.classList.toggle('active');
}

// Auto-expand les éléments avec des problèmes
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.badge-danger, .badge-warning').forEach(function(badge) {
        const collapsible = badge.closest('.collapsible');
        if (collapsible) {
            const id = collapsible.getAttribute('onclick').match(/toggleContent\\('([^']+)'/)[1];
            document.getElementById(id).classList.add('active');
        }
    });
});
</script>";

// Exécution du diagnostic
$fixer->diagnose();

// Tentative de correction automatique
$fixer->fix();

echo "<div class='result-box result-info'>";
echo "<h3>🔒 Sécurité</h3>";
echo "<p><strong>Important :</strong> Supprimez ce fichier après utilisation :</p>";
echo "<pre>rm " . __FILE__ . "</pre>";
echo "<p>Ou renommez-le avec une extension différente pour désactiver PHP.</p>";
echo "</div>";

echo "</div>"; // Fermeture container
echo "</body></html>";
?>
