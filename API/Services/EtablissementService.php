<?php
namespace API\Services;

use PDO;

/**
 * Service de gestion de l'établissement
 * Gère les classes, matières, périodes, et configuration
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
     * Récupère toutes les données de l'établissement
     */
    public function getData()
    {
        return [
            'info' => $this->getInfo(),
            'classes' => $this->getClasses(),
            'matieres' => $this->getMatieres(),
            'periodes' => $this->getPeriodes()
        ];
    }

    /**
     * Récupère les informations de l'établissement
     */
    public function getInfo()
    {
        if (isset($this->cache['info'])) {
            return $this->cache['info'];
        }

        $stmt = $this->pdo->query("SELECT * FROM etablissement_info LIMIT 1");
        $info = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$info) {
            $info = $this->createDefaultInfo();
        }

        $this->cache['info'] = $info;
        return $info;
    }

    /**
     * Récupère toutes les classes
     */
    public function getClasses()
    {
        if (isset($this->cache['classes'])) {
            return $this->cache['classes'];
        }

        $stmt = $this->pdo->query("
            SELECT * FROM classes 
            ORDER BY niveau, nom
        ");
        
        $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Organiser par niveau
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

    /**
     * Récupère toutes les matières
     */
    public function getMatieres()
    {
        if (isset($this->cache['matieres'])) {
            return $this->cache['matieres'];
        }

        $stmt = $this->pdo->query("
            SELECT code, nom, couleur 
            FROM matieres 
            ORDER BY nom
        ");
        
        $this->cache['matieres'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $this->cache['matieres'];
    }

    /**
     * Récupère toutes les périodes (trimestres/semestres)
     */
    public function getPeriodes()
    {
        if (isset($this->cache['periodes'])) {
            return $this->cache['periodes'];
        }

        $stmt = $this->pdo->query("
            SELECT * FROM periodes 
            ORDER BY date_debut
        ");
        
        $this->cache['periodes'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $this->cache['periodes'];
    }

    /**
     * Met à jour les informations de l'établissement
     */
    public function updateInfo($data)
    {
        $stmt = $this->pdo->prepare("
            UPDATE etablissement_info SET
                nom = ?,
                adresse = ?,
                code_postal = ?,
                ville = ?,
                telephone = ?,
                email = ?,
                academie = ?
            WHERE id = 1
        ");

        $result = $stmt->execute([
            $data['nom'],
            $data['adresse'],
            $data['code_postal'],
            $data['ville'],
            $data['telephone'],
            $data['email'],
            $data['academie']
        ]);

        unset($this->cache['info']);
        return $result;
    }

    /**
     * Ajoute une classe
     */
    public function addClasse($niveau, $nom)
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO classes (niveau, nom) 
            VALUES (?, ?)
        ");
        
        $result = $stmt->execute([$niveau, $nom]);
        unset($this->cache['classes']);
        return $result;
    }

    /**
     * Supprime une classe
     */
    public function deleteClasse($id)
    {
        $stmt = $this->pdo->prepare("DELETE FROM classes WHERE id = ?");
        $result = $stmt->execute([$id]);
        unset($this->cache['classes']);
        return $result;
    }

    /**
     * Ajoute une matière
     */
    public function addMatiere($code, $nom, $couleur = '#3498db')
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO matieres (code, nom, couleur) 
            VALUES (?, ?, ?)
        ");
        
        $result = $stmt->execute([$code, $nom, $couleur]);
        unset($this->cache['matieres']);
        return $result;
    }

    /**
     * Supprime une matière
     */
    public function deleteMatiere($id)
    {
        $stmt = $this->pdo->prepare("DELETE FROM matieres WHERE id = ?");
        $result = $stmt->execute([$id]);
        unset($this->cache['matieres']);
        return $result;
    }

    /**
     * Configure les périodes (trimestres ou semestres)
     */
    public function configurePeriodes($type, $periodes)
    {
        // Supprimer les anciennes périodes
        $this->pdo->exec("DELETE FROM periodes");

        // Ajouter les nouvelles périodes
        $stmt = $this->pdo->prepare("
            INSERT INTO periodes (numero, nom, type, date_debut, date_fin) 
            VALUES (?, ?, ?, ?, ?)
        ");

        foreach ($periodes as $index => $periode) {
            $stmt->execute([
                $index + 1,
                $periode['nom'],
                $type,
                $periode['date_debut'],
                $periode['date_fin']
            ]);
        }

        unset($this->cache['periodes']);
        return true;
    }

    /**
     * Récupère la période actuelle
     */
    public function getPeriodeCourante()
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM periodes 
            WHERE CURDATE() BETWEEN date_debut AND date_fin 
            LIMIT 1
        ");
        
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Crée les informations par défaut de l'établissement
     */
    protected function createDefaultInfo()
    {
        $defaultInfo = [
            'nom' => 'Établissement Scolaire',
            'adresse' => '1 rue de l\'Education',
            'code_postal' => '75001',
            'ville' => 'Paris',
            'telephone' => '01 23 45 67 89',
            'email' => 'contact@etablissement.fr',
            'academie' => 'Paris'
        ];

        $stmt = $this->pdo->prepare("
            INSERT INTO etablissement_info 
            (nom, adresse, code_postal, ville, telephone, email, academie) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute(array_values($defaultInfo));
        
        return $defaultInfo;
    }

    /**
     * Vide le cache
     */
    public function clearCache()
    {
        $this->cache = [];
    }
}
