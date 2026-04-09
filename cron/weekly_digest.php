<?php
/**
 * Cron — Digest hebdomadaire pour les parents
 * 0 18 * * 5 php /path/to/fronote/cron/weekly_digest.php
 */
declare(strict_types=1);
if (php_sapi_name() !== 'cli') { http_response_code(403); exit('CLI only.'); }
require_once dirname(__DIR__) . '/API/bootstrap.php';

$log = function(string $msg) { echo '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n"; };
$log('=== Weekly Digest: Préparation des résumés parents ===');

$pdo = app('db')->getConnection();
$emailQueue = app('email_queue');

$weekStart = date('Y-m-d', strtotime('monday this week'));
$weekEnd = date('Y-m-d');

// Get parents who opted for weekly digest
$parents = $pdo->query("SELECT p.id, p.email, p.nom, p.prenom FROM parents p
    LEFT JOIN portail_parents_preferences pp ON pp.parent_id = p.id
    WHERE p.email IS NOT NULL AND p.email != '' AND COALESCE(pp.notifications_resume_quotidien, 1) = 1")->fetchAll(PDO::FETCH_ASSOC);

$log('Parents à notifier: ' . count($parents));

$queued = 0;
foreach ($parents as $parent) {
    // Get children
    $enfants = $pdo->prepare("SELECT e.id, e.nom, e.prenom, e.classe FROM eleves e
        JOIN parent_eleve pe ON e.id = pe.eleve_id WHERE pe.parent_id = :pid AND e.actif = 1");
    $enfants->execute([':pid' => $parent['id']]);
    $enfants = $enfants->fetchAll(PDO::FETCH_ASSOC);

    if (empty($enfants)) continue;

    $body = "Bonjour {$parent['prenom']} {$parent['nom']},\n\nVoici le résumé de la semaine pour vos enfants :\n\n";

    foreach ($enfants as $enfant) {
        $body .= "--- {$enfant['prenom']} {$enfant['nom']} ({$enfant['classe']}) ---\n";

        // Notes this week
        $notes = $pdo->prepare("SELECT COUNT(*) AS nb, ROUND(AVG(note),1) AS moy FROM notes WHERE id_eleve = :eid AND date_evaluation BETWEEN :s AND :e");
        $notes->execute([':eid' => $enfant['id'], ':s' => $weekStart, ':e' => $weekEnd]);
        $n = $notes->fetch(PDO::FETCH_ASSOC);
        $body .= "Notes: {$n['nb']} nouvelles" . ($n['moy'] ? " (moyenne: {$n['moy']})" : '') . "\n";

        // Absences this week
        $abs = $pdo->prepare("SELECT COUNT(*) FROM absences WHERE id_eleve = :eid AND date_debut BETWEEN :s AND :e");
        $abs->execute([':eid' => $enfant['id'], ':s' => $weekStart, ':e' => $weekEnd]);
        $body .= "Absences: " . $abs->fetchColumn() . "\n";

        // Upcoming events
        $events = $pdo->prepare("SELECT COUNT(*) FROM evenements WHERE date_debut BETWEEN :s AND DATE_ADD(:e2, INTERVAL 7 DAY)");
        $events->execute([':s' => $weekEnd, ':e2' => $weekEnd]);
        $body .= "Événements à venir: " . $events->fetchColumn() . "\n\n";
    }

    $body .= "Connectez-vous à Fronote pour plus de détails.\n\nCordialement,\nL'équipe Fronote";

    try {
        $emailQueue->queue($parent['email'], 'Résumé hebdomadaire Fronote', $body);
        $queued++;
    } catch (\Throwable $e) {
        $log('Erreur email parent #' . $parent['id'] . ': ' . $e->getMessage());
    }
}

$log("Digests en file d'attente: {$queued}");
