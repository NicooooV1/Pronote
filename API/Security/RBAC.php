<?php
declare(strict_types=1);

namespace API\Security;

use PDO;

/**
 * RBAC – Role-Based Access Control centralisé
 *
 * Combine permissions statiques (fichier) et dynamiques (base de données).
 * Supporte : rôles, permissions fines, back-office guard, audit.
 */
class RBAC
{
    private PDO $pdo;
    private ?array $currentUser = null;
    private ?string $currentRole = null;
    private array $cachedPermissions = [];

    // ───────────────────── MATRICE STATIQUE ─────────────────────
    // Chaque action → liste des rôles autorisés
    private const PERMISSIONS = [
        // ─── Back-office administration ───
        'admin.access'           => ['administrateur'],
        'admin.users'            => ['administrateur'],
        'admin.users.create'     => ['administrateur'],
        'admin.users.delete'     => ['administrateur'],
        'admin.users.import'     => ['administrateur'],
        'admin.scolaire'         => ['administrateur'],
        'admin.modules'          => ['administrateur'],
        'admin.systeme'          => ['administrateur'],
        'admin.etablissement'    => ['administrateur'],
        'admin.messagerie'       => ['administrateur'],
        'admin.classes'          => ['administrateur'],

        // ─── Notes ───
        'notes.view'             => ['administrateur', 'professeur', 'vie_scolaire', 'eleve', 'parent'],
        'notes.manage'           => ['administrateur', 'professeur', 'vie_scolaire'],
        'notes.edit'             => ['administrateur', 'professeur'],
        'notes.delete'           => ['administrateur'],
        'notes.lock'             => ['administrateur'],

        // ─── Absences ───
        'absences.view'          => ['administrateur', 'professeur', 'vie_scolaire', 'eleve', 'parent'],
        'absences.manage'        => ['administrateur', 'professeur', 'vie_scolaire'],
        'absences.validate'      => ['administrateur', 'vie_scolaire'],
        'absences.justify'       => ['eleve', 'parent'],
        'absences.stats'         => ['administrateur', 'vie_scolaire'],
        'absences.export'        => ['administrateur', 'vie_scolaire'],

        // ─── Appel ───
        'appel.view'             => ['administrateur', 'professeur', 'vie_scolaire'],
        'appel.manage'           => ['administrateur', 'professeur', 'vie_scolaire'],
        'appel.correction'       => ['administrateur', 'professeur'],

        // ─── Devoirs / Rendus ───
        'devoirs.view'           => ['administrateur', 'professeur', 'eleve', 'parent'],
        'devoirs.manage'         => ['administrateur', 'professeur'],
        'devoirs.submit'         => ['eleve'],
        'devoirs.correct'        => ['administrateur', 'professeur'],

        // ─── Emploi du temps ───
        'edt.view'               => ['administrateur', 'professeur', 'vie_scolaire', 'eleve', 'parent'],
        'edt.manage'             => ['administrateur', 'vie_scolaire'],

        // ─── Discipline ───
        'discipline.view'        => ['administrateur', 'vie_scolaire', 'professeur'],
        'discipline.manage'      => ['administrateur', 'vie_scolaire'],
        'discipline.signal'      => ['administrateur', 'professeur', 'vie_scolaire'],

        // ─── Bulletins ───
        'bulletins.view'         => ['administrateur', 'professeur', 'vie_scolaire', 'eleve', 'parent'],
        'bulletins.manage'       => ['administrateur', 'professeur', 'vie_scolaire'],
        'bulletins.generate'     => ['administrateur', 'vie_scolaire'],

        // ─── Compétences ───
        'competences.view'       => ['administrateur', 'professeur', 'vie_scolaire', 'eleve', 'parent'],
        'competences.manage'     => ['administrateur', 'professeur'],

        // ─── Annonces / Sondages ───
        'annonces.view'          => ['administrateur', 'professeur', 'vie_scolaire', 'eleve', 'parent'],
        'annonces.manage'        => ['administrateur', 'professeur', 'vie_scolaire'],

        // ─── Agenda / Événements ───
        'agenda.view'            => ['administrateur', 'professeur', 'vie_scolaire', 'eleve', 'parent'],
        'agenda.manage'          => ['administrateur', 'professeur', 'vie_scolaire'],

        // ─── Messagerie ───
        'messagerie.view'        => ['administrateur', 'professeur', 'vie_scolaire', 'eleve', 'parent'],
        'messagerie.send'        => ['administrateur', 'professeur', 'vie_scolaire', 'eleve', 'parent'],

        // ─── Documents ───
        'documents.view'         => ['administrateur', 'professeur', 'vie_scolaire', 'eleve', 'parent'],
        'documents.manage'       => ['administrateur', 'professeur', 'vie_scolaire'],

        // ─── Cahier de textes ───
        'cahierdetextes.view'    => ['administrateur', 'professeur', 'vie_scolaire', 'eleve', 'parent'],
        'cahierdetextes.manage'  => ['administrateur', 'professeur'],

        // ─── Réunions ───
        'reunions.view'          => ['administrateur', 'professeur', 'vie_scolaire', 'parent'],
        'reunions.manage'        => ['administrateur', 'vie_scolaire', 'professeur'],
        'reunions.reserve'       => ['parent'],

        // ─── Inscriptions ───
        'inscriptions.view'      => ['administrateur', 'vie_scolaire'],
        'inscriptions.manage'    => ['administrateur', 'vie_scolaire'],

        // ─── Orientation ───
        'orientation.view'       => ['administrateur', 'professeur', 'vie_scolaire', 'eleve', 'parent'],
        'orientation.manage'     => ['administrateur', 'professeur', 'vie_scolaire'],

        // ─── Signalements ───
        'signalements.view'      => ['administrateur', 'vie_scolaire'],
        'signalements.manage'    => ['administrateur', 'vie_scolaire'],
        'signalements.create'    => ['administrateur', 'professeur', 'vie_scolaire', 'eleve'],

        // ─── Bibliothèque ───
        'bibliotheque.view'      => ['administrateur', 'professeur', 'vie_scolaire', 'eleve', 'parent'],
        'bibliotheque.manage'    => ['administrateur', 'vie_scolaire'],
        'bibliotheque.borrow'    => ['eleve', 'professeur'],

        // ─── Clubs ───
        'clubs.view'             => ['administrateur', 'professeur', 'vie_scolaire', 'eleve'],
        'clubs.manage'           => ['administrateur', 'vie_scolaire', 'professeur'],
        'clubs.join'             => ['eleve'],

        // ─── Infirmerie ───
        'infirmerie.view'        => ['administrateur', 'vie_scolaire'],
        'infirmerie.manage'      => ['administrateur', 'vie_scolaire'],

        // ─── Support ───
        'support.view'           => ['administrateur', 'professeur', 'vie_scolaire', 'eleve', 'parent'],
        'support.manage'         => ['administrateur', 'vie_scolaire'],
        'support.create'         => ['administrateur', 'professeur', 'vie_scolaire', 'eleve', 'parent'],

        // ─── Examens ───
        'examens.view'           => ['administrateur', 'vie_scolaire', 'professeur', 'eleve'],
        'examens.manage'         => ['administrateur', 'vie_scolaire'],

        // ─── Ressources ───
        'ressources.view'        => ['administrateur', 'professeur', 'vie_scolaire', 'eleve'],
        'ressources.manage'      => ['administrateur', 'professeur'],

        // ─── Stages ───
        'stages.view'            => ['administrateur', 'professeur', 'vie_scolaire', 'eleve', 'parent'],
        'stages.manage'          => ['administrateur', 'vie_scolaire', 'professeur'],

        // ─── Facturation ───
        'facturation.view'       => ['administrateur', 'vie_scolaire', 'parent'],
        'facturation.manage'     => ['administrateur', 'vie_scolaire'],

        // ─── Cantine ───
        'cantine.view'           => ['administrateur', 'vie_scolaire', 'eleve', 'parent'],
        'cantine.manage'         => ['administrateur', 'vie_scolaire'],
        'cantine.reserve'        => ['parent', 'eleve'],

        // ─── Salles / Matériels ───
        'salles.view'            => ['administrateur', 'vie_scolaire', 'professeur'],
        'salles.manage'          => ['administrateur', 'vie_scolaire'],
        'salles.reserve'         => ['administrateur', 'vie_scolaire', 'professeur'],

        // ─── Périscolaire / Garderie ───
        'periscolaire.view'      => ['administrateur', 'vie_scolaire', 'parent'],
        'periscolaire.manage'    => ['administrateur', 'vie_scolaire'],

        // ─── Personnel ───
        'personnel.view'         => ['administrateur', 'vie_scolaire'],
        'personnel.manage'       => ['administrateur', 'vie_scolaire'],

        // ─── Transports ───
        'transports.view'        => ['administrateur', 'vie_scolaire', 'parent'],
        'transports.manage'      => ['administrateur', 'vie_scolaire'],

        // ─── Diplômes ───
        'diplomes.view'          => ['administrateur', 'vie_scolaire', 'eleve', 'parent'],
        'diplomes.manage'        => ['administrateur', 'vie_scolaire'],

        // ─── Archivage ───
        'archivage.view'         => ['administrateur'],
        'archivage.manage'       => ['administrateur'],

        // ─── Trombinoscope ───
        'trombinoscope.view'     => ['administrateur', 'professeur', 'vie_scolaire'],

        // ─── Reporting ───
        'reporting.view'         => ['administrateur', 'professeur', 'vie_scolaire'],
        'reporting.export'       => ['administrateur', 'vie_scolaire'],

        // ─── RGPD ───
        'rgpd.view'              => ['administrateur'],
        'rgpd.manage'            => ['administrateur'],
        'rgpd.my_data'           => ['administrateur', 'professeur', 'vie_scolaire', 'eleve', 'parent'],

        // ─── Vie scolaire (dashboard) ───
        'vie_scolaire.view'      => ['administrateur', 'vie_scolaire'],
        'vie_scolaire.manage'    => ['administrateur', 'vie_scolaire'],

        // ─── Notifications ───
        'notifications.view'     => ['administrateur', 'professeur', 'vie_scolaire', 'eleve', 'parent'],

        // ─── Paramètres ───
        'parametres.view'        => ['administrateur', 'professeur', 'vie_scolaire', 'eleve', 'parent'],

        // ─── Projets pédagogiques ───
        'projets.view'           => ['administrateur', 'professeur', 'vie_scolaire'],
        'projets.manage'         => ['administrateur', 'professeur'],

        // ─── Parcours éducatifs ───
        'parcours.view'          => ['administrateur', 'professeur', 'vie_scolaire', 'eleve', 'parent'],
        'parcours.manage'        => ['administrateur', 'professeur'],

        // ─── Besoins éducatifs ───
        'besoins.view'           => ['administrateur', 'professeur', 'vie_scolaire', 'parent'],
        'besoins.manage'         => ['administrateur', 'vie_scolaire', 'professeur'],

        // ─── Internat ───
        'internat.view'          => ['administrateur', 'vie_scolaire'],
        'internat.manage'        => ['administrateur', 'vie_scolaire'],

        // ─── Vie associative ───
        'vie_associative.view'   => ['administrateur', 'vie_scolaire', 'eleve'],
        'vie_associative.manage' => ['administrateur', 'vie_scolaire'],
    ];

