<?php
// Bootstrap the application
$app = require_once __DIR__ . '/API/bootstrap.php';

// helpers
function section($t){ echo "=== {$t} ===\n"; }

use API\Database\QueryBuilder;

$db = $app->make('db');
$pdo = $db->getConnection();

section("Test 1 : SELECT simple");
$qb1 = new QueryBuilder($pdo, 'notes');
$sql1 = $qb1->select(['id', 'note', 'coefficient'])->toSql();
echo "SQL: {$sql1}\n\n";

section("Test 2 : SELECT avec WHERE/ORDER/LIMIT");
$qb2 = new QueryBuilder($pdo, 'notes');
$sql2 = $qb2->where('id_eleve', 5)->where('note', '>=', 10)->orderBy('date_creation', 'DESC')->limit(10)->toSql();
echo "SQL: {$sql2}\n\n";

// Exécution réelle si table existe
section("Test 3 : Exécution réelle (si DB existe)");
try {
    $qb3 = new QueryBuilder($pdo, 'notes');
    $result = $qb3->limit(1)->first();
    echo "First note: " . json_encode($result, JSON_PRETTY_PRINT) . "\n";
} catch (\Exception $e) {
    echo "Erreur (normal si pas de données): " . $e->getMessage() . "\n";
}
