<?php
/**
 * Legacy User class — Façade pour le panneau d'administration
 *
 * Enveloppe UserService pour offrir l'interface attendue par les pages admin
 * qui utilisent `new User($pdo)`. Cette classe n'est PAS un modèle ORM,
 * c'est un adaptateur vers le service centralisé.
 */

class User
{
    private PDO $pdo;
    private \API\Services\UserService $service;
    private string $lastError = '';

    private static array $tableMap = [
        'eleve'          => 'eleves',
        'parent'         => 'parents',
        'professeur'     => 'professeurs',
        'vie_scolaire'   => 'vie_scolaire',
        'administrateur' => 'administrateurs',
    ];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->service = function_exists('app') ? app('API\Services\UserService') : new \API\Services\UserService($pdo);
    }

    /* ──────────────────────── CRUD ──────────────────────── */

    /**
     * Crée un utilisateur.
     * @return array ['success' => bool, 'identifiant' => string, 'password' => string, ...]
     */
    public function createUser(string $profil, array $data): array
    {
        $result = $this->service->create($profil, $data);
        if (!$result['success']) {
            $this->lastError = $result['message'] ?? 'Erreur inconnue';
        }
        return $result;
    }

    /**
     * Récupère un utilisateur par son ID et son type.
     */
    public function getById(int $id, string $type): ?array
    {
        return $this->service->findById($id, $type);
    }

    /**
     * Supprime un utilisateur (soft-delete : actif = 0).
     */
    public function delete(string $profil, int $userId): bool
    {
        $table = self::getTableName($profil);
        if (!$table) {
            $this->lastError = 'Profil invalide.';
            return false;
        }

        try {
            $stmt = $this->pdo->prepare("UPDATE `{$table}` SET actif = 0 WHERE id = ?");
            $stmt->execute([$userId]);
            return $stmt->rowCount() > 0;
        } catch (\PDOException $e) {
            $this->lastError = 'Erreur lors de la suppression : ' . $e->getMessage();
            error_log('User::delete error: ' . $e->getMessage());
            return false;
        }
    }

    /* ──────────────────── MOT DE PASSE ──────────────────── */

    /**
     * Change le mot de passe d'un utilisateur.
     * @param string $profil   Type de profil (eleve, professeur, etc.)
     * @param int    $userId   ID de l'utilisateur
     * @param string $newPassword Nouveau mot de passe en clair
     * @return bool
     */
    public function changePassword(string $profil, int $userId, string $newPassword): bool
    {
        $result = $this->service->changePassword($userId, $newPassword, $profil);
        if (!$result['success']) {
            $this->lastError = $result['message'] ?? 'Erreur inconnue';
            return false;
        }
        return true;
    }

    /* ──────────────────── RECHERCHE ──────────────────────── */

    /**
     * Recherche des utilisateurs dans toutes les tables.
     * @return array Liste d'utilisateurs correspondants
     */
    public function searchUsers(string $term): array
    {
        $results = [];
        $term = '%' . trim($term) . '%';

        foreach (self::$tableMap as $profil => $table) {
            try {
                $stmt = $this->pdo->prepare(
                    "SELECT id, identifiant, nom, prenom, mail, '{$profil}' AS type
                     FROM `{$table}`
                     WHERE actif = 1
                       AND (identifiant LIKE ? OR nom LIKE ? OR prenom LIKE ? OR mail LIKE ?)
                     LIMIT 20"
                );
                $stmt->execute([$term, $term, $term, $term]);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($rows as $row) {
                    $results[] = $row;
                }
            } catch (\PDOException $e) {
                // Table peut ne pas exister — continuer
                continue;
            }
        }

        return $results;
    }

    /* ──────────────────── UTILITAIRES ──────────────────────── */

    /**
     * Retourne le nom de table pour un profil.
     */
    public static function getTableName(string $profil): ?string
    {
        return self::$tableMap[$profil] ?? null;
    }

    /**
     * Dernier message d'erreur.
     */
    public function getErrorMessage(): string
    {
        return $this->lastError;
    }
}
