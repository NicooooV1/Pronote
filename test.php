<?php
require_once __DIR__ . '/API/bootstrap.php';

// helpers
function section($t){ echo "=== {$t} ===\n"; }
function kv($k,$v){
    if (is_bool($v)) $v = $v ? 'OUI' : 'NON';
    if ($v === null) $v = 'NULL';
    echo "{$k}: {$v}\n";
}

try {
    $app = app();
    section('Test App');
    kv('App loaded', is_object($app));
    kv('App URL', config('app.url', 'http://localhost'));
    kv('DB Host', config('database.host', 'localhost'));
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
