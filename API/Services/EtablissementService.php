<?php
namespace API\Services;

use PDO;
use API\Core\EstablishmentContext;

/**
 * Service de gestion des etablissements.
 * Multi-etablissement : utilise EstablishmentContext pour scoper les requetes.
 */
class EtablissementService
{
    protected $pdo;
    protected $cache = [];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Recupere l'etablissement courant.
     */
    public function getCurrent(): ?array
    {
        return $this->getById(EstablishmentContext::id());
    }

    /**
     * Recupere un etablissement par ID.
     */
    public function getById(int $id): ?array
    {
        $key = 'etab_' . $id;
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        try {
            $stmt = $this->pdo->prepare("SELECT * FROM etablissements WHERE id = ?");
            $stmt->execute([$id]);
            $info = $stmt->fetch(PDO::FETCH_ASSOC);
            $this->cache[$key] = $info ?: null;
            return $this->cache[$key];
        } catch (\PDOException $e) {
            return null;
        }
    }

    /**
     * Alias legacy — retourne les infos de l'etablissement courant.
     */
    public function getInfo(): ?array
    {
        return $this->getCurrent();
    }

    /**
     * Recupere tous les etablissements actifs.
     */
    public function getAll(bool $activeOnly = true): array
    {
        try {
            $sql = "SELECT * FROM etablissements";
            if ($activeOnly) {
                $sql .= " WHERE actif = 1";
            }
            $sql .= " ORDER BY nom";
            return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            return [];
        }
    }

