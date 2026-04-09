<?php
declare(strict_types=1);

namespace Echanges;

use PDO;

/**
 * EchangesService — Échanges & Mobilité Internationale.
 *
 * Programmes Erasmus+/eTwinning, candidatures, familles d'accueil,
 * conventions partenariat, suivi linguistique CECRL, logistique.
 */
class EchangesService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ─── Programmes ───────────────────────────────────────────────

    public function creerProgramme(int $etabId, string $titre, string $type, string $description, string $paysDestination, string $dateDebut, string $dateFin, int $placesMax, float $budget = 0, int $responsableId = 0): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO echanges_programmes (etablissement_id, titre, type_programme, description, pays_destination, date_debut, date_fin, places_max, budget, responsable_id, statut) VALUES (:eid, :t, :ty, :d, :pd, :dd, :df, :pm, :b, :rid, 'planifie')");
        $stmt->execute([':eid' => $etabId, ':t' => $titre, ':ty' => $type, ':d' => $description, ':pd' => $paysDestination, ':dd' => $dateDebut, ':df' => $dateFin, ':pm' => $placesMax, ':b' => $budget, ':rid' => $responsableId]);
        return (int)$this->pdo->lastInsertId();
    }

    public function getProgrammes(int $etabId, ?string $statut = null): array
    {
        $sql = "SELECT p.*, (SELECT COUNT(*) FROM echanges_candidatures c WHERE c.programme_id = p.id AND c.statut = 'acceptee') AS nb_inscrits, CONCAT(pr.prenom,' ',pr.nom) AS responsable_nom FROM echanges_programmes p LEFT JOIN professeurs pr ON p.responsable_id = pr.id WHERE p.etablissement_id = :eid";
        $params = [':eid' => $etabId];
        if ($statut) { $sql .= " AND p.statut = :s"; $params[':s'] = $statut; }
        $sql .= " ORDER BY p.date_debut DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function ouvrirCandidatures(int $programmeId): void
    {
        $this->pdo->prepare("UPDATE echanges_programmes SET statut = 'candidatures_ouvertes' WHERE id = :id")->execute([':id' => $programmeId]);
    }

    // ─── Candidatures ─────────────────────────────────────────────

    public function postuler(int $programmeId, int $eleveId, string $motivation, string $niveauLangue = ''): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO echanges_candidatures (programme_id, eleve_id, motivation, niveau_langue_declare, statut) VALUES (:pid, :eid, :m, :nl, 'soumise')");
        $stmt->execute([':pid' => $programmeId, ':eid' => $eleveId, ':m' => $motivation, ':nl' => $niveauLangue]);
        return (int)$this->pdo->lastInsertId();
    }

    public function gererCandidature(int $candidatureId, string $decision, string $commentaire = ''): void
    {
        $this->pdo->prepare("UPDATE echanges_candidatures SET statut = :s, commentaire = :c, date_decision = NOW() WHERE id = :id")
            ->execute([':s' => $decision, ':c' => $commentaire, ':id' => $candidatureId]);
    }

    public function getCandidatures(int $programmeId): array
    {
        $stmt = $this->pdo->prepare("SELECT c.*, CONCAT(e.prenom,' ',e.nom) AS eleve_nom, e.classe FROM echanges_candidatures c JOIN eleves e ON c.eleve_id = e.id WHERE c.programme_id = :pid ORDER BY c.created_at");
        $stmt->execute([':pid' => $programmeId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ─── Familles d'accueil ───────────────────────────────────────

    public function inscrireFamille(int $etabId, string $nomFamille, string $adresse, string $telephone, string $email, int $capacite, string $languesJson = '[]', string $preferences = ''): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO echanges_familles (etablissement_id, nom_famille, adresse, telephone, email, capacite, langues, preferences, statut) VALUES (:eid, :nf, :a, :t, :e, :c, :l, :p, 'disponible')");
        $stmt->execute([':eid' => $etabId, ':nf' => $nomFamille, ':a' => $adresse, ':t' => $telephone, ':e' => $email, ':c' => $capacite, ':l' => $languesJson, ':p' => $preferences]);
        return (int)$this->pdo->lastInsertId();
    }

    public function getFamilles(int $etabId, ?string $statut = null): array
    {
        $sql = "SELECT * FROM echanges_familles WHERE etablissement_id = :eid";
        $params = [':eid' => $etabId];
        if ($statut) { $sql .= " AND statut = :s"; $params[':s'] = $statut; }
        $sql .= " ORDER BY nom_famille";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function affecterHebergement(int $candidatureId, int $familleId, string $dateArrivee, string $dateDepart): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO echanges_hebergements (candidature_id, famille_id, date_arrivee, date_depart, statut) VALUES (:cid, :fid, :da, :dd, 'confirme')");
        $stmt->execute([':cid' => $candidatureId, ':fid' => $familleId, ':da' => $dateArrivee, ':dd' => $dateDepart]);

        $this->pdo->prepare("UPDATE echanges_familles SET statut = 'occupee' WHERE id = :id")
            ->execute([':id' => $familleId]);

        return (int)$this->pdo->lastInsertId();
    }

    // ─── Partenariats ─────────────────────────────────────────────

    public function enregistrerPartenariat(int $etabId, string $nomEtablissement, string $pays, string $ville, string $type, string $dateDebut, ?string $dateFin = null, string $contactNom = '', string $contactEmail = ''): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO echanges_partenariats (etablissement_id, nom_etablissement_partenaire, pays, ville, type_partenariat, date_debut, date_fin, contact_nom, contact_email, statut) VALUES (:eid, :ne, :p, :v, :t, :dd, :df, :cn, :ce, 'actif')");
        $stmt->execute([':eid' => $etabId, ':ne' => $nomEtablissement, ':p' => $pays, ':v' => $ville, ':t' => $type, ':dd' => $dateDebut, ':df' => $dateFin, ':cn' => $contactNom, ':ce' => $contactEmail]);
        return (int)$this->pdo->lastInsertId();
    }

    public function getPartenariats(int $etabId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM echanges_partenariats WHERE etablissement_id = :eid AND statut = 'actif' ORDER BY pays, nom_etablissement_partenaire");
        $stmt->execute([':eid' => $etabId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ─── Suivi linguistique CECRL ─────────────────────────────────

    public function evaluerNiveauLinguistique(int $eleveId, string $langue, string $niveau, string $typeEvaluation, int $evaluateurId, string $commentaire = ''): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO echanges_suivi_linguistique (eleve_id, langue, niveau_cecrl, type_evaluation, evaluateur_id, commentaire, date_evaluation) VALUES (:eid, :l, :n, :te, :evid, :c, NOW())");
        $stmt->execute([':eid' => $eleveId, ':l' => $langue, ':n' => $niveau, ':te' => $typeEvaluation, ':evid' => $evaluateurId, ':c' => $commentaire]);
        return (int)$this->pdo->lastInsertId();
    }

    public function getProgressionLinguistique(int $eleveId, string $langue): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM echanges_suivi_linguistique WHERE eleve_id = :eid AND langue = :l ORDER BY date_evaluation ASC");
        $stmt->execute([':eid' => $eleveId, ':l' => $langue]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ─── Statistiques ─────────────────────────────────────────────

    public function getStatistiques(int $etabId): array
    {
        $nbProgrammes = $this->pdo->prepare("SELECT COUNT(*) FROM echanges_programmes WHERE etablissement_id = :eid");
        $nbProgrammes->execute([':eid' => $etabId]);

        $nbParticipants = $this->pdo->prepare("SELECT COUNT(DISTINCT c.eleve_id) FROM echanges_candidatures c JOIN echanges_programmes p ON c.programme_id = p.id WHERE p.etablissement_id = :eid AND c.statut = 'acceptee'");
        $nbParticipants->execute([':eid' => $etabId]);

        $parPays = $this->pdo->prepare("SELECT pays_destination AS pays, COUNT(*) AS nb FROM echanges_programmes WHERE etablissement_id = :eid GROUP BY pays_destination ORDER BY nb DESC");
        $parPays->execute([':eid' => $etabId]);

        $nbPartenaires = $this->pdo->prepare("SELECT COUNT(*) FROM echanges_partenariats WHERE etablissement_id = :eid AND statut = 'actif'");
        $nbPartenaires->execute([':eid' => $etabId]);

        return [
            'nb_programmes' => (int)$nbProgrammes->fetchColumn(),
            'nb_participants' => (int)$nbParticipants->fetchColumn(),
            'par_pays' => $parPays->fetchAll(PDO::FETCH_ASSOC),
            'nb_partenaires_actifs' => (int)$nbPartenaires->fetchColumn()
        ];
    }
}
