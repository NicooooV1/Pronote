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

/**
 * Retourne les informations de fil d'Ariane pour une page admin.
 * @param string $currentPage Identifiant de la page courante
 * @param string $pageTitle   Titre de secours
 * @return array ['label' => string, 'parent' => string|null]
 */
function getAdminBreadcrumb($currentPage, $pageTitle = '') {
    $sections = [
        'dashboard'        => ['label' => 'Tableau de bord',           'parent' => null],
        'users'            => ['label' => 'Tous les utilisateurs',     'parent' => 'Utilisateurs'],
        'users_create'     => ['label' => 'Ajouter un utilisateur',    'parent' => 'Utilisateurs'],
        'admins'           => ['label' => 'Administrateurs',           'parent' => 'Utilisateurs'],
        'passwords'        => ['label' => 'Mots de passe',            'parent' => 'Utilisateurs'],
        'sessions'         => ['label' => 'Sessions actives',         'parent' => 'Utilisateurs'],
        'users_import'     => ['label' => 'Import CSV',               'parent' => 'Utilisateurs'],
        'notes'            => ['label' => 'Notes & Évaluations',      'parent' => 'Vie scolaire'],
        'absences'         => ['label' => 'Absences & Retards',       'parent' => 'Vie scolaire'],
        'justificatifs'    => ['label' => 'Justificatifs',            'parent' => 'Vie scolaire'],
        'devoirs'          => ['label' => 'Devoirs',                  'parent' => 'Vie scolaire'],
        'classes'          => ['label' => 'Gestion des classes',      'parent' => 'Classes'],
        'affectations'     => ['label' => 'Affectations professeurs', 'parent' => 'Classes'],
        'msg_moderation'   => ['label' => 'Modération',              'parent' => 'Messagerie'],
        'msg_conversations'=> ['label' => 'Conversations',           'parent' => 'Messagerie'],
        'msg_annonces'     => ['label' => 'Annonces globales',       'parent' => 'Messagerie'],
        'etab_info'        => ['label' => 'Informations',            'parent' => 'Établissement'],
        'etab_matieres'    => ['label' => 'Matières & Coefficients', 'parent' => 'Établissement'],
        'etab_periodes'    => ['label' => 'Périodes scolaires',      'parent' => 'Établissement'],
        'etab_evenements'  => ['label' => 'Événements',              'parent' => 'Établissement'],
        'audit'            => ['label' => 'Journal d\'audit',        'parent' => 'Système'],
        'stats'            => ['label' => 'Statistiques',            'parent' => 'Système'],
        'modules'          => ['label' => 'Gestion des modules',     'parent' => 'Système'],
    ];
    return $sections[$currentPage] ?? ['label' => $pageTitle, 'parent' => null];
}

/**
 * Génère le HTML du fil d'Ariane admin.
 * @param string $currentPage Page courante
 * @param string $pageTitle   Titre de secours
 * @param string $rootPrefix  Préfixe vers la racine du projet
 * @return string HTML du breadcrumb
 */
function renderAdminBreadcrumb($currentPage, $pageTitle = '', $rootPrefix = '../../') {
    $bc = getAdminBreadcrumb($currentPage, $pageTitle);
    $dashboardUrl = $rootPrefix . 'admin/dashboard.php';
    $html = '<nav class="admin-breadcrumb">';
    $html .= '<a href="' . $dashboardUrl . '"><i class="fas fa-cogs"></i> Administration</a>';
    if ($currentPage !== 'dashboard') {
        if (!empty($bc['parent'])) {
            $html .= '<span class="breadcrumb-sep"><i class="fas fa-chevron-right"></i></span>';
            $html .= '<span>' . htmlspecialchars($bc['parent']) . '</span>';
        }
        $html .= '<span class="breadcrumb-sep"><i class="fas fa-chevron-right"></i></span>';
        $html .= '<span class="breadcrumb-current">' . htmlspecialchars($bc['label']) . '</span>';
    }
    $html .= '</nav>';
    return $html;
}