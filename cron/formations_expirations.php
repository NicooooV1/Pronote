<?php
/**
 * Cron — Alertes expirations certifications (formation continue)
 * 0 8 * * * php /path/to/fronote/cron/formations_expirations.php
 */
declare(strict_types=1);
if (php_sapi_name() !== 'cli') { http_response_code(403); exit('CLI only.'); }
require_once dirname(__DIR__) . '/API/bootstrap.php';

$log = function(string $msg) { echo '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n"; };
$log('=== Formations: Vérification expirations certifications ===');

$pdo = app('db')->getConnection();

// Certifications expiring within 30 days
$stmt = $pdo->prepare("SELECT fc.*, DATEDIFF(fc.date_expiration, CURDATE()) AS jours_restants
    FROM formation_certifications fc
    WHERE fc.statut = 'valide' AND fc.date_expiration BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
    ORDER BY fc.date_expiration");
$stmt->execute();
$expirations = $stmt->fetchAll(PDO::FETCH_ASSOC);

$log('Certifications expirant dans 30 jours: ' . count($expirations));

$notified = 0;
foreach ($expirations as $cert) {
    try {
        $pdo->prepare("INSERT INTO notifications_globales (user_id, user_type, type, titre, contenu, importance)
            VALUES (:uid, :utype, 'general', :titre, :contenu, 'importante')")
            ->execute([
                ':uid' => $cert['personnel_id'],
                ':utype' => $cert['personnel_type'],
                ':titre' => 'Certification expirante: ' . $cert['intitule'],
                ':contenu' => "Votre certification \"{$cert['intitule']}\" expire dans {$cert['jours_restants']} jours ({$cert['date_expiration']}). Pensez au renouvellement."
            ]);
        $notified++;
    } catch (\Throwable $e) {
        $log('Erreur: ' . $e->getMessage());
    }
}

// Mark expired certifications
$pdo->exec("UPDATE formation_certifications SET statut = 'expire' WHERE statut = 'valide' AND date_expiration < CURDATE()");

$log("Notifications envoyées: {$notified}");
