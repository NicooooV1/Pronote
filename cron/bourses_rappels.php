<?php
/**
 * Cron — Rappels campagne de bourses
 * 0 9 * * 1 php /path/to/fronote/cron/bourses_rappels.php
 */
declare(strict_types=1);
if (php_sapi_name() !== 'cli') { http_response_code(403); exit('CLI only.'); }
require_once dirname(__DIR__) . '/API/bootstrap.php';

$log = function(string $msg) { echo '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n"; };
$log('=== Bourses: Rappels campagne ===');

$pdo = app('db')->getConnection();

// Active scholarship types
$types = $pdo->query("SELECT id, nom, annee_scolaire FROM bourses_types WHERE actif = 1")->fetchAll(PDO::FETCH_ASSOC);
if (empty($types)) {
    $log('Aucune campagne active.');
    exit;
}

$sent = 0;
foreach ($types as $type) {
    // Find parents with enrolled children who haven't submitted
    $stmt = $pdo->prepare("SELECT DISTINCT p.id, p.email, p.nom, p.prenom
        FROM parents p
        JOIN parent_eleve pe ON p.id = pe.parent_id
        JOIN eleves e ON pe.eleve_id = e.id AND e.actif = 1
        WHERE p.id NOT IN (
            SELECT DISTINCT bd.parent_id FROM bourses_demandes bd
            WHERE bd.type_bourse_id = :tid AND bd.statut != 'brouillon'
        )");
    $stmt->execute([':tid' => $type['id']]);
    $parents = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($parents as $parent) {
        try {
            $pdo->prepare("INSERT INTO notifications_globales (user_id, user_type, type, titre, contenu, lien, importance)
                VALUES (:uid, 'parent', 'general', :titre, :contenu, '/bourses/', 'normale')")
                ->execute([
                    ':uid' => $parent['id'],
                    ':titre' => 'Campagne de bourses: ' . $type['nom'],
                    ':contenu' => 'La campagne de bourses ' . $type['nom'] . ' est ouverte. Pensez à déposer votre demande.'
                ]);
            $sent++;
        } catch (\Throwable $e) {
            $log('Erreur notification parent #' . $parent['id'] . ': ' . $e->getMessage());
        }
    }
}

$log("Rappels envoyés: {$sent}");
