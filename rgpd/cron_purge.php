<?php
/**
 * M23 – RGPD — Cron de purge automatique
 * Usage: php /var/www/html/Pronote/rgpd/cron_purge.php
 * Cron recommandé: 0 3 * * 0 (dimanche à 3h)
 */
if (php_sapi_name() !== 'cli') {
    die('Ce script ne peut être exécuté qu\'en ligne de commande.');
}

require_once __DIR__ . '/../API/bootstrap.php';
require_once __DIR__ . '/includes/AuditRgpdService.php';

$service = new AuditRgpdService(getPDO());
$results = $service->executerPurge();

echo "[" . date('Y-m-d H:i:s') . "] Purge RGPD terminée\n";
foreach ($results as $table => $r) {
    if (isset($r['error'])) {
        echo "  ✗ {$table}: ERREUR - {$r['error']}\n";
    } else {
        echo "  ✓ {$table}: {$r['purged']} enregistrement(s) purgé(s) (< {$r['cutoff']})\n";
    }
}
echo "Terminé.\n";
