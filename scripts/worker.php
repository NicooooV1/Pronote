<?php
/**
 * Job Queue Worker — à exécuter via cron toutes les minutes :
 *   * * * * * php /path/to/scripts/worker.php >> /path/to/logs/worker.log 2>&1
 *
 * Traite les jobs en attente dans la table job_queue.
 */

require_once __DIR__ . '/../API/bootstrap.php';

$queue = app('queue');

$processed = $queue->processAll(50);

if ($processed > 0) {
    echo date('Y-m-d H:i:s') . " — Processed {$processed} job(s)\n";
}

// Purge les vieux jobs terminés (> 7 jours)
$purged = $queue->purge(7);
if ($purged > 0) {
    echo date('Y-m-d H:i:s') . " — Purged {$purged} old job(s)\n";
}
