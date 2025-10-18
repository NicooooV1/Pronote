<?php
session_start();
require_once __DIR__ . '/API/bootstrap.php';
require_once __DIR__ . '/API/Services/AuditService.php';

use Pronote\Services\AuditService;
use Pronote\Services\Audit;

// helpers
function section($t){ echo "=== {$t} ===\n"; }
function kv($k,$v){
    if (is_bool($v)) $v = $v ? 'OUI' : 'NON';
    if ($v === null) $v = 'NULL';
    echo "{$k}: {$v}\n";
}
function jprint($k,$v){ echo "{$k}: " . json_encode($v, JSON_PRETTY_PRINT) . "\n"; }

// Simuler un utilisateur connecté
$_SESSION['user'] = [
    'id' => 123,
    'profil' => 'professeur',
    'nom' => 'Dupont',
    'prenom' => 'Jean'
];

$audit = new AuditService();

section("Test 1 : Log d'une action simple");
$result = $audit->log('test.action', null, [
    'new' => ['data' => 'test']
]);
echo "Log créé : " . ($result ? "OUI" : "NON") . "\n\n";

section("Test 2 : Log d'authentification");
$audit->logAuth('login', 'jean.dupont', true, ['method' => 'password']);
$audit->logAuth('login', 'hacker', false, ['method' => 'password', 'reason' => 'invalid_credentials']);
echo "Logs d'auth créés\n\n";

section("Test 3 : Log de sécurité");
$audit->logSecurity('csrf_invalid', [
    'token' => 'abc123',
    'expected' => 'def456'
]);
$audit->logSecurity('rate_limit_exceeded', [
    'action' => 'login',
    'attempts' => 10
]);
echo "Logs de sécurité créés\n\n";

section("Test 4 : Log avec modèle (simulation)");
$fakeNote = [
    'id' => 456,
    'note' => 15,
    'coefficient' => 2,
    'commentaire' => 'Bon travail'
];
$audit->logCreated($fakeNote);
echo "Log de création de modèle créé\n\n";

section("Test 5 : Log de mise à jour avec dirty");
$fakeNoteDirty = [
    'note' => 17,
    'commentaire' => 'Excellent travail'
];
$audit->logUpdated($fakeNote, $fakeNoteDirty);
echo "Log de mise à jour créé\n\n";

section("Test 6 : Log de suppression");
$audit->logDeleted($fakeNote);
echo "Log de suppression créé\n\n";

section("Test 7 : Sanitization des données sensibles");
$sensitiveData = [
    'nom' => 'Dupont',
    'password' => 'secret123',
    'mot_de_passe' => 'motdepasse',
    'token' => 'abc123xyz',
    'email' => 'test@example.com'
];
$audit->log('test.sensitive', null, ['new' => $sensitiveData]);
echo "Log avec données sensibles créé (mots de passe masqués)\n\n";

section("Test 8 : Récupération de l'historique");
try {
    $history = $audit->getHistoryByUser(123, 'professeur', 10);
    echo "Historique récupéré : " . count($history) . " entrées\n";
    if (!empty($history)) {
        kv('Dernière action', $history[0]['action']);
        kv('Date', $history[0]['created_at']);
    }
} catch (\Exception $e) {
    echo "Erreur (normal si table n'existe pas) : " . $e->getMessage() . "\n";
}
echo "\n";

section("Test 9 : Recherche dans l'audit");
try {
    $results = $audit->search([
        'action' => 'auth.login',
        'user_id' => 123
    ], 5);
    echo "Résultats de recherche : " . count($results) . " entrées\n";
} catch (\Exception $e) {
    echo "Erreur (normal si table n'existe pas) : " . $e->getMessage() . "\n";
}
echo "\n";

section("Test 10 : Statistiques");
try {
    $stats = $audit->getStats(7);
    echo "Statistiques sur 7 jours : " . count($stats) . " entrées\n";
    if (!empty($stats)) {
        echo "Actions les plus fréquentes :\n";
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
            echo "  - {$action}: {$count} fois\n";
        }
    }
} catch (\Exception $e) {
    echo "Erreur (normal si table n'existe pas) : " . $e->getMessage() . "\n";
}
echo "\n";

section("Test 11 : Facade Audit");
Audit::setInstance($audit);
Audit::log('test.facade', null, ['new' => ['method' => 'facade']]);
echo "Log via facade créé\n\n";

section("Test 12 : Cleanup (simulation)");
try {
    $deleted = $audit->cleanup(365);
    echo "Logs supprimés (> 365 jours) : {$deleted}\n";
} catch (\Exception $e) {
    echo "Erreur (normal si table n'existe pas) : " . $e->getMessage() . "\n";
}
echo "\n";

section("Test 13 : Champs sensibles personnalisés");
$audit->addSensitiveField('ssn');
$audit->addSensitiveField('credit_card');
$dataWithSSN = [
    'name' => 'John Doe',
    'ssn' => '123-45-6789',
    'credit_card' => '4111111111111111'
];
$audit->log('test.custom_sensitive', null, ['new' => $dataWithSSN]);
echo "Log avec champs sensibles personnalisés créé\n\n";

section("Résumé");
echo "Tous les tests d'audit terminés.\n";
echo "Si la table audit_log existe, vérifiez son contenu avec :\n";
echo "SELECT * FROM audit_log ORDER BY created_at DESC LIMIT 10;\n";
