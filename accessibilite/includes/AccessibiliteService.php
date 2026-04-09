<?php
declare(strict_types=1);

namespace Accessibilite;

use PDO;

/**
 * AccessibiliteService — Accessibilité & Inclusion.
 *
 * Registre aménagements, gestion AESH, liaison MDPH, suivi PPS/PAP,
 * notification profs, workflow ESS, audit accessibilité numérique.
 */
class AccessibiliteService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ─── Aménagements ─────────────────────────────────────────────

    public function creerAmenagement(int $etabId, int $eleveId, string $type, string $description, string $dateDebut, ?string $dateFin = null, string $prescripteur = '', string $documentRef = ''): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO accessibilite_amenagements (etablissement_id, eleve_id, type_amenagement, description, date_debut, date_fin, prescripteur, document_reference, statut) VALUES (:eid, :elid, :t, :d, :dd, :df, :p, :dr, 'actif')");
        $stmt->execute([':eid' => $etabId, ':elid' => $eleveId, ':t' => $type, ':d' => $description, ':dd' => $dateDebut, ':df' => $dateFin, ':p' => $prescripteur, ':dr' => $documentRef]);
        return (int)$this->pdo->lastInsertId();
    }

    public function getAmenagements(int $eleveId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM accessibilite_amenagements WHERE eleve_id = :eid AND statut = 'actif' ORDER BY type_amenagement");
        $stmt->execute([':eid' => $eleveId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAmenagementsParProfesseur(int $profId): array
    {
        $stmt = $this->pdo->prepare("SELECT a.*, CONCAT(e.prenom,' ',e.nom) AS eleve_nom, e.classe FROM accessibilite_amenagements a JOIN eleves e ON a.eleve_id = e.id WHERE e.classe IN (SELECT DISTINCT classe FROM emploi_du_temps WHERE id_professeur = :pid) AND a.statut = 'actif' AND e.actif = 1 ORDER BY e.classe, e.nom");
        $stmt->execute([':pid' => $profId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAmenagementsParClasse(string $classe): array
    {
        $stmt = $this->pdo->prepare("SELECT a.*, CONCAT(e.prenom,' ',e.nom) AS eleve_nom FROM accessibilite_amenagements a JOIN eleves e ON a.eleve_id = e.id WHERE e.classe = :c AND a.statut = 'actif' AND e.actif = 1 ORDER BY e.nom, a.type_amenagement");
        $stmt->execute([':c' => $classe]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function desactiverAmenagement(int $amenagementId): void
    {
        $this->pdo->prepare("UPDATE accessibilite_amenagements SET statut = 'inactif' WHERE id = :id")
            ->execute([':id' => $amenagementId]);
    }

    // ─── AESH ─────────────────────────────────────────────────────

    public function getAeshList(int $etabId): array
    {
        $stmt = $this->pdo->prepare("SELECT a.*, (SELECT COUNT(*) FROM accessibilite_aesh_affectations af WHERE af.aesh_id = a.id AND af.statut = 'actif') AS nb_eleves, (SELECT SUM(af.heures_semaine) FROM accessibilite_aesh_affectations af WHERE af.aesh_id = a.id AND af.statut = 'actif') AS total_heures FROM accessibilite_aesh a WHERE a.etablissement_id = :eid AND a.actif = 1 ORDER BY a.nom");
        $stmt->execute([':eid' => $etabId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function affecterAesh(int $aeshId, int $eleveId, float $heuresSemaine, string $typeAccompagnement, string $dateDebut, ?string $dateFin = null): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO accessibilite_aesh_affectations (aesh_id, eleve_id, heures_semaine, type_accompagnement, date_debut, date_fin, statut) VALUES (:aid, :eid, :h, :t, :dd, :df, 'actif')");
        $stmt->execute([':aid' => $aeshId, ':eid' => $eleveId, ':h' => $heuresSemaine, ':t' => $typeAccompagnement, ':dd' => $dateDebut, ':df' => $dateFin]);
        return (int)$this->pdo->lastInsertId();
    }

    public function getCalendrierAesh(int $aeshId, string $semaine): array
    {
        $stmt = $this->pdo->prepare("SELECT af.eleve_id, CONCAT(e.prenom,' ',e.nom) AS eleve, e.classe, af.heures_semaine, af.type_accompagnement, edt.jour, edt.heure_debut, edt.heure_fin, m.nom AS matiere FROM accessibilite_aesh_affectations af JOIN eleves e ON af.eleve_id = e.id JOIN emploi_du_temps edt ON edt.classe = e.classe JOIN matieres m ON edt.id_matiere = m.id WHERE af.aesh_id = :aid AND af.statut = 'actif' ORDER BY FIELD(edt.jour,'lundi','mardi','mercredi','jeudi','vendredi'), edt.heure_debut");
        $stmt->execute([':aid' => $aeshId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ─── MDPH ─────────────────────────────────────────────────────

    public function enregistrerDecisionMdph(int $eleveId, string $typeDecision, string $dateDecision, string $dateExpiration, string $contenu, string $numeroDossier = ''): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO accessibilite_mdph (eleve_id, type_decision, date_decision, date_expiration, contenu, numero_dossier, statut) VALUES (:eid, :td, :dd, :de, :c, :nd, 'actif')");
        $stmt->execute([':eid' => $eleveId, ':td' => $typeDecision, ':dd' => $dateDecision, ':de' => $dateExpiration, ':c' => $contenu, ':nd' => $numeroDossier]);
        return (int)$this->pdo->lastInsertId();
    }

    public function getDecisionsMdph(int $eleveId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM accessibilite_mdph WHERE eleve_id = :eid ORDER BY date_decision DESC");
        $stmt->execute([':eid' => $eleveId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getDecisionsExpirant(int $etabId, int $joursAvant = 60): array
    {
        $stmt = $this->pdo->prepare("SELECT md.*, CONCAT(e.prenom,' ',e.nom) AS eleve_nom, e.classe FROM accessibilite_mdph md JOIN eleves e ON md.eleve_id = e.id WHERE e.actif = 1 AND md.statut = 'actif' AND md.date_expiration BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL :j DAY) ORDER BY md.date_expiration ASC");
        $stmt->execute([':j' => $joursAvant]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ─── ESS (Equipe de Suivi de Scolarisation) ──────────────────

    public function planifierEss(int $eleveId, string $dateEss, string $lieu, array $participantsIds, string $objectifs = ''): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO accessibilite_ess (eleve_id, date_ess, lieu, participants_ids, objectifs, statut) VALUES (:eid, :d, :l, :p, :o, 'planifie')");
        $stmt->execute([':eid' => $eleveId, ':d' => $dateEss, ':l' => $lieu, ':p' => json_encode($participantsIds), ':o' => $objectifs]);
        return (int)$this->pdo->lastInsertId();
    }

    public function completerEss(int $essId, string $compteRendu, string $decisions, string $prochaineDateEss = ''): void
    {
        $this->pdo->prepare("UPDATE accessibilite_ess SET statut = 'realise', compte_rendu = :cr, decisions = :d, prochaine_date = :pd WHERE id = :id")
            ->execute([':cr' => $compteRendu, ':d' => $decisions, ':pd' => $prochaineDateEss ?: null, ':id' => $essId]);
    }

    public function getEssEleve(int $eleveId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM accessibilite_ess WHERE eleve_id = :eid ORDER BY date_ess DESC");
        $stmt->execute([':eid' => $eleveId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ─── Audit accessibilité numérique (RGAA) ────────────────────

    public function auditModuleAccessibilite(int $etabId, string $moduleKey, int $auditeurId, array $criteres): int
    {
        $nbConformes = count(array_filter($criteres, fn($c) => $c['conforme'] === true));
        $nbTotal = count($criteres);
        $score = $nbTotal > 0 ? round(($nbConformes / $nbTotal) * 100, 1) : 0;

        $stmt = $this->pdo->prepare("INSERT INTO accessibilite_audit_numerique (etablissement_id, module_key, auditeur_id, criteres_json, nb_conformes, nb_total, score, date_audit) VALUES (:eid, :mk, :aid, :cj, :nc, :nt, :s, NOW())");
        $stmt->execute([':eid' => $etabId, ':mk' => $moduleKey, ':aid' => $auditeurId, ':cj' => json_encode($criteres), ':nc' => $nbConformes, ':nt' => $nbTotal, ':s' => $score]);
        return (int)$this->pdo->lastInsertId();
    }

    public function getAuditsNumeriques(int $etabId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM accessibilite_audit_numerique WHERE etablissement_id = :eid ORDER BY date_audit DESC");
        $stmt->execute([':eid' => $etabId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ─── Fiche aménagements (pour PDF) ────────────────────────────

    public function genererFicheAmenagements(int $eleveId): array
    {
        $eleve = $this->pdo->prepare("SELECT id, nom, prenom, classe, date_naissance FROM eleves WHERE id = :id");
        $eleve->execute([':id' => $eleveId]);

        $amenagements = $this->getAmenagements($eleveId);
        $decisions = $this->getDecisionsMdph($eleveId);
        $ess = $this->getEssEleve($eleveId);

        $aesh = $this->pdo->prepare("SELECT a.nom, a.prenom, af.heures_semaine, af.type_accompagnement FROM accessibilite_aesh_affectations af JOIN accessibilite_aesh a ON af.aesh_id = a.id WHERE af.eleve_id = :eid AND af.statut = 'actif'");
        $aesh->execute([':eid' => $eleveId]);

        return [
            'eleve' => $eleve->fetch(PDO::FETCH_ASSOC),
            'amenagements' => $amenagements,
            'decisions_mdph' => $decisions,
            'historique_ess' => $ess,
            'accompagnement_aesh' => $aesh->fetchAll(PDO::FETCH_ASSOC)
        ];
    }

    // ─── Statistiques ─────────────────────────────────────────────

    public function getStatistiques(int $etabId): array
    {
        $nbEleves = $this->pdo->prepare("SELECT COUNT(DISTINCT eleve_id) FROM accessibilite_amenagements WHERE etablissement_id = :eid AND statut = 'actif'");
        $nbEleves->execute([':eid' => $etabId]);

        $parType = $this->pdo->prepare("SELECT type_amenagement, COUNT(*) AS nb FROM accessibilite_amenagements WHERE etablissement_id = :eid AND statut = 'actif' GROUP BY type_amenagement ORDER BY nb DESC");
        $parType->execute([':eid' => $etabId]);

        $nbAesh = $this->pdo->prepare("SELECT COUNT(*) FROM accessibilite_aesh WHERE etablissement_id = :eid AND actif = 1");
        $nbAesh->execute([':eid' => $etabId]);

        $totalHeures = $this->pdo->prepare("SELECT COALESCE(SUM(af.heures_semaine),0) FROM accessibilite_aesh_affectations af JOIN accessibilite_aesh a ON af.aesh_id = a.id WHERE a.etablissement_id = :eid AND af.statut = 'actif'");
        $totalHeures->execute([':eid' => $etabId]);

        return [
            'nb_eleves_amenages' => (int)$nbEleves->fetchColumn(),
            'amenagements_par_type' => $parType->fetchAll(PDO::FETCH_ASSOC),
            'nb_aesh' => (int)$nbAesh->fetchColumn(),
            'total_heures_aesh' => (float)$totalHeures->fetchColumn()
        ];
    }
}
