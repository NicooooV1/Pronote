<?php
declare(strict_types=1);

namespace Inventaire;

use PDO;

/**
 * InventaireService — Inventaire & Patrimoine IT.
 *
 * Registre assets IT, QR codes, maintenance préventive, prêts/retours,
 * amortissement comptable, signalement pannes, stats parc.
 */
class InventaireService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ─── Assets ───────────────────────────────────────────────────

    public function createAsset(int $etabId, string $nom, string $type, string $numeroSerie, string $marque = '', string $modele = '', float $prixAchat = 0, string $dateAchat = '', ?int $salleId = null, string $fournisseur = ''): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO inventaire_assets (etablissement_id, nom, type_asset, numero_serie, marque, modele, prix_achat, date_achat, salle_id, fournisseur, statut) VALUES (:eid, :n, :t, :ns, :ma, :mo, :pa, :da, :sid, :f, 'actif')");
        $stmt->execute([':eid' => $etabId, ':n' => $nom, ':t' => $type, ':ns' => $numeroSerie, ':ma' => $marque, ':mo' => $modele, ':pa' => $prixAchat, ':da' => $dateAchat, ':sid' => $salleId, ':f' => $fournisseur]);
        return (int)$this->pdo->lastInsertId();
    }

    public function getAssets(int $etabId, ?string $type = null, ?string $statut = null): array
    {
        $sql = "SELECT a.*, s.nom AS salle_nom FROM inventaire_assets a LEFT JOIN salles s ON a.salle_id = s.id WHERE a.etablissement_id = :eid";
        $params = [':eid' => $etabId];
        if ($type) { $sql .= " AND a.type_asset = :t"; $params[':t'] = $type; }
        if ($statut) { $sql .= " AND a.statut = :s"; $params[':s'] = $statut; }
        $sql .= " ORDER BY a.type_asset, a.nom";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function generateQrCode(int $assetId): string
    {
        $token = 'ASSET-' . str_pad((string)$assetId, 6, '0', STR_PAD_LEFT) . '-' . substr(md5((string)$assetId . 'fronote'), 0, 8);
        $this->pdo->prepare("UPDATE inventaire_assets SET qr_token = :t WHERE id = :id")
            ->execute([':t' => $token, ':id' => $assetId]);
        return $token;
    }

    // ─── Maintenance ──────────────────────────────────────────────

    public function planifierMaintenance(int $assetId, string $type, string $datePrevue, string $description = '', int $responsableId = 0): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO inventaire_maintenance (asset_id, type_maintenance, date_prevue, description, responsable_id, statut) VALUES (:aid, :t, :dp, :d, :rid, 'planifiee')");
        $stmt->execute([':aid' => $assetId, ':t' => $type, ':dp' => $datePrevue, ':d' => $description, ':rid' => $responsableId]);
        return (int)$this->pdo->lastInsertId();
    }

    public function completerMaintenance(int $maintenanceId, string $rapport, float $cout = 0): void
    {
        $this->pdo->prepare("UPDATE inventaire_maintenance SET statut = 'terminee', date_realisation = NOW(), rapport = :r, cout = :c WHERE id = :id")
            ->execute([':r' => $rapport, ':c' => $cout, ':id' => $maintenanceId]);
    }

    public function getMaintenancesAVenir(int $etabId, int $joursAvant = 30): array
    {
        $stmt = $this->pdo->prepare("SELECT m.*, a.nom AS asset_nom, a.type_asset, a.numero_serie FROM inventaire_maintenance m JOIN inventaire_assets a ON m.asset_id = a.id WHERE a.etablissement_id = :eid AND m.statut = 'planifiee' AND m.date_prevue BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL :j DAY) ORDER BY m.date_prevue ASC");
        $stmt->execute([':eid' => $etabId, ':j' => $joursAvant]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ─── Prêts ────────────────────────────────────────────────────

    public function preterAsset(int $assetId, int $emprunteurId, string $emprunteurType, string $dateRetourPrevue, string $motif = ''): int
    {
        $this->pdo->prepare("UPDATE inventaire_assets SET statut = 'prete' WHERE id = :id AND statut = 'actif'")
            ->execute([':id' => $assetId]);

        $stmt = $this->pdo->prepare("INSERT INTO inventaire_prets (asset_id, emprunteur_id, emprunteur_type, date_pret, date_retour_prevue, motif, statut) VALUES (:aid, :eid, :et, NOW(), :drp, :m, 'en_cours')");
        $stmt->execute([':aid' => $assetId, ':eid' => $emprunteurId, ':et' => $emprunteurType, ':drp' => $dateRetourPrevue, ':m' => $motif]);
        return (int)$this->pdo->lastInsertId();
    }

    public function retournerAsset(int $pretId, string $etat = 'bon'): void
    {
        $pret = $this->pdo->prepare("SELECT asset_id FROM inventaire_prets WHERE id = :id");
        $pret->execute([':id' => $pretId]);
        $assetId = $pret->fetchColumn();

        $this->pdo->prepare("UPDATE inventaire_prets SET statut = 'retourne', date_retour_effectif = NOW(), etat_retour = :e WHERE id = :id")
            ->execute([':e' => $etat, ':id' => $pretId]);

        $this->pdo->prepare("UPDATE inventaire_assets SET statut = 'actif' WHERE id = :id")
            ->execute([':id' => $assetId]);
    }

    public function getPretsEnCours(int $etabId): array
    {
        $stmt = $this->pdo->prepare("SELECT p.*, a.nom AS asset_nom, a.type_asset, a.numero_serie FROM inventaire_prets p JOIN inventaire_assets a ON p.asset_id = a.id WHERE a.etablissement_id = :eid AND p.statut = 'en_cours' ORDER BY p.date_retour_prevue ASC");
        $stmt->execute([':eid' => $etabId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPretsEnRetard(int $etabId): array
    {
        $stmt = $this->pdo->prepare("SELECT p.*, a.nom AS asset_nom, a.type_asset FROM inventaire_prets p JOIN inventaire_assets a ON p.asset_id = a.id WHERE a.etablissement_id = :eid AND p.statut = 'en_cours' AND p.date_retour_prevue < CURDATE() ORDER BY p.date_retour_prevue ASC");
        $stmt->execute([':eid' => $etabId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ─── Signalement pannes ───────────────────────────────────────

    public function signalerPanne(int $assetId, int $signalePar, string $signaleParType, string $description, string $urgence = 'normal'): int
    {
        $this->pdo->prepare("UPDATE inventaire_assets SET statut = 'en_panne' WHERE id = :id")
            ->execute([':id' => $assetId]);

        $stmt = $this->pdo->prepare("INSERT INTO inventaire_incidents_tech (asset_id, signale_par, signale_par_type, description, urgence, statut) VALUES (:aid, :sp, :spt, :d, :u, 'ouvert')");
        $stmt->execute([':aid' => $assetId, ':sp' => $signalePar, ':spt' => $signaleParType, ':d' => $description, ':u' => $urgence]);
        return (int)$this->pdo->lastInsertId();
    }

    public function resoudrePanne(int $incidentId, string $resolution, float $cout = 0): void
    {
        $incident = $this->pdo->prepare("SELECT asset_id FROM inventaire_incidents_tech WHERE id = :id");
        $incident->execute([':id' => $incidentId]);
        $assetId = $incident->fetchColumn();

        $this->pdo->prepare("UPDATE inventaire_incidents_tech SET statut = 'resolu', resolution = :r, cout_reparation = :c, date_resolution = NOW() WHERE id = :id")
            ->execute([':r' => $resolution, ':c' => $cout, ':id' => $incidentId]);

        $this->pdo->prepare("UPDATE inventaire_assets SET statut = 'actif' WHERE id = :id")
            ->execute([':id' => $assetId]);
    }

    // ─── Amortissement ────────────────────────────────────────────

    public function calculerAmortissement(int $assetId, string $methode = 'lineaire', int $dureeAnnees = 5): array
    {
        $asset = $this->pdo->prepare("SELECT prix_achat, date_achat FROM inventaire_assets WHERE id = :id");
        $asset->execute([':id' => $assetId]);
        $a = $asset->fetch(PDO::FETCH_ASSOC);

        $prix = (float)$a['prix_achat'];
        $dateAchat = new \DateTime($a['date_achat']);
        $now = new \DateTime();
        $anneesEcoulees = $dateAchat->diff($now)->y + ($dateAchat->diff($now)->m / 12);

        if ($methode === 'lineaire') {
            $amortissementAnnuel = $prix / $dureeAnnees;
            $amortissementCumule = min($prix, $amortissementAnnuel * $anneesEcoulees);
            $valeurResiduelle = max(0, $prix - $amortissementCumule);
        } else { // degressif
            $coef = match(true) {
                $dureeAnnees <= 4 => 1.25,
                $dureeAnnees <= 6 => 1.75,
                default => 2.25
            };
            $taux = (1 / $dureeAnnees) * $coef;
            $valeurResiduelle = $prix * pow(1 - $taux, $anneesEcoulees);
            $amortissementCumule = $prix - $valeurResiduelle;
        }

        // Persist
        $this->pdo->prepare("INSERT INTO inventaire_amortissements (asset_id, methode, duree_annees, amortissement_cumule, valeur_residuelle, date_calcul) VALUES (:aid, :m, :d, :ac, :vr, NOW()) ON DUPLICATE KEY UPDATE amortissement_cumule=VALUES(amortissement_cumule), valeur_residuelle=VALUES(valeur_residuelle), date_calcul=NOW()")
            ->execute([':aid' => $assetId, ':m' => $methode, ':d' => $dureeAnnees, ':ac' => round($amortissementCumule, 2), ':vr' => round($valeurResiduelle, 2)]);

        return [
            'prix_achat' => $prix,
            'methode' => $methode,
            'duree_annees' => $dureeAnnees,
            'annees_ecoulees' => round($anneesEcoulees, 1),
            'amortissement_cumule' => round($amortissementCumule, 2),
            'valeur_residuelle' => round($valeurResiduelle, 2)
        ];
    }

    // ─── Statistiques parc ────────────────────────────────────────

    public function getStatistiquesParc(int $etabId): array
    {
        $parType = $this->pdo->prepare("SELECT type_asset, COUNT(*) AS nb, SUM(prix_achat) AS valeur_totale FROM inventaire_assets WHERE etablissement_id = :eid GROUP BY type_asset ORDER BY nb DESC");
        $parType->execute([':eid' => $etabId]);

        $parStatut = $this->pdo->prepare("SELECT statut, COUNT(*) AS nb FROM inventaire_assets WHERE etablissement_id = :eid GROUP BY statut");
        $parStatut->execute([':eid' => $etabId]);

        $valeurTotale = $this->pdo->prepare("SELECT COALESCE(SUM(prix_achat),0) FROM inventaire_assets WHERE etablissement_id = :eid");
        $valeurTotale->execute([':eid' => $etabId]);

        $valeurResiduelle = $this->pdo->prepare("SELECT COALESCE(SUM(ia.valeur_residuelle),0) FROM inventaire_amortissements ia JOIN inventaire_assets a ON ia.asset_id = a.id WHERE a.etablissement_id = :eid");
        $valeurResiduelle->execute([':eid' => $etabId]);

        $pannesOuvertes = $this->pdo->prepare("SELECT COUNT(*) FROM inventaire_incidents_tech it JOIN inventaire_assets a ON it.asset_id = a.id WHERE a.etablissement_id = :eid AND it.statut = 'ouvert'");
        $pannesOuvertes->execute([':eid' => $etabId]);

        return [
            'par_type' => $parType->fetchAll(PDO::FETCH_ASSOC),
            'par_statut' => $parStatut->fetchAll(PDO::FETCH_ASSOC),
            'valeur_achat_totale' => (float)$valeurTotale->fetchColumn(),
            'valeur_residuelle_totale' => (float)$valeurResiduelle->fetchColumn(),
            'pannes_ouvertes' => (int)$pannesOuvertes->fetchColumn()
        ];
    }

    // ─── Export registre ──────────────────────────────────────────

    public function exportRegistre(int $etabId): array
    {
        $stmt = $this->pdo->prepare("SELECT a.*, s.nom AS salle_nom, ia.valeur_residuelle, ia.methode AS methode_amortissement FROM inventaire_assets a LEFT JOIN salles s ON a.salle_id = s.id LEFT JOIN inventaire_amortissements ia ON ia.asset_id = a.id WHERE a.etablissement_id = :eid ORDER BY a.type_asset, a.nom");
        $stmt->execute([':eid' => $etabId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
