<?php
/**
 * M33 – Facturation — Service
 */
class FacturationService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /* ───── FACTURES ───── */

    public function getFactures(array $filters = []): array
    {
        $sql = "SELECT f.*, CONCAT(p.prenom, ' ', p.nom) AS parent_nom,
                       (SELECT COALESCE(SUM(pa.montant), 0) FROM paiements pa WHERE pa.facture_id = f.id) AS montant_paye
                FROM factures f
                JOIN parents p ON f.parent_id = p.id
                WHERE 1=1";
        $params = [];
        if (!empty($filters['statut'])) { $sql .= ' AND f.statut = ?'; $params[] = $filters['statut']; }
        if (!empty($filters['parent_id'])) { $sql .= ' AND f.parent_id = ?'; $params[] = $filters['parent_id']; }
        $sql .= ' ORDER BY f.created_at DESC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getFacture(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT f.*, CONCAT(p.prenom, ' ', p.nom) AS parent_nom, p.email AS parent_email FROM factures f JOIN parents p ON f.parent_id = p.id WHERE f.id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function creerFacture(array $d): int
    {
        $numero = 'FAC-' . date('Ym') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
        $stmt = $this->pdo->prepare("INSERT INTO factures (numero, parent_id, montant_ht, montant_tva, montant_ttc, date_echeance, statut, type, description) VALUES (?,?,?,?,?,?,?,?,?)");
        $stmt->execute([$numero, $d['parent_id'], $d['montant_ht'], $d['montant_tva'] ?? 0, $d['montant_ttc'], $d['date_echeance'], 'en_attente', $d['type'] ?? 'scolarite', $d['description'] ?? null]);
        return $this->pdo->lastInsertId();
    }

    /* ───── LIGNES FACTURE ───── */

    public function getLignes(int $factureId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM facture_lignes WHERE facture_id = ? ORDER BY id");
        $stmt->execute([$factureId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function ajouterLigne(int $factureId, string $description, int $quantite, float $prix): void
    {
        $stmt = $this->pdo->prepare("INSERT INTO facture_lignes (facture_id, description, quantite, prix_unitaire) VALUES (?,?,?,?)");
        $stmt->execute([$factureId, $description, $quantite, $prix]);
        $this->recalculerMontants($factureId);
    }

    private function recalculerMontants(int $factureId): void
    {
        $stmt = $this->pdo->prepare("SELECT SUM(quantite * prix_unitaire) FROM facture_lignes WHERE facture_id = ?");
        $stmt->execute([$factureId]);
        $ht = (float)$stmt->fetchColumn();
        $tva = round($ht * 0.20, 2);
        $ttc = $ht + $tva;
        $this->pdo->prepare("UPDATE factures SET montant_ht = ?, montant_tva = ?, montant_ttc = ? WHERE id = ?")->execute([$ht, $tva, $ttc, $factureId]);
    }

    /* ───── PAIEMENTS ───── */

    public function getPaiements(int $factureId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM paiements WHERE facture_id = ? ORDER BY date_paiement DESC");
        $stmt->execute([$factureId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function enregistrerPaiement(int $factureId, float $montant, string $mode): void
    {
        $this->pdo->prepare("INSERT INTO paiements (facture_id, montant, date_paiement, mode_paiement) VALUES (?,?,NOW(),?)")->execute([$factureId, $montant, $mode]);
        // Mettre à jour statut
        $facture = $this->getFacture($factureId);
        $stmt = $this->pdo->prepare("SELECT COALESCE(SUM(montant), 0) FROM paiements WHERE facture_id = ?");
        $stmt->execute([$factureId]);
        $paye = (float)$stmt->fetchColumn();
        $newStatut = $paye >= $facture['montant_ttc'] ? 'payee' : 'partielle';
        $this->pdo->prepare("UPDATE factures SET statut = ? WHERE id = ?")->execute([$newStatut, $factureId]);
    }

    /* ───── HELPERS ───── */

    public function getParents(): array
    {
        return $this->pdo->query("SELECT id, prenom, nom, email FROM parents ORDER BY nom")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getStats(): array
    {
        $total = $this->pdo->query("SELECT COALESCE(SUM(montant_ttc), 0) FROM factures WHERE statut != 'annulee'")->fetchColumn();
        $paye = $this->pdo->query("SELECT COALESCE(SUM(montant), 0) FROM paiements")->fetchColumn();
        $impayees = $this->pdo->query("SELECT COUNT(*) FROM factures WHERE statut IN ('en_attente','en_retard')")->fetchColumn();
        return ['total_facture' => $total, 'total_paye' => $paye, 'impayees' => $impayees];
    }

    public function getMesFactures(int $parentId): array
    {
        return $this->getFactures(['parent_id' => $parentId]);
    }

    public static function typesFacture(): array
    {
        return ['scolarite' => 'Scolarité', 'cantine' => 'Cantine', 'transport' => 'Transport', 'activite' => 'Activité', 'autre' => 'Autre'];
    }

    public static function modesPaiement(): array
    {
        return ['carte' => 'Carte bancaire', 'virement' => 'Virement', 'cheque' => 'Chèque', 'especes' => 'Espèces', 'prelevement' => 'Prélèvement'];
    }

    public static function badgeStatut(string $s): string
    {
        $m = ['en_attente' => 'warning', 'payee' => 'success', 'partielle' => 'info', 'en_retard' => 'danger', 'annulee' => 'secondary'];
        return '<span class="badge badge-' . ($m[$s] ?? 'secondary') . '">' . ucfirst(str_replace('_', ' ', $s)) . '</span>';
    }
}
