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

    /* ───── RAPPELS & RELANCES ───── */

    /**
     * Détecte et marque les factures en retard (date_echeance dépassée, non payées).
     * @return int nombre de factures passées en retard
     */
    public function detecterRetards(): int
    {
        $stmt = $this->pdo->prepare("
            UPDATE factures SET statut = 'en_retard'
            WHERE statut = 'en_attente' AND date_echeance < CURDATE()
        ");
        $stmt->execute();
        return $stmt->rowCount();
    }

    /**
     * Envoie des rappels pour les factures en retard non encore rappelées.
     * @return int nombre de rappels envoyés
     */
    public function envoyerRappels(): int
    {
        $stmt = $this->pdo->query("
            SELECT f.id, f.numero, f.montant_ttc, f.date_echeance,
                   p.id AS parent_id, CONCAT(p.prenom, ' ', p.nom) AS parent_nom
            FROM factures f
            JOIN parents p ON f.parent_id = p.id
            WHERE f.statut = 'en_retard' AND f.rappel_envoye = 0
        ");
        $retards = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $count = 0;
        foreach ($retards as $f) {
            try {
                if (function_exists('app')) {
                    $notifService = app()->make('API\Services\NotificationService');
                    $notifService->create([
                        'user_id'   => $f['parent_id'],
                        'user_type' => 'parent',
                        'type'      => 'facture_rappel',
                        'titre'     => 'Rappel de paiement',
                        'message'   => "La facture {$f['numero']} de {$f['montant_ttc']}€ est impayée (échéance : " . date('d/m/Y', strtotime($f['date_echeance'])) . ").",
                        'lien'      => "facturation/detail.php?id={$f['id']}",
                        'priorite'  => 'haute',
                    ]);
                }
                $this->pdo->prepare("UPDATE factures SET rappel_envoye = 1, rappel_date = NOW() WHERE id = ?")
                           ->execute([$f['id']]);
                $count++;
            } catch (\Throwable $e) {
                error_log("Facturation::envoyerRappels error for facture {$f['id']}: " . $e->getMessage());
            }
        }
        return $count;
    }

    /* ───── AUTO-BILLING ───── */

    /**
     * Generate invoices automatically for a service (cantine, garderie, etc.)
     * @return int number of invoices created
     */
    public function genererFacturesAuto(string $type, string $mois, float $tarif, ?string $description = null): int
    {
        $count = 0;
        $desc = $description ?? ucfirst($type) . ' — ' . $mois;

        // Find parents with active subscriptions for this service
        $parentIds = [];
        switch ($type) {
            case 'cantine':
                $stmt = $this->pdo->prepare("
                    SELECT DISTINCT pe.parent_id
                    FROM cantine_reservations cr
                    JOIN parent_eleve pe ON pe.eleve_id = cr.eleve_id
                    WHERE DATE_FORMAT(cr.date_reservation, '%Y-%m') = ?
                ");
                $stmt->execute([$mois]);
                $parentIds = $stmt->fetchAll(\PDO::FETCH_COLUMN);
                break;
            case 'garderie':
                $stmt = $this->pdo->prepare("
                    SELECT DISTINCT pe.parent_id
                    FROM garderie_inscriptions gi
                    JOIN parent_eleve pe ON pe.eleve_id = gi.eleve_id
                    WHERE DATE_FORMAT(gi.date_inscription, '%Y-%m') = ?
                ");
                $stmt->execute([$mois]);
                $parentIds = $stmt->fetchAll(\PDO::FETCH_COLUMN);
                break;
            default:
                // All active parents
                $parentIds = $this->pdo->query("SELECT id FROM parents WHERE actif = 1")->fetchAll(\PDO::FETCH_COLUMN);
        }

        foreach ($parentIds as $pid) {
            // Check if invoice already exists for this parent/type/month
            $check = $this->pdo->prepare("SELECT id FROM factures WHERE parent_id = ? AND type = ? AND description LIKE ?");
            $check->execute([$pid, $type, "%$mois%"]);
            if ($check->fetch()) continue;

            $this->creerFacture([
                'parent_id' => $pid,
                'montant_ht' => $tarif,
                'montant_ttc' => $tarif,
                'date_echeance' => date('Y-m-t', strtotime($mois . '-01')),
                'type' => $type,
                'description' => $desc,
            ]);
            $count++;
        }
        return $count;
    }

    /**
     * Send escalating reminders: J+15, J+30, J+45.
     * @return int number of relances sent
     */
    public function envoyerRelancesEscaladees(): int
    {
        $count = 0;
        $today = date('Y-m-d');

        $stmt = $this->pdo->query("
            SELECT f.*, CONCAT(p.prenom, ' ', p.nom) AS parent_nom, p.mail AS parent_email
            FROM factures f
            JOIN parents p ON f.parent_id = p.id
            WHERE f.statut IN ('en_retard', 'en_attente')
              AND f.date_echeance < CURDATE()
            ORDER BY f.date_echeance ASC
        ");
        $factures = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($factures as $f) {
            $joursRetard = (int)((strtotime($today) - strtotime($f['date_echeance'])) / 86400);
            $relanceCount = (int)($f['relance_count'] ?? 0);

            $shouldRelance = false;
            if ($joursRetard >= 45 && $relanceCount < 3) $shouldRelance = true;
            elseif ($joursRetard >= 30 && $relanceCount < 2) $shouldRelance = true;
            elseif ($joursRetard >= 15 && $relanceCount < 1) $shouldRelance = true;

            if ($shouldRelance) {
                try {
                    $notifPath = __DIR__ . '/../../notifications/includes/NotificationService.php';
                    if (file_exists($notifPath)) {
                        require_once $notifPath;
                        $notif = new \NotificationService($this->pdo);
                        $niveau = $relanceCount >= 2 ? 'urgente' : 'haute';
                        $notif->creer(
                            $f['parent_id'], 'parent', 'facturation',
                            "Relance paiement n°" . ($relanceCount + 1),
                            "Facture {$f['numero']} — {$f['montant_ttc']}€ (échéance : " . date('d/m/Y', strtotime($f['date_echeance'])) . ")",
                            '/facturation/detail.php?id=' . $f['id'],
                            $niveau
                        );
                    }
                } catch (\Exception $e) {}

                $this->pdo->prepare("UPDATE factures SET relance_count = relance_count + 1, derniere_relance = CURDATE(), statut = 'en_retard' WHERE id = ?")
                           ->execute([$f['id']]);
                $count++;
            }
        }
        return $count;
    }

    /* ───── ACCOUNTING EXPORT ───── */

    /**
     * Export comptable (format CSV compatible logiciels compta).
     */
    public function getExportComptable(string $dateDebut, string $dateFin): array
    {
        $stmt = $this->pdo->prepare("
            SELECT f.numero, f.created_at AS date_facture, f.montant_ht, f.montant_tva, f.montant_ttc,
                   f.type, f.statut, f.description,
                   CONCAT(p.nom, ' ', p.prenom) AS client,
                   (SELECT COALESCE(SUM(pa.montant), 0) FROM paiements pa WHERE pa.facture_id = f.id) AS total_paye
            FROM factures f
            JOIN parents p ON f.parent_id = p.id
            WHERE f.created_at BETWEEN ? AND ?
            ORDER BY f.created_at
        ");
        $stmt->execute([$dateDebut, $dateFin . ' 23:59:59']);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /* ───── EXPORT ──��── */

    /**
     * Export des factures pour ExportService.
     */
    public function getFacturesForExport(array $filters = []): array
    {
        $factures = $this->getFactures($filters);
        $result = [];
        foreach ($factures as $f) {
            $result[] = [
                'Numéro'       => $f['numero'],
                'Parent'       => $f['parent_nom'],
                'Type'         => self::typesFacture()[$f['type']] ?? $f['type'],
                'Montant HT'   => number_format($f['montant_ht'], 2, ',', ' '),
                'TVA'          => number_format($f['montant_tva'], 2, ',', ' '),
                'Montant TTC'  => number_format($f['montant_ttc'], 2, ',', ' '),
                'Payé'         => number_format($f['montant_paye'] ?? 0, 2, ',', ' '),
                'Reste'        => number_format($f['montant_ttc'] - ($f['montant_paye'] ?? 0), 2, ',', ' '),
                'Statut'       => ucfirst(str_replace('_', ' ', $f['statut'])),
                'Échéance'     => $f['date_echeance'] ? date('d/m/Y', strtotime($f['date_echeance'])) : '',
                'Date création'=> $f['created_at'] ? date('d/m/Y', strtotime($f['created_at'])) : '',
            ];
        }
        return $result;
    }

    // ─── AVOIR / NOTE DE CRÉDIT ───

    public function creerAvoir(int $factureId, float $montant, string $motif): int
    {
        $facture = $this->getFacture($factureId);
        if (!$facture) throw new \RuntimeException('Facture introuvable');

        $numero = 'AV-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        $stmt = $this->pdo->prepare("
            INSERT INTO avoirs (facture_id, numero, montant, motif, parent_id, created_at)
            VALUES (:f, :n, :m, :mo, :p, NOW())
        ");
        $stmt->execute([':f' => $factureId, ':n' => $numero, ':m' => $montant, ':mo' => $motif, ':p' => $facture['parent_id']]);
        return (int)$this->pdo->lastInsertId();
    }

    public function getAvoirs(?int $parentId = null): array
    {
        $sql = "SELECT a.*, f.numero AS facture_numero FROM avoirs a LEFT JOIN factures f ON a.facture_id = f.id WHERE 1=1";
        $params = [];
        if ($parentId) { $sql .= " AND a.parent_id = :p"; $params[':p'] = $parentId; }
        $sql .= " ORDER BY a.created_at DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    // ─── TABLEAU DE BORD TRÉSORERIE ───

    public function getDashboardTresorerie(string $dateDebut, string $dateFin): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                COUNT(*) AS total_factures,
                SUM(montant_ttc) AS total_ttc,
                SUM(CASE WHEN statut = 'payee' THEN montant_ttc ELSE 0 END) AS total_encaisse,
                SUM(CASE WHEN statut IN ('en_attente','en_retard') THEN montant_ttc ELSE 0 END) AS total_impaye,
                COUNT(CASE WHEN statut = 'en_retard' THEN 1 END) AS nb_retards,
                COUNT(CASE WHEN statut = 'payee' THEN 1 END) AS nb_payees
            FROM factures WHERE created_at BETWEEN :d AND :f
        ");
        $stmt->execute([':d' => $dateDebut, ':f' => $dateFin . ' 23:59:59']);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];
    }

    // ─── PAIEMENT EN PLUSIEURS FOIS ───

    public function creerEcheancier(int $factureId, int $nbEcheances): array
    {
        $facture = $this->getFacture($factureId);
        if (!$facture) throw new \RuntimeException('Facture introuvable');

        $montantParEcheance = round($facture['montant_ttc'] / $nbEcheances, 2);
        $echeances = [];

        for ($i = 0; $i < $nbEcheances; $i++) {
            $dateEcheance = date('Y-m-d', strtotime("+{$i} months", strtotime($facture['date_echeance'] ?? date('Y-m-d'))));
            $montant = ($i === $nbEcheances - 1) ? $facture['montant_ttc'] - ($montantParEcheance * ($nbEcheances - 1)) : $montantParEcheance;

            $stmt = $this->pdo->prepare("INSERT INTO facture_echeancier (facture_id, numero_echeance, montant, date_echeance, statut) VALUES (:f, :n, :m, :d, 'en_attente')");
            $stmt->execute([':f' => $factureId, ':n' => $i + 1, ':m' => $montant, ':d' => $dateEcheance]);
            $echeances[] = ['echeance' => $i + 1, 'montant' => $montant, 'date' => $dateEcheance];
        }
        return $echeances;
    }

    public function getEcheancier(int $factureId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM facture_echeancier WHERE facture_id = :f ORDER BY numero_echeance");
        $stmt->execute([':f' => $factureId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
