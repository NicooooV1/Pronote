<?php
/**
 * Template partagé : Header (ouverture HTML + <head> + top bar)
 * 
 * Variables attendues :
 *   $pageTitle      — string  : titre de la page (affiché dans <title> et le <h1>)
 *   $user_initials  — string  : initiales de l'utilisateur
 * 
 * Variables optionnelles :
 *   $pageSubtitle       — string : sous-titre sous le h1
 *   $extraCss           — array  : fichiers CSS supplémentaires à charger (chemins relatifs)
 *   $extraHeadHtml      — string : HTML supplémentaire dans le <head>
 *   $headerExtraActions — string : HTML d'actions supplémentaires dans le header-actions (boutons spécifiques au module)
 *   $user_fullname      — string : nom complet pour le tooltip de l'avatar
 */

// Gestion des variables globales pour tous les modules
$pageTitle = $pageTitle ?? 'FRONOTE';
$user_initials = $user_initials ?? '';
$pageSubtitle = $pageSubtitle ?? '';
$extraCss = $extraCss ?? [];
$extraHeadHtml = $extraHeadHtml ?? '';
$headerExtraActions = $headerExtraActions ?? '';
$user_fullname = $user_fullname ?? '';
$activePage = $activePage ?? '';
$isAdmin = $isAdmin ?? false;
$rootPrefix = $rootPrefix ?? '../';

// NOTE : $activePage doit être défini dans chaque page/module pour la coloration de la navigation
// Exemples : 'accueil', 'notes', 'agenda', 'cahierdetextes', 'messagerie', 'absences', 'admin'

// ─── Theme loading (cached) ──────────────────────────────────────────────────
// Priorité : ClientCache (session+cookie) → DB → fallback 'classic'
// Élimine la requête SQL sur chaque page après le premier chargement.
$_hdr_theme = 'classic';
$_hdr_dark_mode = 'light';
try {
    if (!empty($_SESSION['user_id']) && !empty($_SESSION['user_type'])) {
        /** @var \API\Core\ClientCache $cc */
        $cc = class_exists('\\API\\Core\\ClientCache') ? new \API\Core\ClientCache() : null;

        $_hdr_raw_theme = null;
        if ($cc) {
            $_hdr_raw_theme = $cc->get('user_theme');
        }

        // Fallback DB si pas en cache
        if ($_hdr_raw_theme === null) {
            $_hdr_pdo = getPDO();
            $_hdr_stmt = $_hdr_pdo->prepare("SELECT theme FROM user_settings WHERE user_id = ? AND user_type = ? LIMIT 1");
            $_hdr_stmt->execute([$_SESSION['user_id'], $_SESSION['user_type']]);
            $_hdr_raw_theme = $_hdr_stmt->fetchColumn() ?: 'classic';
            // Mettre en cache (TTL 1h — invalidé à la modification dans parametres)
            if ($cc) {
                $cc->set('user_theme', $_hdr_raw_theme, 3600);
            }
        }

        // Support both old (light/dark/auto) and new (classic/glass) theme values
        if (in_array($_hdr_raw_theme, ['classic', 'glass'], true)) {
            $_hdr_theme = $_hdr_raw_theme;
        } elseif ($_hdr_raw_theme === 'light' || $_hdr_raw_theme === 'dark' || $_hdr_raw_theme === 'auto') {
            $_hdr_theme = 'classic';
            $_hdr_dark_mode = $_hdr_raw_theme;
        }
    }
} catch (Exception $e) { /* fallback to classic */ }

// For dark mode, let JS handle 'auto' via prefers-color-scheme
$_hdr_effective_dark = $_hdr_dark_mode;
if ($_hdr_dark_mode === 'auto') {
    $_hdr_effective_dark = 'light';
}

// ─── CSRF token ──────────────────────────────────────────────────────────────
// Utilise la facade CSRF (token bucket avec rotation) pour éviter deux systèmes parallèles.
// Stocke également dans $_SESSION['csrf_token'] pour rétrocompatibilité avec les formulaires.
try {
    $_hdr_csrf_token = \API\Core\Facades\CSRF::generate();
} catch (\Throwable $_hdr_csrf_err) {
    // Fallback si le container n'est pas encore initialisé
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    $_hdr_csrf_token = $_SESSION['csrf_token'];
}
$_SESSION['csrf_token'] = $_hdr_csrf_token;

