<?php
declare(strict_types=1);

namespace Bourses;

use PDO;

/**
 * BoursesService — Bourses & Aides Financières.
 *
 * Simulateur éligibilité, demandes en ligne, workflow instruction,
 * gestion fonds sociaux, export paiements, stats campagne.
 */
class BoursesService
{
    private PDO $pdo;

    // Barème simplifié bourses nationales collège 2025-2026
    private array $baremeEchelons = [
        1 => ['plafond_1_enfant' => 16845, 'montant_annuel' => 105],
        2 => ['plafond_1_enfant' => 10000, 'montant_annuel' => 288],
        3 => ['plafond_1_enfant' => 3000, 'montant_annuel' => 450],
    ];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ─── Simulateur ───────────────────────────────────────────────

    public function simulerEligibilite(float $revenuFiscal, int $nbEnfants): array
    {
        $quotient = $revenuFiscal / max(1, $nbEnfants);
        $echelon = 0;
        $montant = 0;

        foreach ($this->baremeEchelons as $ech => $data) {
            $plafond = $data['plafond_1_enfant'] * $this->coefficientEnfants($nbEnfants);
            if ($revenuFiscal <= $plafond) {
                $echelon = $ech;
                $montant = $data['montant_annuel'];
            }
        }

        return [
            'eligible' => $echelon > 0,
            'echelon' => $echelon,
            'montant_annuel' => $montant,
            'montant_trimestriel' => round($montant / 3, 2),
            'quotient_familial' => round($quotient, 2)
        ];
    }

    private function coefficientEnfants(int $nb): float
    {
        return match(true) {
            $nb <= 1 => 1.0,
            $nb === 2 => 1.3,
            $nb === 3 => 1.6,
            $nb === 4 => 1.9,
            default => 1.9 + (($nb - 4) * 0.3)
        };
    }

    public function calculerEchelon(float $revenuFiscal, int $nbEnfants): int
    {
        return $this->simulerEligibilite($revenuFiscal, $nbEnfants)['echelon'];
    }

    // ─── Demandes ─────────────────────────────────────────────────

    public function creerDemande(int $etabId, int $parentId, int $eleveId, int $typeId, float $revenuFiscal, int $nbEnfants): int
    {
        $simulation = $this->simulerEligibilite($revenuFiscal, $nbEnfants);
        $stmt = $this->pdo->prepare("INSERT INTO bourses_demandes (etablissement_id, parent_id, eleve_id, type_id, revenu_fiscal, nb_enfants, echelon_simule, montant_simule, statut) VALUES (:eid, :pid, :elid, :tid, :rf, :ne, :es, :ms, 'brouillon')");
        $stmt->execute([':eid' => $etabId, ':pid' => $parentId, ':elid' => $eleveId, ':tid' => $typeId, ':rf' => $revenuFiscal, ':ne' => $nbEnfants, ':es' => $simulation['echelon'], ':ms' => $simulation['montant_annuel']]);
        return (int)$this->pdo->lastInsertId();
    }

    public function soumettreDemande(int $demandeId): void
    {
        $this->pdo->prepare("UPDATE bourses_demandes SET statut = 'soumise', date_soumission = NOW() WHERE id = :id AND statut = 'brouillon'")
            ->execute([':id' => $demandeId]);
    }

