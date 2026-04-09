<?php
/**
 * Layout partage pour les pages d'erreur.
 * Variables attendues : $errorCode, $errorTitle, $errorMessage, $errorIcon
 */
$errorCode    = $errorCode ?? 500;
$errorTitle   = $errorTitle ?? 'Erreur';
$errorMessage = $errorMessage ?? 'Une erreur inattendue est survenue.';
$errorIcon    = $errorIcon ?? '&#9888;';

http_response_code($errorCode);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $errorCode ?> - <?= htmlspecialchars($errorTitle) ?> | Fronote</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f8fafc;
            color: #334155;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .error-card {
            text-align: center;
            max-width: 460px;
            padding: 48px 32px;
        }
        .error-code {
            font-size: 72px;
            font-weight: 800;
            color: #e2e8f0;
            line-height: 1;
            margin-bottom: 8px;
            letter-spacing: -2px;
        }
        .error-icon {
            font-size: 40px;
            margin-bottom: 24px;
        }
        h1 {
            font-size: 22px;
            font-weight: 600;
            margin-bottom: 12px;
            color: #1e293b;
        }
        .error-message {
            font-size: 15px;
            line-height: 1.6;
            color: #64748b;
            margin-bottom: 32px;
        }
        .error-actions {
            display: flex;
            gap: 12px;
            justify-content: center;
            flex-wrap: wrap;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 24px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.15s ease;
            border: none;
            cursor: pointer;
        }
        .btn-primary {
            background: #3b82f6;
            color: #fff;
        }
        .btn-primary:hover { background: #2563eb; }
        .btn-ghost {
            background: #f1f5f9;
            color: #475569;
        }
        .btn-ghost:hover { background: #e2e8f0; }
        .brand {
            margin-top: 48px;
            font-size: 13px;
            color: #cbd5e1;
        }
        <?php if (!empty($debugTrace)): ?>
        .debug-trace {
            margin-top: 32px;
            text-align: left;
            background: #1e293b;
            color: #e2e8f0;
            padding: 16px;
            border-radius: 8px;
            font-family: monospace;
            font-size: 12px;
            overflow-x: auto;
            max-height: 300px;
            overflow-y: auto;
        }
        <?php endif; ?>
    </style>
</head>
<body>
    <div class="error-card">
        <div class="error-code"><?= $errorCode ?></div>
        <div class="error-icon"><?= $errorIcon ?></div>
        <h1><?= htmlspecialchars($errorTitle) ?></h1>
        <p class="error-message"><?= htmlspecialchars($errorMessage) ?></p>
        <div class="error-actions">
            <a href="javascript:history.back()" class="btn btn-ghost">Retour</a>
            <a href="/" class="btn btn-primary">Accueil</a>
        </div>
        <?php if (!empty($debugTrace)): ?>
        <pre class="debug-trace"><?= htmlspecialchars($debugTrace) ?></pre>
        <?php endif; ?>
        <div class="brand">FRONOTE</div>
    </div>
</body>
</html>
