<?php
declare(strict_types=1);

namespace API\Services;

use PDO;

/**
 * Manages super-administrators who operate across all establishments.
 */
class SuperAdminService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Authenticate a super-admin by login + password.
     * Returns the user array or null.
     */
    public function authenticate(string $login, string $password): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM super_admins
            WHERE (identifiant = ? OR mail = ?) AND actif = 1
            LIMIT 1
        ");
        $stmt->execute([$login, $login]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($password, $user['mot_de_passe'])) {
            return null;
        }

        // Update last_login
        $this->pdo->prepare("UPDATE super_admins SET last_login = NOW() WHERE id = ?")
            ->execute([$user['id']]);

        unset($user['mot_de_passe']);
        $user['type'] = 'super_admin';
        return $user;
    }

    /**
     * Get all super-admins.
     */
    public function getAll(): array
    {
        $stmt = $this->pdo->query("
            SELECT id, nom, prenom, mail, identifiant, actif, date_creation, last_login
            FROM super_admins ORDER BY nom, prenom
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get a super-admin by ID.
     */
    public function getById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, nom, prenom, mail, identifiant, actif, date_creation, last_login
            FROM super_admins WHERE id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Create a new super-admin.
     */
    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO super_admins (nom, prenom, mail, identifiant, mot_de_passe)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['nom'],
            $data['prenom'],
            $data['mail'],
            $data['identifiant'],
            password_hash($data['mot_de_passe'], PASSWORD_DEFAULT),
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Update a super-admin.
     */
    public function update(int $id, array $data): bool
    {
        $fields = [];
        $values = [];

        foreach (['nom', 'prenom', 'mail', 'identifiant', 'actif'] as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "`{$field}` = ?";
                $values[] = $data[$field];
            }
        }

        if (isset($data['mot_de_passe']) && $data['mot_de_passe'] !== '') {
            $fields[] = '`mot_de_passe` = ?';
            $values[] = password_hash($data['mot_de_passe'], PASSWORD_DEFAULT);
        }

        if (empty($fields)) {
            return false;
        }

        $values[] = $id;
        $stmt = $this->pdo->prepare("UPDATE super_admins SET " . implode(', ', $fields) . " WHERE id = ?");
        return $stmt->execute($values);
    }

    /**
     * Delete a super-admin (soft: deactivate).
     */
    public function deactivate(int $id): bool
    {
        $stmt = $this->pdo->prepare("UPDATE super_admins SET actif = 0 WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /**
     * List all establishments (cross-establishment view).
     */
    public function getEstablishments(): array
    {
        $stmt = $this->pdo->query("
            SELECT e.*,
                   (SELECT COUNT(*) FROM administrateurs a WHERE a.etablissement_id = e.id) AS admin_count,
                   (SELECT COUNT(*) FROM eleves el WHERE el.etablissement_id = e.id) AS student_count,
                   (SELECT COUNT(*) FROM professeurs p WHERE p.etablissement_id = e.id) AS teacher_count
            FROM etablissements e
            ORDER BY e.nom
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Check if a user is a super-admin (by session).
     */
    public static function isSuperAdmin(): bool
    {
        return ($_SESSION['user_type'] ?? '') === 'super_admin';
    }
}
