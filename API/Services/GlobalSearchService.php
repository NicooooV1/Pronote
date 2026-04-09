<?php
declare(strict_types=1);

namespace API\Services;

use PDO;

/**
 * GlobalSearchService — Cross-module search across the entire application.
 *
 * Searches students, staff, notes, messages, announcements, documents, and events
 * with relevance scoring and role-based filtering.
 */
class GlobalSearchService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Search across all modules.
     *
     * @param string $query      Search term (min 2 chars)
     * @param string $userType   Role of the searching user
     * @param int    $limit      Max results per category
     * @return array Grouped results by category
     */
    public function search(string $query, string $userType, int $limit = 10): array
    {
        $query = trim($query);
        if (mb_strlen($query) < 2) {
            return [];
        }

        $like = '%' . $query . '%';
        $results = [];

        // Students (accessible by admin, professeur, vie_scolaire)
        if (in_array($userType, ['administrateur', 'professeur', 'vie_scolaire'])) {
            $results['eleves'] = $this->searchEleves($like, $limit);
        }

        // Staff (admin only)
        if ($userType === 'administrateur') {
            $results['personnel'] = $this->searchPersonnel($like, $limit);
        }

        // Announcements (all roles)
        $results['annonces'] = $this->searchAnnonces($like, $limit);

        // Documents (all roles)
        $results['documents'] = $this->searchDocuments($like, $limit);

        // Events (all roles)
        $results['evenements'] = $this->searchEvenements($like, $limit);

        // Modules (admin)
        if ($userType === 'administrateur') {
            $results['modules'] = $this->searchModules($like, $limit);
        }

        return $results;
    }

    private function searchEleves(string $like, int $limit): array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, nom, prenom, classe, 'eleve' AS type,
                   CONCAT(prenom, ' ', nom) AS label
            FROM eleves
            WHERE nom LIKE :q OR prenom LIKE :q2 OR classe LIKE :q3
            ORDER BY nom, prenom
            LIMIT :lim
        ");
        $stmt->bindValue(':q', $like);
        $stmt->bindValue(':q2', $like);
        $stmt->bindValue(':q3', $like);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function searchPersonnel(string $like, int $limit): array
    {
        $results = [];
        foreach (['professeurs', 'administrateurs', 'vie_scolaire'] as $table) {
            $stmt = $this->pdo->prepare("
                SELECT id, nom, prenom, '{$table}' AS type,
                       CONCAT(prenom, ' ', nom) AS label
                FROM {$table}
                WHERE nom LIKE :q OR prenom LIKE :q2
                ORDER BY nom, prenom
                LIMIT :lim
            ");
            $stmt->bindValue(':q', $like);
            $stmt->bindValue(':q2', $like);
            $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $results = array_merge($results, $stmt->fetchAll(PDO::FETCH_ASSOC));
        }
        return array_slice($results, 0, $limit);
    }

    private function searchAnnonces(string $like, int $limit): array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, titre AS label, 'annonce' AS type, date_publication
            FROM annonces
            WHERE titre LIKE :q OR contenu LIKE :q2
            ORDER BY date_publication DESC
            LIMIT :lim
        ");
        $stmt->bindValue(':q', $like);
        $stmt->bindValue(':q2', $like);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function searchDocuments(string $like, int $limit): array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, titre AS label, 'document' AS type, categorie
            FROM documents
            WHERE titre LIKE :q OR description LIKE :q2
            ORDER BY created_at DESC
            LIMIT :lim
        ");
        $stmt->bindValue(':q', $like);
        $stmt->bindValue(':q2', $like);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function searchEvenements(string $like, int $limit): array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, titre AS label, 'evenement' AS type, date_debut
            FROM evenements
            WHERE titre LIKE :q OR description LIKE :q2
            ORDER BY date_debut DESC
            LIMIT :lim
        ");
        $stmt->bindValue(':q', $like);
        $stmt->bindValue(':q2', $like);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function searchModules(string $like, int $limit): array
    {
        $stmt = $this->pdo->prepare("
            SELECT module_key AS id, label, 'module' AS type, category
            FROM modules_config
            WHERE label LIKE :q OR description LIKE :q2
            LIMIT :lim
        ");
        $stmt->bindValue(':q', $like);
        $stmt->bindValue(':q2', $like);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
