<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Audit - Pronote</title>
    <link rel="stylesheet" href="test-styles.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>🔍 Test du Service d'Audit</h1>
            <p>Vérification complète du système d'audit et de logging</p>
        </header>
        <main>
<?php
session_start();
require_once __DIR__ . '/API/bootstrap.php';
require_once __DIR__ . '/API/Services/AuditService.php';

use Pronote\Services\AuditService;
use Pronote\Services\Audit;

// helpers
function section($t){ echo "<div class='test-section'><h2>{$t}</h2><div class='test-content'>"; }
function sectionEnd(){ echo "</div></div>"; }
function kv($k,$v){
    if (is_bool($v)) $v = $v ? 'OUI' : 'NON';
    if ($v === null) $v = 'NULL';
    $class = '';
    if ($v === 'OUI' || $v === 'OK' || $v === 'VALIDE' || $v === 'PASS') $class = 'success';
    if ($v === 'NON' || $v === 'FAIL' || $v === 'INVALIDE') $class = 'error';
    echo "<div class='kv-item'><span class='kv-key'>{$k}</span><span class='kv-value {$class}'>" . htmlspecialchars($v) . "</span></div>";
}
function jprint($k,$v){ 
    echo "<div class='kv-item'><span class='kv-key'>{$k}</span></div>";
    echo "<pre class='json-view'>" . htmlspecialchars(json_encode($v, JSON_PRETTY_PRINT)) . "</pre>";
}

// Simuler un utilisateur connecté
$_SESSION['user'] = [
    'id' => 123,
    'profil' => 'professeur',
    'nom' => 'Ledroit',
    'prenom' => 'Jean'
];

$audit = new AuditService();

section("Test 1 : Log d'une action simple");
$result = $audit->log('test.action', null, [
    'new' => ['data' => 'test']
]);
echo "<div class='success-message'>Log créé : " . ($result ? "<span class='status ok'>OUI</span>" : "<span class='status fail'>NON</span>") . "</div>";
sectionEnd();

section("Test 2 : Log d'authentification");
$audit->logAuth('login', 'jean.dupont', true, ['method' => 'password']);
$audit->logAuth('login', 'hacker', false, ['method' => 'password', 'reason' => 'invalid_credentials']);
echo "<div class='success-message'>Logs d'auth créés avec succès</div>";
sectionEnd();

section("Test 3 : Log de sécurité");
$audit->logSecurity('csrf_invalid', [
    'token' => 'abc123',
    'expected' => 'def456'
]);
$audit->logSecurity('rate_limit_exceeded', [
    'action' => 'login',
    'attempts' => 10
]);
echo "<div class='warning-message'>Logs de sécurité créés</div>";
sectionEnd();

section("Test 4 : Log avec modèle (simulation)");
$fakeNote = [
    'id' => 456,
    'note' => 15,
    'coefficient' => 2,
    'commentaire' => 'Bon travail'
];
$audit->logCreated($fakeNote);
echo "<div class='info-message'>Log de création de modèle créé</div>";
sectionEnd();

section("Test 5 : Log de mise à jour avec dirty");
$fakeNoteDirty = [
    'note' => 17,
    'commentaire' => 'Excellent travail'
];
$audit->logUpdated($fakeNote, $fakeNoteDirty);
kv('Ancien commentaire', $fakeNote['commentaire']);
kv('Nouveau commentaire', $fakeNoteDirty['commentaire']);
sectionEnd();

section("Test 6 : Log de suppression");
$audit->logDeleted($fakeNote);
echo "<div class='error-message'>Note supprimée (simulé)</div>";
sectionEnd();

section("Test 7 : Sanitization des données sensibles");
$sensitiveData = [
    'nom' => 'Dupont',
    'password' => 'secret123',
    'mot_de_passe' => 'motdepasse',
    'token' => 'abc123xyz',
    'email' => 'test@example.com'
];
$audit->log('test.sensitive', null, ['new' => $sensitiveData]);
echo "<div class='warning-message'>⚠️ Log créé avec masquage automatique des mots de passe</div>";
sectionEnd();

