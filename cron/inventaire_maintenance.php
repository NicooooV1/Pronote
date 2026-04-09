<?php
/**
 * Cron — Rappels maintenance préventive (inventaire IT)
 * 0 7 * * * php /path/to/fronote/cron/inventaire_maintenance.php
 */
declare(strict_types=1);
if (php_sapi_name() !== 'cli') { http_response_code(403); exit('CLI only.'); }
require_once dirname(__DIR__) . '/API/bootstrap.php';

$log = function(string $msg) { echo '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n"; };
$log('=== Inventaire: Rappels maintenance ===');

$pdo = app('db')->getConnection();

// Maintenance due today or overdue
$stmt = $pdo->query("SELECT m.*, a.nom AS asset_nom, a.code_inventaire, a.categorie
    FROM inventaire_maintenance m
    JOIN inventaire_assets a ON m.asset_id = a.id
    WHERE m.statut = 'planifiee' AND m.date_planifiee <= CURDATE()
    ORDER BY m.date_planifiee");
$maintenances = $stmt->fetchAll(PDO::FETCH_ASSOC);

$log('Maintenances dues: ' . count($maintenances));

// Notify admins
$admins = $pdo->query("SELECT id FROM administrateurs WHERE actif = 1")->fetchAll(PDO::FETCH_COLUMN);

$notified = 0;
foreach ($maintenances as $m) {
    $overdue = $m['date_planifiee'] < date('Y-m-d') ? ' (EN RETARD)' : '';
    foreach ($admins as $adminId) {
        try {
            $pdo->prepare("INSERT INTO notifications_globales (user_id, user_type, type, titre, contenu, importance)
                VALUES (:uid, 'administrateur', 'general', :titre, :contenu, :imp)")
                ->execute([
                    ':uid' => $adminId,
                    ':titre' => "Maintenance {$m['type']}: {$m['asset_nom']}{$overdue}",
                    ':contenu' => "Maintenance {$m['type']} prévue le {$m['date_planifiee']} pour {$m['asset_nom']} ({$m['code_inventaire']}). {$m['description']}",
                    ':imp' => $overdue ? 'urgente' : 'normale'
                ]);
            $notified++;
        } catch (\Throwable $e) {
            $log('Erreur: ' . $e->getMessage());
        }
    }
}

// Check overdue loans
$overdueLoans = $pdo->query("SELECT ip.*, a.nom AS asset_nom FROM inventaire_prets ip
    JOIN inventaire_assets a ON ip.asset_id = a.id
    WHERE ip.statut = 'en_cours' AND ip.date_retour_prevue < CURDATE()")->fetchAll(PDO::FETCH_ASSOC);

$pdo->exec("UPDATE inventaire_prets SET statut = 'en_retard' WHERE statut = 'en_cours' AND date_retour_prevue < CURDATE()");
$log("Prêts en retard marqués: " . count($overdueLoans));
$log("Notifications envoyées: {$notified}");
