<?php
/**
 * Toolbar developpeur — visible uniquement en env non-production.
 * Inclus automatiquement par shared_footer.php.
 */
if (!function_exists('app')) return;

$_dtEnv = app('environment');
if (!$_dtEnv || $_dtEnv->isProduction()) return;

$_dtStart = $_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true);
$_dtDuration = round((microtime(true) - $_dtStart) * 1000, 1);
$_dtMemory = round(memory_get_peak_usage(true) / 1024 / 1024, 2);
$_dtRequestId = $_SERVER['X_REQUEST_ID'] ?? '-';
$_dtLocale = '-';
try { $_dtLocale = app('translator')->getLocale(); } catch (\Throwable $e) {}
$_dtDbQueries = '-';
try {
    $db = app('db');
    if (method_exists($db, 'getQueryCount')) {
        $_dtDbQueries = $db->getQueryCount();
    }
} catch (\Throwable $e) {}
$_dtEnvName = strtoupper($_dtEnv->get());
$_dtWsStatus = getenv('WEBSOCKET_ENABLED') ? 'ON' : 'OFF';
$_dtPhp = PHP_VERSION;
$_dtUser = $_SESSION['user_id'] ?? '-';
$_dtFlags = '-';
try {
    $ff = app('features');
    if ($ff) $_dtFlags = count($ff->getEnabled());
} catch (\Throwable $e) {}
?>
<div id="fronote-dev-toolbar" style="position:fixed;bottom:0;left:0;right:0;z-index:99999;background:#1e293b;color:#94a3b8;font-family:monospace;font-size:12px;padding:6px 16px;display:flex;gap:20px;align-items:center;border-top:2px solid #3b82f6;">
    <span style="color:#3b82f6;font-weight:bold;">DEV</span>
    <span title="Environment">ENV: <b style="color:#fbbf24;"><?= $_dtEnvName ?></b></span>
    <span title="PHP Version">PHP: <b><?= $_dtPhp ?></b></span>
    <span title="Render time">Time: <b><?= $_dtDuration ?>ms</b></span>
    <span title="Peak memory">Mem: <b><?= $_dtMemory ?>MB</b></span>
    <span title="SQL Queries">SQL: <b><?= $_dtDbQueries ?></b></span>
    <span title="Locale">Lang: <b><?= $_dtLocale ?></b></span>
    <span title="Active feature flags">Flags: <b><?= $_dtFlags ?></b></span>
    <span title="WebSocket">WS: <b><?= $_dtWsStatus ?></b></span>
    <span title="Request ID">Req: <b style="color:#64748b;"><?= substr($_dtRequestId, 0, 8) ?></b></span>
    <span title="User ID">User: <b><?= $_dtUser ?></b></span>
    <button onclick="this.parentElement.style.display='none'" style="margin-left:auto;background:none;border:none;color:#64748b;cursor:pointer;font-size:14px;">&times;</button>
</div>