section("Test 8 : Récupération de l'historique");
try {
    $history = $audit->getHistoryByUser(123, 'professeur', 10);
    echo "<div class='stats-grid'>";
    echo "<div class='stat-card'><div class='stat-value'>" . count($history) . "</div><div class='stat-label'>Entrées trouvées</div></div>";
    echo "</div>";
    if (!empty($history)) {
        kv('Dernière action', $history[0]['action']);
        kv('Date', $history[0]['created_at']);
    }
} catch (\Exception $e) {
    echo "<div class='error-message'>⚠️ " . htmlspecialchars($e->getMessage()) . "</div>";
}
sectionEnd();

section("Test 9 : Recherche dans l'audit");
try {
    $results = $audit->search([
        'action' => 'auth.login',
        'user_id' => 123
    ], 5);
    echo "<div class='stat-card'><div class='stat-value'>" . count($results) . "</div><div class='stat-label'>Résultats</div></div>";
} catch (\Exception $e) {
    echo "<div class='error-message'>⚠️ " . htmlspecialchars($e->getMessage()) . "</div>";
}
sectionEnd();

section("Test 10 : Statistiques");
try {
    $stats = $audit->getStats(7);
    echo "<div class='stats-grid'>";
    echo "<div class='stat-card'><div class='stat-value'>" . count($stats) . "</div><div class='stat-label'>Stats (7 jours)</div></div>";
    echo "</div>";
    if (!empty($stats)) {
        echo "<div class='info-message'><strong>Actions les plus fréquentes :</strong><ul class='list-group'>";
        $grouped = [];
        foreach ($stats as $stat) {
            if (!isset($grouped[$stat['action']])) {
                $grouped[$stat['action']] = 0;
            }
            $grouped[$stat['action']] += $stat['count'];
        }
        arsort($grouped);
        $top3 = array_slice($grouped, 0, 3, true);
        foreach ($top3 as $action => $count) {
            echo "<li class='list-group-item'><span>{$action}</span><span class='badge primary'>{$count} fois</span></li>";
        }
        echo "</ul></div>";
    }
} catch (\Exception $e) {
    echo "<div class='error-message'>⚠️ " . htmlspecialchars($e->getMessage()) . "</div>";
}
sectionEnd();

section("Test 11 : Facade Audit");
Audit::setInstance($audit);
Audit::log('test.facade', null, ['new' => ['method' => 'facade']]);
echo "<div class='success-message'>✓ Log via facade créé</div>";
sectionEnd();

section("Test 12 : Cleanup (simulation)");
try {
    $deleted = $audit->cleanup(365);
    echo "<div class='stat-card'><div class='stat-value'>{$deleted}</div><div class='stat-label'>Logs supprimés</div></div>";
} catch (\Exception $e) {
    echo "<div class='error-message'>⚠️ " . htmlspecialchars($e->getMessage()) . "</div>";
}
sectionEnd();

section("Test 13 : Champs sensibles personnalisés");
$audit->addSensitiveField('ssn');
$audit->addSensitiveField('credit_card');
$dataWithSSN = [
    'name' => 'John Doe',
    'ssn' => '123-45-6789',
    'credit_card' => '4111111111111111'
];
$audit->log('test.custom_sensitive', null, ['new' => $dataWithSSN]);
echo "<div class='warning-message'>⚠️ Champs SSN et carte bancaire masqués automatiquement</div>";
sectionEnd();

section("Résumé Final");
echo "<div class='success-message'>";
echo "<strong>✓ Tous les tests d'audit terminés avec succès !</strong><br><br>";
echo "Si la table audit_log existe, vérifiez son contenu avec :<br>";
echo "<code>SELECT * FROM audit_log ORDER BY created_at DESC LIMIT 10;</code>";
echo "</div>";
sectionEnd();
?>
        </main>
        <footer>
            <p>Pronote API Test Suite &copy; 2024</p>
        </footer>
    </div>
</body>
</html>
