<?php
/**
 * Cron — Calcul quotidien des scores de risque (analyse prédictive)
 * 0 3 * * * php /path/to/fronote/cron/intelligence_calcul.php
 */
declare(strict_types=1);
if (php_sapi_name() !== 'cli') { http_response_code(403); exit('CLI only.'); }
require_once dirname(__DIR__) . '/API/bootstrap.php';

$log = function(string $msg) { echo '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n"; };
$log('=== Intelligence: Calcul des scores de risque ===');

$features = app('features');
if (!$features->isEnabled('intelligence.enabled')) {
    $log('Module intelligence désactivé. Abandon.');
    exit;
}

$pdo = app('db')->getConnection();

// Get config
$config = $pdo->query("SELECT * FROM intelligence_config LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$pAbsences = (float)($config['poids_absences'] ?? 0.30);
$pNotes = (float)($config['poids_notes'] ?? 0.35);
$pDiscipline = (float)($config['poids_discipline'] ?? 0.20);
$pEngagement = (float)($config['poids_engagement'] ?? 0.15);
$seuilJaune = (float)($config['seuil_jaune'] ?? 40);
$seuilOrange = (float)($config['seuil_orange'] ?? 60);
$seuilRouge = (float)($config['seuil_rouge'] ?? 80);

$annee = $pdo->query("SELECT code FROM annees_scolaires WHERE actif = 1 LIMIT 1")->fetchColumn() ?: date('Y');

// All active students
$eleves = $pdo->query("SELECT id, classe FROM eleves WHERE actif = 1")->fetchAll(PDO::FETCH_ASSOC);
$log('Élèves à calculer: ' . count($eleves));

$updated = 0;
$alertes = 0;

$stmtAbsences = $pdo->prepare("SELECT COUNT(*) FROM absences WHERE id_eleve = :eid AND justifiee = 0");
$stmtNotes = $pdo->prepare("SELECT AVG(note) FROM notes WHERE id_eleve = :eid");
$stmtDiscipline = $pdo->prepare("SELECT COUNT(*) FROM incidents WHERE eleve_id = :eid");

$stmtUpsert = $pdo->prepare("INSERT INTO intelligence_scores (etablissement_id, eleve_id, annee_scolaire, score_risque, score_absences, score_notes, score_discipline, score_engagement, niveau_alerte, date_calcul)
    VALUES (1, :eid, :annee, :risque, :abs, :notes, :disc, :eng, :niveau, NOW())
    ON DUPLICATE KEY UPDATE score_risque=VALUES(score_risque), score_absences=VALUES(score_absences), score_notes=VALUES(score_notes), score_discipline=VALUES(score_discipline), score_engagement=VALUES(score_engagement), niveau_alerte=VALUES(niveau_alerte), date_calcul=NOW()");

foreach ($eleves as $e) {
    $stmtAbsences->execute([':eid' => $e['id']]);
    $nbAbsNJ = (int)$stmtAbsences->fetchColumn();
    $scoreAbs = min(100, $nbAbsNJ * 10); // 10 points per unjustified absence

    $stmtNotes->execute([':eid' => $e['id']]);
    $moy = (float)$stmtNotes->fetchColumn();
    $scoreNotes = $moy > 0 ? max(0, 100 - ($moy * 5)) : 50; // Lower grades = higher risk

    $stmtDiscipline->execute([':eid' => $e['id']]);
    $nbIncidents = (int)$stmtDiscipline->fetchColumn();
    $scoreDisc = min(100, $nbIncidents * 20);

    $scoreEng = ($nbAbsNJ > 5) ? 60 : ($nbAbsNJ > 2 ? 30 : 0); // Simplified engagement

    $risque = round($scoreAbs * $pAbsences + $scoreNotes * $pNotes + $scoreDisc * $pDiscipline + $scoreEng * $pEngagement, 2);
    $niveau = $risque >= $seuilRouge ? 'rouge' : ($risque >= $seuilOrange ? 'orange' : ($risque >= $seuilJaune ? 'jaune' : 'vert'));

    $stmtUpsert->execute([':eid' => $e['id'], ':annee' => $annee, ':risque' => $risque, ':abs' => $scoreAbs, ':notes' => $scoreNotes, ':disc' => $scoreDisc, ':eng' => $scoreEng, ':niveau' => $niveau]);
    $updated++;

    // Generate alert for high-risk students
    if ($niveau === 'rouge' || $niveau === 'orange') {
        $pdo->prepare("INSERT INTO intelligence_alertes (etablissement_id, eleve_id, type_alerte, message, score_declencheur)
            VALUES (1, :eid, :type, :msg, :score)")
            ->execute([':eid' => $e['id'], ':type' => 'risque_' . $niveau, ':msg' => "Score de risque: {$risque}/100 ({$niveau})", ':score' => $risque]);
        $alertes++;
    }
}

$log("Scores calculés: {$updated}, Alertes générées: {$alertes}");
$log('Terminé.');
