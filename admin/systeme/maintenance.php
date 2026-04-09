<?php
/**
 * Administration — Mode maintenance
 */
require_once __DIR__ . '/../../API/core.php';
require_once __DIR__ . '/../includes/admin_functions.php';

requireAuth();
requireRole('administrateur');

$maintenance = new \API\Services\MaintenanceService(BASE_PATH);
$message = '';
$messageType = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = app('csrf');
    if (!$csrf->validate($_POST['_token'] ?? '')) {
        $message = 'Token CSRF invalide.';
        $messageType = 'error';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'activate') {
            $msg = trim($_POST['message'] ?? '');
            $ips = array_filter(array_map('trim', explode("\n", $_POST['allowed_ips'] ?? '')));
            $eta = !empty($_POST['eta_minutes']) ? (int)$_POST['eta_minutes'] : null;
            $maintenance->activate($msg, $ips, $eta);
            $message = 'Mode maintenance active.';
            $messageType = 'success';
        } elseif ($action === 'deactivate') {
            $maintenance->deactivate();
            $message = 'Mode maintenance desactive.';
            $messageType = 'success';
        }
    }
}

$status = $maintenance->getStatus();
$isActive = $maintenance->isActive();
$csrfToken = app('csrf')->generate();

$pageTitle = 'Mode Maintenance';
$activePage = 'systeme';

require_once __DIR__ . '/../../templates/shared_header.php';
?>

<div class="topbar">
    <div class="topbar-left">
        <h1 class="page-title"><i class="fas fa-tools"></i> Mode Maintenance</h1>
    </div>
</div>

<div class="content-body" style="padding: 24px;">

    <?php if ($message): ?>
    <div class="alert alert-<?= $messageType === 'success' ? 'success' : 'danger' ?>" style="margin-bottom: 20px;">
        <?= e($message) ?>
    </div>
    <?php endif; ?>

    <div class="card" style="max-width: 640px;">
        <div class="card-body" style="padding: 24px;">

            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 24px;">
                <span style="width: 12px; height: 12px; border-radius: 50%; background: <?= $isActive ? '#ef4444' : '#22c55e' ?>;"></span>
                <span style="font-size: 18px; font-weight: 600;">
                    <?= $isActive ? 'Maintenance ACTIVE' : 'Site en ligne' ?>
                </span>
            </div>

            <?php if ($isActive): ?>
            <form method="post">
                <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="action" value="deactivate">
                <p style="color: var(--text-muted); margin-bottom: 16px;">
                    Le site est actuellement en mode maintenance.
                    Seules les IPs autorisees peuvent y acceder.
                </p>
                <?php if (!empty($status['started_at'])): ?>
                <p style="color: var(--text-muted); font-size: 13px; margin-bottom: 16px;">
                    Active depuis : <?= e($status['started_at']) ?>
                </p>
                <?php endif; ?>
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-check-circle"></i> Desactiver la maintenance
                </button>
            </form>

            <?php else: ?>
            <form method="post">
                <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="action" value="activate">

                <div style="margin-bottom: 16px;">
                    <label style="display: block; font-weight: 500; margin-bottom: 6px;">Message affiche</label>
                    <textarea name="message" rows="3" class="form-control"
                        placeholder="Maintenance en cours. Merci de votre patience."
                    ><?= e($status['message'] ?? '') ?></textarea>
                </div>

                <div style="margin-bottom: 16px;">
                    <label style="display: block; font-weight: 500; margin-bottom: 6px;">IPs autorisees (une par ligne)</label>
                    <textarea name="allowed_ips" rows="3" class="form-control"
                        placeholder="127.0.0.1&#10;192.168.1.0/24"
                    ><?= e(implode("\n", $status['allowed_ips'] ?? [])) ?></textarea>
                    <small style="color: var(--text-muted);">Votre IP actuelle : <?= e($_SERVER['REMOTE_ADDR'] ?? '?') ?></small>
                </div>

                <div style="margin-bottom: 24px;">
                    <label style="display: block; font-weight: 500; margin-bottom: 6px;">Duree estimee (minutes)</label>
                    <input type="number" name="eta_minutes" class="form-control"
                        placeholder="30" min="1" max="1440"
                        value="<?= (int)($status['eta_minutes'] ?? '') ?>">
                </div>

                <button type="submit" class="btn btn-danger">
                    <i class="fas fa-power-off"></i> Activer la maintenance
                </button>
            </form>
            <?php endif; ?>

        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../templates/shared_footer.php'; ?>