    // ─── Hiérarchie des rôles (un rôle hérite des permissions inférieures) ───
    private const ROLE_HIERARCHY = [
        'administrateur' => ['vie_scolaire', 'professeur'],
        'vie_scolaire'   => [],
        'professeur'     => [],
        'parent'         => [],
        'eleve'          => [],
    ];

    // ─── Rôles autorisés à accéder au back-office admin ───
    private const ADMIN_ROLES = ['administrateur'];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Définit l'utilisateur courant
     */
    public function setUser(?array $user): void
    {
        $this->currentUser = $user;
        $this->currentRole = $user['type'] ?? $user['profil'] ?? null;
        $this->cachedPermissions = [];
    }

    /**
     * Vérifie si l'utilisateur courant a une permission donnée.
     * Prend en compte la hiérarchie des rôles : un administrateur hérite
     * des permissions de vie_scolaire et professeur.
     */
    public function can(string $permission): bool
    {
        if (!$this->currentRole) {
            return false;
        }

        // Cache
        if (isset($this->cachedPermissions[$permission])) {
            return $this->cachedPermissions[$permission];
        }

        // Résoudre tous les rôles effectifs (rôle courant + rôles hérités)
        $effectiveRoles = $this->resolveRoles($this->currentRole);

        // 1) Vérifier dans la matrice statique
        $allowed = self::PERMISSIONS[$permission] ?? null;
        if ($allowed !== null) {
            $result = !empty(array_intersect($effectiveRoles, $allowed));
            $this->cachedPermissions[$permission] = $result;
            return $result;
        }

        // 2) Vérifier les permissions dynamiques (DB) – table rbac_permissions
        $result = $this->checkDynamicPermission($permission);
        $this->cachedPermissions[$permission] = $result;
        return $result;
    }

