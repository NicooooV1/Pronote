<?php
/**
 * Fonctions utilitaires partagées pour le panneau d'administration
 */

/**
 * Journalise une action dans la table audit_log
 */
function logAudit($action, $model = null, $modelId = null, $oldValues = null, $newValues = null) {
    try {
        $pdo = getPDO();
        $user = getCurrentUser();
        $stmt = $pdo->prepare("
            INSERT INTO audit_log (action, model, model_id, user_id, user_type, 
                                   old_values, new_values, ip_address, user_agent)
            VALUES (?, ?, ?, ?, 'administrateur', ?, ?, ?, ?)
        ");
        $stmt->execute([
            $action, $model, $modelId, $user['id'] ?? null,
            $oldValues ? json_encode($oldValues) : null,
            $newValues ? json_encode($newValues) : null,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    } catch (Exception $e) {
        error_log("Audit log error: " . $e->getMessage());
    }
}

/**
 * Génère un mot de passe sécurisé cryptographiquement
 */
function generateSecurePassword($length = 12) {
    $upper = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $lower = 'abcdefghijklmnopqrstuvwxyz';
    $digits = '0123456789';
    $special = '!@#$%&*?';
    
    // Garantir au moins un de chaque type
    $password = $upper[random_int(0, strlen($upper)-1)]
              . $lower[random_int(0, strlen($lower)-1)]
              . $digits[random_int(0, strlen($digits)-1)]
              . $special[random_int(0, strlen($special)-1)];
    
    $all = $upper . $lower . $digits . $special;
    for ($i = 4; $i < $length; $i++) {
        $password .= $all[random_int(0, strlen($all)-1)];
    }
    
    return str_shuffle($password);
}

/**
 * Résout le nom complet d'un utilisateur par son ID et type
 */
function resolveUserName($pdo, $userId, $userType) {
    $tableMap = [
        'eleve' => 'eleves',
        'parent' => 'parents',
        'professeur' => 'professeurs',
        'vie_scolaire' => 'vie_scolaire',
        'administrateur' => 'administrateurs',
    ];
    $table = $tableMap[$userType] ?? null;
    if (!$table) return 'Inconnu';
    try {
        $stmt = $pdo->prepare("SELECT CONCAT(prenom, ' ', nom) as fullname FROM `$table` WHERE id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetchColumn() ?: 'Inconnu';
    } catch (Exception $e) {
        return 'Inconnu';
    }
}

/**
 * Labels des profils
 */
function getProfilLabel($profil) {
    $labels = [
        'eleve' => 'Élève',
        'parent' => 'Parent',
        'professeur' => 'Professeur',
        'vie_scolaire' => 'Vie scolaire',
        'administrateur' => 'Administrateur',
    ];
    return $labels[$profil] ?? ucfirst($profil);
}

/**
 * Classe CSS pour un badge profil
 */
function getProfilBadgeClass($profil) {
    $classes = [
        'eleve' => 'badge-eleve',
        'parent' => 'badge-parent',
        'professeur' => 'badge-prof',
        'vie_scolaire' => 'badge-vs',
        'administrateur' => 'badge-admin',
    ];
    return $classes[$profil] ?? 'badge-default';
}
