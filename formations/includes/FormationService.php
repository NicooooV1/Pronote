<?php
declare(strict_types=1);

namespace Formations;

use PDO;

/**
 * FormationService — Formation Continue Personnel.
 *
 * Catalogue formations, inscriptions avec workflow validation,
 * suivi certifications, gestion budget, attestations PDF.
 */
class FormationService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ─── Catalogue ────────────────────────────────────────────────

    public function getCatalogue(int $etabId, ?string $type = null): array
    {
        $sql = "SELECT f.*, (SELECT COUNT(*) FROM formation_inscriptions fi WHERE fi.formation_id = f.id AND fi.statut = 'validee') AS nb_inscrits FROM formations f WHERE f.etablissement_id = :eid AND f.statut = 'publiee'";
        $params = [':eid' => $etabId];
        if ($type) { $sql .= " AND f.type = :t"; $params[':t'] = $type; }
        $sql .= " ORDER BY f.date_debut ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function creerFormation(int $etabId, string $titre, string $type, string $description, string $dateDebut, string $dateFin, int $placesMax, float $cout = 0, string $organisme = '', string $lieu = ''): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO formations (etablissement_id, titre, type, description, date_debut, date_fin, places_max, cout, organisme, lieu, statut) VALUES (:eid, :t, :ty, :d, :dd, :df, :pm, :c, :o, :l, 'brouillon')");
        $stmt->execute([':eid' => $etabId, ':t' => $titre, ':ty' => $type, ':d' => $description, ':dd' => $dateDebut, ':df' => $dateFin, ':pm' => $placesMax, ':c' => $cout, ':o' => $organisme, ':l' => $lieu]);
        return (int)$this->pdo->lastInsertId();
    }

    public function publierFormation(int $formationId): void
    {
        $this->pdo->prepare("UPDATE formations SET statut = 'publiee' WHERE id = :id")->execute([':id' => $formationId]);
    }

    // ─── Inscriptions ─────────────────────────────────────────────

    public function inscrire(int $formationId, int $personnelId, string $motivation = ''): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO formation_inscriptions (formation_id, personnel_id, motivation, statut) VALUES (:fid, :pid, :m, 'en_attente')");
        $stmt->execute([':fid' => $formationId, ':pid' => $personnelId, ':m' => $motivation]);
        return (int)$this->pdo->lastInsertId();
    }

    public function validerInscription(int $inscriptionId, int $validePar): void
    {
        $this->pdo->prepare("UPDATE formation_inscriptions SET statut = 'validee', valide_par = :vp, date_validation = NOW() WHERE id = :id AND statut = 'en_attente'")
            ->execute([':vp' => $validePar, ':id' => $inscriptionId]);
    }

    public function refuserInscription(int $inscriptionId, int $refusePar, string $motif = ''): void
    {
        $this->pdo->prepare("UPDATE formation_inscriptions SET statut = 'refusee', valide_par = :rp, motif_refus = :m, date_validation = NOW() WHERE id = :id AND statut = 'en_attente'")
            ->execute([':rp' => $refusePar, ':m' => $motif, ':id' => $inscriptionId]);
    }

    public function getInscriptions(int $formationId): array
    {
        $stmt = $this->pdo->prepare("SELECT fi.*, CONCAT(p.prenom,' ',p.nom) AS personnel_nom, p.fonction FROM formation_inscriptions fi JOIN personnel p ON fi.personnel_id = p.id WHERE fi.formation_id = :fid ORDER BY fi.created_at");
        $stmt->execute([':fid' => $formationId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getMesInscriptions(int $personnelId): array
    {
        $stmt = $this->pdo->prepare("SELECT fi.*, f.titre, f.date_debut, f.date_fin, f.lieu, f.type FROM formation_inscriptions fi JOIN formations f ON fi.formation_id = f.id WHERE fi.personnel_id = :pid ORDER BY f.date_debut DESC");
        $stmt->execute([':pid' => $personnelId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ─── Certifications ───────────────────────────────────────────

    public function getCertifications(int $personnelId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM formation_certifications WHERE personnel_id = :pid ORDER BY date_expiration ASC");
        $stmt->execute([':pid' => $personnelId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function ajouterCertification(int $personnelId, string $titre, string $organisme, string $dateObtention, ?string $dateExpiration = null, string $numeroCertificat = ''): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO formation_certifications (personnel_id, titre, organisme, date_obtention, date_expiration, numero_certificat) VALUES (:pid, :t, :o, :do, :de, :nc)");
        $stmt->execute([':pid' => $personnelId, ':t' => $titre, ':o' => $organisme, ':do' => $dateObtention, ':de' => $dateExpiration, ':nc' => $numeroCertificat]);
        return (int)$this->pdo->lastInsertId();
    }

    public function checkExpirations(int $etabId, int $joursAvant = 30): array
    {
        $stmt = $this->pdo->prepare("SELECT fc.*, CONCAT(p.prenom,' ',p.nom) AS personnel_nom FROM formation_certifications fc JOIN personnel p ON fc.personnel_id = p.id WHERE fc.date_expiration BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL :j DAY) ORDER BY fc.date_expiration ASC");
        $stmt->execute([':j' => $joursAvant]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ─── Budget ───────────────────────────────────────────────────

    public function getBudgetSuivi(int $etabId, string $annee): array
    {
        $stmt = $this->pdo->prepare("SELECT fb.*, CONCAT(p.prenom,' ',p.nom) AS personnel_nom FROM formation_budgets fb JOIN personnel p ON fb.personnel_id = p.id WHERE fb.etablissement_id = :eid AND fb.annee = :a ORDER BY p.nom");
        $stmt->execute([':eid' => $etabId, ':a' => $annee]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function deduireBudget(int $personnelId, int $etabId, string $annee, float $montant): void
    {
        $this->pdo->prepare("INSERT INTO formation_budgets (etablissement_id, personnel_id, annee, budget_alloue, budget_consomme) VALUES (:eid, :pid, :a, 0, :m) ON DUPLICATE KEY UPDATE budget_consomme = budget_consomme + VALUES(budget_consomme)")
            ->execute([':eid' => $etabId, ':pid' => $personnelId, ':a' => $annee, ':m' => $montant]);
    }

    // ─── Évaluations post-formation ───────────────────────────────

    public function evaluerFormation(int $inscriptionId, int $noteGlobale, string $commentaire = '', string $pointsForts = '', string $pointsAmeliorer = ''): void
    {
        $this->pdo->prepare("INSERT INTO formation_evaluations (inscription_id, note_globale, commentaire, points_forts, points_ameliorer) VALUES (:iid, :n, :c, :pf, :pa)")
            ->execute([':iid' => $inscriptionId, ':n' => $noteGlobale, ':c' => $commentaire, ':pf' => $pointsForts, ':pa' => $pointsAmeliorer]);
    }

    // ─── Plan annuel ──────────────────────────────────────────────

    public function getPlanAnnuel(int $etabId, string $annee): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM formation_plan_annuel WHERE etablissement_id = :eid AND annee = :a ORDER BY priorite ASC");
        $stmt->execute([':eid' => $etabId, ':a' => $annee]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ─── Attestation data ─────────────────────────────────────────

    public function genererAttestationData(int $inscriptionId): array
    {
        $stmt = $this->pdo->prepare("SELECT fi.*, f.titre, f.description, f.date_debut, f.date_fin, f.organisme, f.lieu, CONCAT(p.prenom,' ',p.nom) AS personnel_nom, p.fonction FROM formation_inscriptions fi JOIN formations f ON fi.formation_id = f.id JOIN personnel p ON fi.personnel_id = p.id WHERE fi.id = :id AND fi.statut = 'validee'");
        $stmt->execute([':id' => $inscriptionId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    // ─── Statistiques ─────────────────────────────────────────────

    public function getStatistiques(int $etabId, string $annee): array
    {
        $nbFormations = $this->pdo->prepare("SELECT COUNT(*) FROM formations WHERE etablissement_id = :eid AND YEAR(date_debut) = :a");
        $nbFormations->execute([':eid' => $etabId, ':a' => $annee]);

        $nbInscrits = $this->pdo->prepare("SELECT COUNT(DISTINCT fi.personnel_id) FROM formation_inscriptions fi JOIN formations f ON fi.formation_id = f.id WHERE f.etablissement_id = :eid AND YEAR(f.date_debut) = :a AND fi.statut = 'validee'");
        $nbInscrits->execute([':eid' => $etabId, ':a' => $annee]);

        $budgetTotal = $this->pdo->prepare("SELECT COALESCE(SUM(budget_consomme),0) FROM formation_budgets WHERE etablissement_id = :eid AND annee = :a");
        $budgetTotal->execute([':eid' => $etabId, ':a' => $annee]);

        $noteMoyenne = $this->pdo->prepare("SELECT ROUND(AVG(fe.note_globale),1) FROM formation_evaluations fe JOIN formation_inscriptions fi ON fe.inscription_id = fi.id JOIN formations f ON fi.formation_id = f.id WHERE f.etablissement_id = :eid AND YEAR(f.date_debut) = :a");
        $noteMoyenne->execute([':eid' => $etabId, ':a' => $annee]);

        return [
            'nb_formations' => (int)$nbFormations->fetchColumn(),
            'nb_participants' => (int)$nbInscrits->fetchColumn(),
            'budget_consomme' => (float)$budgetTotal->fetchColumn(),
            'note_satisfaction_moyenne' => $noteMoyenne->fetchColumn()
        ];
    }
}
