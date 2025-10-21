<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Query Builder - Pronote</title>
    <link rel="stylesheet" href="test-styles.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>🔍 Test du Query Builder</h1>
            <p>Vérification de la construction de requêtes SQL</p>
        </header>
        <main>
<?php
$app = require_once __DIR__ . '/API/bootstrap.php';

function section($t){ echo "<div class='test-section'><h2>{$t}</h2><div class='test-content'>"; }
function sectionEnd(){ echo "</div></div>"; }

use API\Database\QueryBuilder;

$db = $app->make('db');
$pdo = $db->getConnection();

section("Test 1 : SELECT simple");
$qb1 = new QueryBuilder($pdo, 'notes');
$sql1 = $qb1->select(['id', 'note', 'coefficient'])->toSql();
echo "<div class='sql-query'>" . htmlspecialchars($sql1) . "</div>";
sectionEnd();

section("Test 2 : SELECT avec WHERE/ORDER/LIMIT");
$qb2 = new QueryBuilder($pdo, 'notes');
$sql2 = $qb2->where('id_eleve', 5)->where('note', '>=', 10)->orderBy('date_creation', 'DESC')->limit(10)->toSql();
echo "<div class='sql-query'>" . htmlspecialchars($sql2) . "</div>";
sectionEnd();

section("Test 3 : Exécution réelle (si DB existe)");
try {
    $qb3 = new QueryBuilder($pdo, 'notes');
    $result = $qb3->limit(1)->first();
    if ($result) {
        echo "<div class='success-message'><strong>✓ Première note récupérée</strong></div>";
        echo "<pre class='json-view'>" . htmlspecialchars(json_encode($result, JSON_PRETTY_PRINT)) . "</pre>";
    } else {
        echo "<div class='info-message'>Aucune donnée trouvée dans la table</div>";
    }
} catch (\Exception $e) {
    echo "<div class='error-message'>⚠️ " . htmlspecialchars($e->getMessage()) . "</div>";
}
sectionEnd();
?>
        </main>
        <footer>
            <p>Pronote API Test Suite &copy; 2024</p>
        </footer>
    </div>
</body>
</html>