    /**
     * Résout la hiérarchie des rôles : retourne le rôle + tous les rôles hérités (récursif).
     */
    private function resolveRoles(string $role): array
    {
        $roles = [$role];
        $children = self::ROLE_HIERARCHY[$role] ?? [];
        foreach ($children as $child) {
            $roles = array_merge($roles, $this->resolveRoles($child));
        }
        return array_unique($roles);
    }

    /**
     * Vérifie une permission OU lève une exception HTTP 403
     */
    public function authorize(string $permission): void
    {
        if (!$this->can($permission)) {
            $this->denyAccess($permission);
        }
    }

    /**
     * Vérifie au moins une permission parmi la liste
     */
    public function canAny(array $permissions): bool
    {
        foreach ($permissions as $p) {
            if ($this->can($p)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Vérifie TOUTES les permissions
     */
    public function canAll(array $permissions): bool
    {
        foreach ($permissions as $p) {
            if (!$this->can($p)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Vérifie si le rôle actuel peut accéder au back-office admin
     */
    public function isAdminRole(): bool
    {
        return in_array($this->currentRole, self::ADMIN_ROLES, true);
    }

    /**
     * Bloque l'accès au back-office si non-admin
     */
    public function requireAdmin(): void
    {
        if (!$this->isAdminRole()) {
            $this->denyAccess('admin.access');
        }
    }

    /**
     * Vérifie qu'un rôle spécifique est actif
     */
    public function requireRole(string ...$roles): void
    {
        if (!in_array($this->currentRole, $roles, true)) {
            $this->denyAccess('role:' . implode(',', $roles));
        }
    }

    /**
     * Retourne le rôle courant
     */
    public function getRole(): ?string
    {
        return $this->currentRole;
    }

    /**
     * Retourne toutes les permissions pour un rôle donné
     */
    public function getPermissionsForRole(string $role): array
    {
        $perms = [];
        foreach (self::PERMISSIONS as $perm => $roles) {
            if (in_array($role, $roles, true)) {
                $perms[] = $perm;
            }
        }
        return $perms;
    }

    /**
     * Retourne la matrice complète (pour l'admin UI)
     */
    public function getFullMatrix(): array
    {
        return self::PERMISSIONS;
    }

    /**
     * Retourne les rôles disponibles
     */
    public static function getRoles(): array
    {
        return ['administrateur', 'vie_scolaire', 'professeur', 'parent', 'eleve'];
    }

    /**
     * Retourne les labels des rôles en français
     */
    public static function getRoleLabels(): array
    {
        return [
            'administrateur' => 'Administrateur',
            'vie_scolaire'   => 'Vie scolaire',
            'professeur'     => 'Professeur',
            'parent'         => 'Parent',
            'eleve'          => 'Élève',
        ];
    }

    // ───────────── PERMISSIONS MODULE CRUD ─────────────

    /**
     * Vérifie une permission CRUD sur un module.
     * Ex: canModule('messagerie', 'send'), canModule('notes', 'create')
     */
    public function canModule(string $moduleKey, string $action = 'view'): bool
    {
        if (!$this->currentRole) return false;

        $cacheKey = "module.{$moduleKey}.{$action}";
        if (isset($this->cachedPermissions[$cacheKey])) {
            return $this->cachedPermissions[$cacheKey];
        }

        try {
            // Actions standard → colonnes directes
            $standardActions = ['view', 'create', 'edit', 'delete', 'export', 'import'];

            if (in_array($action, $standardActions, true)) {
                $column = "can_{$action}";
                $stmt = $this->pdo->prepare("
                    SELECT `{$column}` FROM module_permissions
                    WHERE module_key = ? AND role = ?
                    LIMIT 1
                ");
                $stmt->execute([$moduleKey, $this->currentRole]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);

                // Si pas de ligne → fallback sur la permission statique
                if ($row === false) {
                    $result = $this->can("{$moduleKey}.{$action}") || $this->can("{$moduleKey}.manage");
                } else {
                    $result = (bool)$row[$column];
                }
            } else {
                // Action custom → chercher dans custom_permissions JSON
                $stmt = $this->pdo->prepare("
                    SELECT custom_permissions FROM module_permissions
                    WHERE module_key = ? AND role = ?
                    LIMIT 1
                ");
                $stmt->execute([$moduleKey, $this->currentRole]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($row === false || $row['custom_permissions'] === null) {
                    $result = false;
                } else {
                    $custom = json_decode($row['custom_permissions'], true) ?? [];
                    $result = !empty($custom["can_{$action}"]);
                }
            }
        } catch (\PDOException $e) {
            // Table n'existe pas encore → fallback
            $result = $this->can("{$moduleKey}.{$action}") || $this->can("{$moduleKey}.manage");
        }

        $this->cachedPermissions[$cacheKey] = $result;
        return $result;
    }

    /**
     * Récupère toutes les permissions de module pour un rôle donné.
     */
    public function getModulePermissions(string $role): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT mp.*, mc.label as module_label, mc.category
                FROM module_permissions mp
                JOIN modules_config mc ON mc.module_key = mp.module_key
                WHERE mp.role = ?
                ORDER BY mc.sort_order
            ");
            $stmt->execute([$role]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            return [];
        }
    }

    /**
     * Récupère toutes les permissions de module (matrice complète).
     */
    public function getAllModulePermissions(): array
    {
        try {
            $stmt = $this->pdo->query("
                SELECT mp.*, mc.label as module_label, mc.category, mc.icon
                FROM module_permissions mp
                JOIN modules_config mc ON mc.module_key = mp.module_key
                ORDER BY mc.sort_order, mp.role
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            return [];
        }
    }

    /**
     * Met à jour les permissions CRUD d'un module pour un rôle.
     */
    public function setModulePermission(string $moduleKey, string $role, array $permissions): bool
    {
        try {
            $customJson = null;
            if (!empty($permissions['custom'])) {
                $customJson = json_encode($permissions['custom']);
            }

            $stmt = $this->pdo->prepare("
                INSERT INTO module_permissions (module_key, role, can_view, can_create, can_edit, can_delete, can_export, can_import, custom_permissions)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    can_view = VALUES(can_view),
                    can_create = VALUES(can_create),
                    can_edit = VALUES(can_edit),
                    can_delete = VALUES(can_delete),
                    can_export = VALUES(can_export),
                    can_import = VALUES(can_import),
                    custom_permissions = VALUES(custom_permissions),
                    updated_at = NOW()
            ");

            $result = $stmt->execute([
                $moduleKey,
                $role,
                (int)($permissions['view'] ?? 0),
                (int)($permissions['create'] ?? 0),
                (int)($permissions['edit'] ?? 0),
                (int)($permissions['delete'] ?? 0),
                (int)($permissions['export'] ?? 0),
                (int)($permissions['import'] ?? 0),
                $customJson,
            ]);

            // Clear cache for this module/role
            foreach ($this->cachedPermissions as $key => $v) {
                if (str_starts_with($key, "module.{$moduleKey}.")) {
                    unset($this->cachedPermissions[$key]);
                }
            }

            return $result;
        } catch (\PDOException $e) {
            error_log("RBAC::setModulePermission error: " . $e->getMessage());
            return false;
        }
    }

    // ───────────── PERMISSIONS DYNAMIQUES (DB) ─────────────

    /**
     * Vérifie une permission dynamique en base de données
     */
    private function checkDynamicPermission(string $permission): bool
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 1 FROM rbac_permissions 
                WHERE role = ? AND permission = ? AND granted = 1
                LIMIT 1
            ");
            $stmt->execute([$this->currentRole, $permission]);
            return (bool) $stmt->fetchColumn();
        } catch (\PDOException $e) {
            // Table n'existe pas encore → fallback false
            return false;
        }
    }

    /**
     * Ajoute ou met à jour une permission dynamique
     */
    public function grantPermission(string $role, string $permission): bool
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO rbac_permissions (role, permission, granted, created_at)
                VALUES (?, ?, 1, NOW())
                ON DUPLICATE KEY UPDATE granted = 1, updated_at = NOW()
            ");
            return $stmt->execute([$role, $permission]);
        } catch (\PDOException $e) {
            error_log("RBAC::grantPermission error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Révoque une permission dynamique
     */
    public function revokePermission(string $role, string $permission): bool
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO rbac_permissions (role, permission, granted, created_at)
                VALUES (?, ?, 0, NOW())
                ON DUPLICATE KEY UPDATE granted = 0, updated_at = NOW()
            ");
            return $stmt->execute([$role, $permission]);
        } catch (\PDOException $e) {
            error_log("RBAC::revokePermission error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Récupère toutes les permissions dynamiques
     */
    public function getDynamicPermissions(): array
    {
        try {
            $stmt = $this->pdo->query("SELECT role, permission, granted FROM rbac_permissions ORDER BY role, permission");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            return [];
        }
    }

    // ───────────── ACCESS DENIED ─────────────

    /**
     * Refuse l'accès — redirige ou retourne JSON 403
     */
    private function denyAccess(string $permission): void
    {
        $this->logAccessDenied($permission);

        // API JSON request → réponse JSON
        if ($this->isJsonRequest()) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'error'   => true,
                'message' => 'Accès refusé',
                'code'    => 403
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Requête web → redirection avec flash message
        $_SESSION['error_message'] = 'Vous n\'avez pas les droits nécessaires pour accéder à cette page.';
        $redirect = ($this->currentUser)
            ? (defined('BASE_URL') ? BASE_URL : '') . '/accueil/accueil.php'
            : (defined('BASE_URL') ? BASE_URL : '') . '/login/index.php';
        header('Location: ' . $redirect);
        exit;
    }

    /**
     * Journalise un refus d'accès
     */
    private function logAccessDenied(string $permission): void
    {
        $userId = $this->currentUser['id'] ?? 'anonymous';
        $role   = $this->currentRole ?? 'none';
        $ip     = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $uri    = $_SERVER['REQUEST_URI'] ?? 'unknown';

        error_log(sprintf(
            "RBAC ACCESS DENIED: user=%s role=%s permission=%s uri=%s ip=%s",
            $userId, $role, $permission, $uri, $ip
        ));

        // Audit en base
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO audit_log (action, model, model_id, user_id, user_type, ip_address, user_agent, old_values)
                VALUES ('access_denied', 'rbac', NULL, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $this->currentUser['id'] ?? null,
                $this->currentRole ?? 'anonymous',
                $ip,
                $_SERVER['HTTP_USER_AGENT'] ?? null,
                json_encode(['permission' => $permission, 'uri' => $uri])
            ]);
        } catch (\PDOException $e) {
            // Échec silent – déjà loggé via error_log
        }
    }

    /**
     * Détecte si la requête attend du JSON
     */
    private function isJsonRequest(): bool
    {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        $xhr = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';

        return str_contains($accept, 'application/json')
            || str_contains($contentType, 'application/json')
            || strtolower($xhr) === 'xmlhttprequest';
    }
}