    public function ajouterDocument(int $demandeId, string $type, string $fichierPath, string $nomOriginal): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO bourses_documents (demande_id, type_document, fichier_path, nom_original) VALUES (:did, :t, :fp, :no)");
        $stmt->execute([':did' => $demandeId, ':t' => $type, ':fp' => $fichierPath, ':no' => $nomOriginal]);
        return (int)$this->pdo->lastInsertId();
    }

    // ─── Instruction ──────────────────────────────────────────────

    public function instruireDemande(int $demandeId, int $instructeurId, string $decision, int $echelonFinal = 0, float $montantFinal = 0, string $commentaire = ''): void
    {
        $this->pdo->prepare("UPDATE bourses_demandes SET statut = :s, instructeur_id = :iid, echelon_final = :ef, montant_final = :mf, commentaire_instruction = :c, date_instruction = NOW() WHERE id = :id")
            ->execute([':s' => $decision, ':iid' => $instructeurId, ':ef' => $echelonFinal, ':mf' => $montantFinal, ':c' => $commentaire, ':id' => $demandeId]);
    }

    public function getDemandesAInstruire(int $etabId): array
    {
        $stmt = $this->pdo->prepare("SELECT bd.*, CONCAT(e.prenom,' ',e.nom) AS eleve_nom, e.classe, bt.libelle AS type_bourse FROM bourses_demandes bd JOIN eleves e ON bd.eleve_id = e.id JOIN bourses_types bt ON bd.type_id = bt.id WHERE bd.etablissement_id = :eid AND bd.statut = 'soumise' ORDER BY bd.date_soumission ASC");
        $stmt->execute([':eid' => $etabId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getDemande(int $demandeId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT bd.*, CONCAT(e.prenom,' ',e.nom) AS eleve_nom, e.classe, bt.libelle AS type_bourse FROM bourses_demandes bd JOIN eleves e ON bd.eleve_id = e.id JOIN bourses_types bt ON bd.type_id = bt.id WHERE bd.id = :id");
        $stmt->execute([':id' => $demandeId]);
        $demande = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$demande) return null;

        $docs = $this->pdo->prepare("SELECT * FROM bourses_documents WHERE demande_id = :did ORDER BY type_document");
        $docs->execute([':did' => $demandeId]);
        $demande['documents'] = $docs->fetchAll(PDO::FETCH_ASSOC);

        return $demande;
    }

    // ─── Versements ───────────────────────────────────────────────

    public function planifierVersement(int $demandeId, float $montant, string $dateVersement, string $trimestre): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO bourses_versements (demande_id, montant, date_versement, trimestre, statut) VALUES (:did, :m, :dv, :t, 'planifie')");
        $stmt->execute([':did' => $demandeId, ':m' => $montant, ':dv' => $dateVersement, ':t' => $trimestre]);
        return (int)$this->pdo->lastInsertId();
    }

    public function effectuerVersement(int $versementId): void
    {
        $this->pdo->prepare("UPDATE bourses_versements SET statut = 'verse', date_effectif = NOW() WHERE id = :id AND statut = 'planifie'")
            ->execute([':id' => $versementId]);
    }

    public function exportVersementsComptables(int $etabId, string $dateDebut, string $dateFin): array
    {
        $stmt = $this->pdo->prepare("SELECT bv.*, bd.eleve_id, CONCAT(e.prenom,' ',e.nom) AS eleve_nom, bd.parent_id, bt.libelle AS type_bourse, bt.code FROM bourses_versements bv JOIN bourses_demandes bd ON bv.demande_id = bd.id JOIN eleves e ON bd.eleve_id = e.id JOIN bourses_types bt ON bd.type_id = bt.id WHERE bd.etablissement_id = :eid AND bv.date_versement BETWEEN :dd AND :df AND bv.statut = 'verse' ORDER BY bv.date_versement");
        $stmt->execute([':eid' => $etabId, ':dd' => $dateDebut, ':df' => $dateFin]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ─── Fonds sociaux ────────────────────────────────────────────

    public function creerDemandeFondsSocial(int $etabId, int $parentId, int $eleveId, string $motif, float $montantDemande): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO fonds_sociaux (etablissement_id, parent_id, eleve_id, motif, montant_demande, statut) VALUES (:eid, :pid, :elid, :m, :md, 'soumise')");
        $stmt->execute([':eid' => $etabId, ':pid' => $parentId, ':elid' => $eleveId, ':m' => $motif, ':md' => $montantDemande]);
        return (int)$this->pdo->lastInsertId();
    }

    // ─── Statistiques campagne ────────────────────────────────────

    public function getStatistiquesCampagne(int $etabId, string $annee): array
    {
        $parStatut = $this->pdo->prepare("SELECT statut, COUNT(*) AS nb, COALESCE(SUM(montant_final),0) AS montant_total FROM bourses_demandes WHERE etablissement_id = :eid AND annee_scolaire = :a GROUP BY statut");
        $parStatut->execute([':eid' => $etabId, ':a' => $annee]);

        $parType = $this->pdo->prepare("SELECT bt.libelle, COUNT(*) AS nb, SUM(bd.montant_final) AS montant FROM bourses_demandes bd JOIN bourses_types bt ON bd.type_id = bt.id WHERE bd.etablissement_id = :eid AND bd.annee_scolaire = :a AND bd.statut = 'accordee' GROUP BY bt.id");
        $parType->execute([':eid' => $etabId, ':a' => $annee]);

        $parEchelon = $this->pdo->prepare("SELECT echelon_final AS echelon, COUNT(*) AS nb FROM bourses_demandes WHERE etablissement_id = :eid AND annee_scolaire = :a AND statut = 'accordee' GROUP BY echelon_final ORDER BY echelon_final");
        $parEchelon->execute([':eid' => $etabId, ':a' => $annee]);

        return [
            'par_statut' => $parStatut->fetchAll(PDO::FETCH_ASSOC),
            'par_type' => $parType->fetchAll(PDO::FETCH_ASSOC),
            'par_echelon' => $parEchelon->fetchAll(PDO::FETCH_ASSOC)
        ];
    }
}
