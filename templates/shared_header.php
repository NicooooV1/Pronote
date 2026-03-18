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

// ─── Theme loading ───────────────────────────────────────────────────────────
// Load user's theme preference from DB or localStorage fallback
$_hdr_theme = 'light';
try {
    if (!empty($_SESSION['user_id']) && !empty($_SESSION['role'])) {
        $_hdr_pdo = getPDO();
        $_hdr_stmt = $_hdr_pdo->prepare("SELECT theme FROM user_settings WHERE user_id = ? AND user_type = ?");
        $_hdr_stmt->execute([$_SESSION['user_id'], $_SESSION['role']]);
        $_hdr_theme = $_hdr_stmt->fetchColumn() ?: 'light';
    }
} catch (Exception $e) { /* fallback to light */ }

// For 'auto' theme, we'll let JS handle the actual dark/light switch
$_hdr_effective_theme = $_hdr_theme;
if ($_hdr_theme === 'auto') {
    $_hdr_effective_theme = 'light'; // JS will override based on prefers-color-scheme
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
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-{$_hdr_nonce}' cdnjs.cloudflare.com cdn.socket.io code.jquery.com; style-src 'self' 'unsafe-inline' cdnjs.cloudflare.com; font-src cdnjs.cloudflare.com data:; img-src 'self' data: blob:; connect-src 'self' ws: wss:; frame-ancestors 'none';");
    header("X-Frame-Options: DENY");
    header("X-Content-Type-Options: nosniff");
    header("Referrer-Policy: strict-origin-when-cross-origin");
    header("Permissions-Policy: camera=(), microphone=(), geolocation=()");
}
?>
<!DOCTYPE html>
<html lang="fr" data-theme="<?= htmlspecialchars($_hdr_effective_theme) ?>" data-theme-pref="<?= htmlspecialchars($_hdr_theme) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars($_hdr_csrf_token) ?>">
    <title><?= htmlspecialchars($pageTitle) ?> - FRONOTE</title>
    <!-- CSS unifié pour toute l'application -->
    <link rel="stylesheet" href="<?= $rootPrefix ?>assets/css/pronote-unified.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <?php foreach ($extraCss as $css): ?>
    <link rel="stylesheet" href="<?= htmlspecialchars($css) ?>">
    <?php endforeach; ?>
    <?= $extraHeadHtml ?>
    <!-- WebSocket global -->
    <script nonce="<?= $_hdr_nonce ?>">window.FRONOTE_WS = <?= $_hdr_ws_config ?>;</script>
    <script src="https://cdn.socket.io/4.7.5/socket.io.min.js" crossorigin="anonymous"></script>
    <script src="<?= $rootPrefix ?>assets/js/ws-global.js" defer></script>
    <script nonce="<?= $_hdr_nonce ?>">
    // Instant theme application to prevent flash of wrong theme
    (function() {
        var pref = document.documentElement.getAttribute('data-theme-pref') || 'light';
        var stored = null;
        try { stored = localStorage.getItem('fronote_theme'); } catch(e) {}
        if (stored && !pref) pref = stored;
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

<div class="app-container">
