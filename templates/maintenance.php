<?php
/**
 * Page de maintenance — affichee quand le mode maintenance est actif.
 * Fonctionne sans bootstrap (independant de la BDD).
 */
$_mFile = dirname(__DIR__) . '/storage/maintenance.json';
$_mData = file_exists($_mFile) ? json_decode(file_get_contents($_mFile), true) : [];
$_mMessage = $_mData['message'] ?? 'Maintenance en cours. Merci de votre patience.';
$_mEta = null;
if (isset($_mData['started_at'], $_mData['eta_minutes'])) {
    $remaining = (strtotime($_mData['started_at']) + $_mData['eta_minutes'] * 60) - time();
    if ($remaining > 0) {
        $_mEta = $remaining < 60 ? $remaining . 's' : ceil($remaining / 60) . ' min';
    }
}
http_response_code(503);
header('Retry-After: 300');
header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance - Fronote</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #0f172a;
            color: #e2e8f0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .maintenance-card {
            text-align: center;
            max-width: 480px;
            padding: 48px 32px;
        }
        .maintenance-icon {
            width: 80px;
            height: 80px;
            background: #1e293b;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 32px;
            font-size: 36px;
        }
        h1 {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 16px;
            color: #f1f5f9;
        }
        .message {
            font-size: 16px;
            line-height: 1.6;
            color: #94a3b8;
            margin-bottom: 24px;
        }
        .eta {
            display: inline-block;
            background: #1e293b;
            padding: 8px 20px;
            border-radius: 8px;
            font-size: 14px;
            color: #3b82f6;
        }
        .brand {
            margin-top: 48px;
            font-size: 13px;
            color: #475569;
        }
    </style>
</head>
<body>
    <div class="maintenance-card">
        <div class="maintenance-icon">&#128736;</div>
        <h1>Maintenance en cours</h1>
        <p class="message"><?= htmlspecialchars($_mMessage) ?></p>
        <?php if ($_mEta): ?>
        <div class="eta">Retour estime dans ~<?= htmlspecialchars($_mEta) ?></div>
        <?php endif; ?>
        <div class="brand">FRONOTE</div>
    </div>
</body>
</html>