// ─── Nonce CSP ───────────────────────────────────────────────────────────────
$_hdr_nonce = base64_encode(random_bytes(16));

// ─── WebSocket global config ─────────────────────────────────────────────────
// Génère le JWT pour le client WS et injecte window.FRONOTE_WS dans le <head>.
$_hdr_ws_config = 'null';
try {
    $wsEnabled = env('WEBSOCKET_ENABLED', 'true');
    if (!empty($_SESSION['user_id']) && $wsEnabled !== 'false' && $wsEnabled !== false) {
        $wsToken = \API\Core\WebSocket::generateToken(
            (int) $_SESSION['user_id'],
            $_SESSION['user_type'] ?? $_SESSION['role'] ?? ''
        );
        if ($wsToken) {
            $_hdr_ws_config = json_encode([
                'url'      => env('WEBSOCKET_CLIENT_URL', 'http://localhost:3000'),
                'token'    => $wsToken,
                'userId'   => (int) $_SESSION['user_id'],
                'userType' => $_SESSION['user_type'] ?? $_SESSION['role'] ?? '',
                'userRole' => $_SESSION['role'] ?? $_SESSION['user_type'] ?? '',
            ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
        }
    }
} catch (\Throwable $_hdr_ws_err) { /* WS optionnel — ne jamais bloquer le rendu */ }

// ─── Security headers ────────────────────────────────────────────────────────
if (!headers_sent()) {
    // CSP permissive : 'unsafe-inline' pour scripts ET styles, pas de nonce (évite
    // les écueils d'intégration avec les onclick/styles inline de modules tiers).
    // upgrade-insecure-requests désactivé sur HTTP-only (sinon ERR_CONNECTION_REFUSED).
    $_hdr_isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $_hdr_csp = "default-src 'self'; "
        . "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdnjs.cloudflare.com https://cdn.socket.io https://code.jquery.com http://cdnjs.cloudflare.com http://cdn.socket.io http://code.jquery.com; "
        . "style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com http://cdnjs.cloudflare.com; "
        . "font-src 'self' https://cdnjs.cloudflare.com http://cdnjs.cloudflare.com data:; "
        . "img-src 'self' data: blob: https: http:; "
        . "connect-src 'self' ws: wss: https: http:; "
        . "frame-ancestors 'none'; base-uri 'self'; form-action 'self';"
        . ($_hdr_isHttps ? ' upgrade-insecure-requests;' : '');
    header("Content-Security-Policy: {$_hdr_csp}");
    header("X-Frame-Options: DENY");
    header("X-Content-Type-Options: nosniff");
    header("Referrer-Policy: strict-origin-when-cross-origin");
    header("Permissions-Policy: camera=(), microphone=(), geolocation=()");
    if ($_hdr_isHttps) {
        header("Strict-Transport-Security: max-age=31536000; includeSubDomains");
    }
}
?>
<!DOCTYPE html>
<?php
$_hdr_locale = 'fr';
$_hdr_dir = 'ltr';
try {
    $translator = app('translator');
    $_hdr_locale = $translator->getLocale();
    $_hdr_dir = $translator->isRtl() ? 'rtl' : 'ltr';
} catch (\Throwable $_e) {}
?>
<html lang="<?= htmlspecialchars($_hdr_locale) ?>" dir="<?= $_hdr_dir ?>" data-theme="<?= htmlspecialchars($_hdr_effective_dark) ?>" data-theme-pref="<?= htmlspecialchars($_hdr_dark_mode) ?>" data-css-theme="<?= htmlspecialchars($_hdr_theme) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars($_hdr_csrf_token) ?>">
    <meta name="theme-color" content="#667eea">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <link rel="manifest" href="<?= $rootPrefix ?>manifest.webmanifest">
    <link rel="apple-touch-icon" href="<?= $rootPrefix ?>assets/icons/icon-192.png">
    <title><?= htmlspecialchars($pageTitle) ?> - FRONOTE</title>
    <?php
    // Cache-busting : ajoute ?v=<mtime> pour forcer le rechargement apres edition
    $_assetRoot = realpath(__DIR__ . '/..');
    $_assetVersion = function(string $relPath) use ($_assetRoot, $rootPrefix): string {
        $abs = $_assetRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relPath);
        $v = @filemtime($abs) ?: time();
        return $rootPrefix . $relPath . '?v=' . $v;
    };
    ?>
    <!-- CSS : base + tokens + classic (always) + glass overlay (if selected) -->
    <link rel="stylesheet" href="<?= $_assetVersion('assets/css/cookie-consent.css') ?>">
    <link rel="stylesheet" href="<?= $_assetVersion('assets/css/topbar.css') ?>">
    <link rel="stylesheet" href="<?= $_assetVersion('assets/css/base.css') ?>">
    <link rel="stylesheet" href="<?= $_assetVersion('assets/css/tokens.css') ?>">
    <link rel="stylesheet" href="<?= $_assetVersion('assets/css/components.css') ?>">
    <link rel="stylesheet" href="<?= $_assetVersion('assets/css/theme-classic.css') ?>">
    <?php if ($_hdr_theme === 'glass' || $_hdr_theme === 'auto-glass'): ?>
    <link rel="stylesheet" href="<?= $_assetVersion('assets/css/theme-glass.css') ?>">
    <?php endif; ?>
    <?php if ($_hdr_dir === 'rtl'): ?>
    <link rel="stylesheet" href="<?= $_assetVersion('assets/css/rtl.css') ?>">
    <?php endif; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" crossorigin="anonymous">
    <?php foreach ($extraCss as $css):
        // Append ?v=<mtime> when the file resolves to a local asset
        $_cssHref = $css;
        $_cssRel = null;
        if (strpos($css, '://') === false) {
            $_cssRel = ltrim(preg_replace('#^(?:\.\./)+#', '', $css), '/');
            $_cssAbs = $_assetRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $_cssRel);
            $_cssMtime = @filemtime($_cssAbs);
            if ($_cssMtime) {
                $_cssHref .= (strpos($css, '?') === false ? '?v=' : '&v=') . $_cssMtime;
            }
        }
    ?>
    <link rel="stylesheet" href="<?= htmlspecialchars($_cssHref) ?>">
    <?php endforeach; ?>
    <?= $extraHeadHtml ?>
    <!-- WebSocket global -->
    <script nonce="<?= $_hdr_nonce ?>">window.FRONOTE_WS = <?= $_hdr_ws_config ?>;</script>
    <script src="https://cdn.socket.io/4.7.5/socket.io.min.js" crossorigin="anonymous"></script>
    <script src="<?= $_assetVersion('assets/js/topbar.js') ?>" defer></script>
    <script src="<?= $_assetVersion('assets/js/components.js') ?>" defer></script>
    <script src="<?= $_assetVersion('assets/js/fronote-ajax.js') ?>" defer></script>
    <script src="<?= $_assetVersion('assets/js/ws-global.js') ?>" defer></script>
    <script src="<?= $_assetVersion('assets/js/push-manager.js') ?>" defer></script>
    <script nonce="<?= $_hdr_nonce ?>">
    window.FRONOTE_BASE_URL = <?= json_encode(rtrim($rootPrefix, '/') . '/') ?>;
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register(<?= json_encode($rootPrefix . 'sw.js') ?>, { scope: <?= json_encode($rootPrefix) ?> })
            .catch(function(e) { console.warn('SW registration failed:', e); });
    }
    </script>
    <script nonce="<?= $_hdr_nonce ?>">
    // Instant dark-mode application to prevent flash of wrong theme
    (function() {
        var pref = document.documentElement.getAttribute('data-theme-pref') || 'light';
        var stored = null;
        try { stored = localStorage.getItem('fronote_dark_mode'); } catch(e) {}
        if (stored && pref === 'light') pref = stored;
        if (pref === 'auto') {
            var dark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            document.documentElement.setAttribute('data-theme', dark ? 'dark' : 'light');
        } else if (pref === 'dark') {
            document.documentElement.setAttribute('data-theme', 'dark');
        }
    })();
    </script>
</head>
<body>

<?php include __DIR__ . '/cookie_consent.php'; ?>

<div class="app-container">
