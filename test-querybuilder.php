<?php
// Bootstrap the application
$app = require_once __DIR__ . '/API/bootstrap.php';

use Pronote\Database\QueryBuilder;

// Get database from container
$db = $app->make('db');
$pdo = $db->getPDO();

echo "=== Test 1 : SELECT simple ===\n";
$qb1 = new QueryBuilder($pdo);
$sql1 = $qb1->table('notes')
    ->select('id', 'note', 'coefficient')
    ->toSql();
echo "SQL: {$sql1}\n\n";

echo "=== Test 2 : SELECT avec WHERE ===\n";
$qb2 = new QueryBuilder($pdo);
$sql2 = $qb2->table('notes')
    ->where('id_eleve', 5)
    ->where('note', '>=', 10)
    ->toSql();
echo "SQL: {$sql2}\n\n";

echo "=== Test 3 : SELECT avec JOIN ===\n";
$qb3 = new QueryBuilder($pdo);
$sql3 = $qb3->table('notes')
    ->select('notes.*', 'matieres.nom as matiere_nom')
    ->join('matieres', 'notes.id_matiere', '=', 'matieres.id')
    ->where('notes.id_eleve', 5)
    ->toSql();
echo "SQL: {$sql3}\n\n";

echo "=== Test 4 : SELECT avec ORDER BY et LIMIT ===\n";
$qb4 = new QueryBuilder($pdo);
$sql4 = $qb4->table('notes')
    ->where('id_eleve', 5)
    ->orderBy('date_creation', 'DESC')
    ->limit(10)
    ->offset(5)
    ->toSql();
echo "SQL: {$sql4}\n\n";

echo "=== Test 5 : WHERE IN ===\n";
$qb5 = new QueryBuilder($pdo);
$sql5 = $qb5->table('eleves')
    ->whereIn('classe', ['6A', '6B', '6C'])
    ->toSql();
echo "SQL: {$sql5}\n\n";

echo "=== Test 6 : WHERE BETWEEN ===\n";
$qb6 = new QueryBuilder($pdo);
$sql6 = $qb6->table('notes')
    ->whereBetween('note', 10, 15)
    ->toSql();
echo "SQL: {$sql6}\n\n";

echo "=== Test 7 : GROUP BY avec HAVING ===\n";
$qb7 = new QueryBuilder($pdo);
$sql7 = $qb7->table('notes')
    ->select('id_eleve', 'AVG(note) as moyenne')
    ->groupBy('id_eleve')
    ->having('AVG(note)', '>=', 10)
    ->toSql();
echo "SQL: {$sql7}\n\n";

echo "=== Test 8 : COUNT ===\n";
$qb8 = new QueryBuilder($pdo);
$sql8 = $qb8->table('notes')
    ->where('id_eleve', 5)
    ->toCountSql();
echo "SQL: {$sql8}\n\n";

echo "=== Test 9 : Multiple WHERE (AND/OR) ===\n";
$qb9 = new QueryBuilder($pdo);
$sql9 = $qb9->table('notes')
    ->where('id_eleve', 5)
    ->where('note', '>=', 10)
    ->orWhere('coefficient', '>', 2)
    ->toSql();
echo "SQL: {$sql9}\n\n";

echo "=== Test 10 : LEFT JOIN ===\n";
$qb10 = new QueryBuilder($pdo);
$sql10 = $qb10->table('eleves')
    ->select('eleves.*', 'classes.nom as classe_nom')
    ->leftJoin('classes', 'eleves.id_classe', '=', 'classes.id')
    ->toSql();
echo "SQL: {$sql10}\n\n";

// Test avec vraie base de données (si tables existent)
echo "=== Test 11 : Exécution réelle (si DB existe) ===\n";
try {
    $qb11 = new QueryBuilder($pdo);
    $result = $qb11->table('notes')->limit(1)->first();
    echo "First note: " . json_encode($result, JSON_PRETTY_PRINT) . "\n";
} catch (\Exception $e) {
    echo "Erreur (normal si pas de données): " . $e->getMessage() . "\n";
}