    /**
     * Cree un nouvel etablissement.
     */
    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO etablissements (nom, code, type, adresse, code_postal, ville, telephone, fax, email,
                                        chef_etablissement, academie, code_uai, annee_scolaire, default_locale)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['nom'] ?? 'Nouvel Etablissement',
            $data['code'] ?? 'etab-' . bin2hex(random_bytes(4)),
            $data['type'] ?? 'college',
            $data['adresse'] ?? null,
            $data['code_postal'] ?? null,
            $data['ville'] ?? null,
            $data['telephone'] ?? null,
            $data['fax'] ?? null,
            $data['email'] ?? null,
            $data['chef_etablissement'] ?? null,
            $data['academie'] ?? null,
            $data['code_uai'] ?? null,
            $data['annee_scolaire'] ?? date('Y') . '-' . (date('Y') + 1),
            $data['default_locale'] ?? 'fr',
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Met a jour un etablissement.
     */
    public function update(int $id, array $data): bool
    {
        $allowed = ['nom', 'code', 'type', 'adresse', 'code_postal', 'ville', 'telephone', 'fax',
                     'email', 'chef_etablissement', 'academie', 'code_uai', 'annee_scolaire',
                     'logo', 'couleur_primaire', 'couleur_secondaire', 'css_personnalise',
                     'favicon', 'pied_de_page', 'default_locale', 'actif'];

        $fields = [];
        $values = [];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "`{$field}` = ?";
                $values[] = $data[$field];
            }
        }

        if (empty($fields)) {
            return false;
        }

        $values[] = $id;
        $stmt = $this->pdo->prepare("UPDATE etablissements SET " . implode(', ', $fields) . " WHERE id = ?");
        $result = $stmt->execute($values);

        unset($this->cache['etab_' . $id]);
        return $result;
    }

    /**
     * Desactive un etablissement (soft delete).
     */
    public function deactivate(int $id): bool
    {
        return $this->update($id, ['actif' => 0]);
    }

    // ──── Donnees scolaires scopees ────────────────────────────────

    /**
     * Recupere toutes les donnees de l'etablissement courant.
     */
    public function getData(): array
    {
        return [
            'info' => $this->getCurrent(),
            'classes' => $this->getClasses(),
            'matieres' => $this->getMatieres(),
            'periodes' => $this->getPeriodes(),
        ];
    }

    public function getClasses(): array
    {
        if (isset($this->cache['classes'])) {
            return $this->cache['classes'];
        }

        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM classes
                WHERE etablissement_id = ?
                ORDER BY niveau, nom
            ");
            $stmt->execute([EstablishmentContext::id()]);
            $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            $classes = [];
        }

        $organized = [];
        foreach ($classes as $classe) {
            $niveau = $classe['niveau'];
            if (!isset($organized[$niveau])) {
                $organized[$niveau] = [];
            }
            $organized[$niveau][] = $classe['nom'];
        }

        $this->cache['classes'] = $organized;
        return $organized;
    }

    public function getMatieres(): array
    {
        if (isset($this->cache['matieres'])) {
            return $this->cache['matieres'];
        }

        try {
            $stmt = $this->pdo->prepare("
                SELECT code, nom, couleur
                FROM matieres
                WHERE etablissement_id = ?
                ORDER BY nom
            ");
            $stmt->execute([EstablishmentContext::id()]);
            $this->cache['matieres'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            $this->cache['matieres'] = [];
        }
        return $this->cache['matieres'];
    }

    public function getPeriodes(): array
    {
        if (isset($this->cache['periodes'])) {
            return $this->cache['periodes'];
        }

        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM periodes
                WHERE etablissement_id = ?
                ORDER BY date_debut
            ");
            $stmt->execute([EstablishmentContext::id()]);
            $this->cache['periodes'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            $this->cache['periodes'] = [];
        }
        return $this->cache['periodes'];
    }

    /**
     * Met a jour les informations de l'etablissement courant.
     */
    public function updateInfo(array $data): bool
    {
        return $this->update(EstablishmentContext::id(), $data);
    }

    public function addClasse(string $niveau, string $nom): bool
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO classes (niveau, nom, annee_scolaire, etablissement_id)
                VALUES (?, ?, ?, ?)
            ");
            $etab = $this->getCurrent();
            $result = $stmt->execute([$niveau, $nom, $etab['annee_scolaire'] ?? date('Y') . '-' . (date('Y') + 1), EstablishmentContext::id()]);
            unset($this->cache['classes']);
            return $result;
        } catch (\PDOException $e) {
            return false;
        }
    }

    public function deleteClasse(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM classes WHERE id = ? AND etablissement_id = ?");
        $result = $stmt->execute([$id, EstablishmentContext::id()]);
        unset($this->cache['classes']);
        return $result;
    }

    public function addMatiere(string $code, string $nom, string $couleur = '#3498db'): bool
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO matieres (code, nom, couleur, etablissement_id)
                VALUES (?, ?, ?, ?)
            ");
            $result = $stmt->execute([$code, $nom, $couleur, EstablishmentContext::id()]);
            unset($this->cache['matieres']);
            return $result;
        } catch (\PDOException $e) {
            return false;
        }
    }

    public function deleteMatiere(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM matieres WHERE id = ? AND etablissement_id = ?");
        $result = $stmt->execute([$id, EstablishmentContext::id()]);
        unset($this->cache['matieres']);
        return $result;
    }

    public function configurePeriodes(string $type, array $periodes): bool
    {
        try {
            $etabId = EstablishmentContext::id();
            $this->pdo->prepare("DELETE FROM periodes WHERE etablissement_id = ?")->execute([$etabId]);

            $stmt = $this->pdo->prepare("
                INSERT INTO periodes (numero, nom, type, date_debut, date_fin, etablissement_id)
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            foreach ($periodes as $index => $periode) {
                $stmt->execute([
                    $index + 1,
                    $periode['nom'],
                    $type,
                    $periode['date_debut'],
                    $periode['date_fin'],
                    $etabId,
                ]);
            }

            unset($this->cache['periodes']);
            return true;
        } catch (\PDOException $e) {
            return false;
        }
    }

    public function getPeriodeCourante(): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM periodes
            WHERE CURDATE() BETWEEN date_debut AND date_fin AND etablissement_id = ?
            LIMIT 1
        ");
        $stmt->execute([EstablishmentContext::id()]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function clearCache(): void
    {
        $this->cache = [];
    }
}
