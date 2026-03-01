<?php
/**
 * M23 – Consentements RGPD
 */
$pageTitle = 'Mes consentements';
$activePage = 'consentements';
require_once __DIR__ . '/includes/header.php';

$userId = getUserId();
$userType = getUserRole();
$types = AuditRgpdService::typesConsentement();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCSRFToken()) {
    $rgpdService->sauvegarderConsentements($userId, $userType, $_POST['consent'] ?? [], $_SERVER['REMOTE_ADDR'] ?? null);
    $_SESSION['success_message'] = 'Consentements mis à jour.';
    header('Location: consentements.php');
    exit;
}

$consentements = $rgpdService->getConsentements($userId, $userType);
?>

<div class="content-wrapper">
    <div class="content-header">
        <h1><i class="fas fa-check-circle"></i> Mes consentements</h1>
    </div>

    <?php if (!empty($_SESSION['success_message'])): ?>
        <div class="alert alert-success"><?= $_SESSION['success_message'] ?></div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>

    <div class="rgpd-info-banner">
        <i class="fas fa-info-circle"></i>
        <p>Conformément au RGPD, vous pouvez gérer vos consentements ci-dessous. Vous pouvez les modifier ou les retirer à tout moment.</p>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="post">
                <?= csrfField() ?>
                <div class="consent-list">
                    <?php foreach ($types as $key => $label): ?>
                    <div class="consent-item">
                        <label class="consent-toggle">
                            <input type="checkbox" name="consent[<?= $key ?>]" value="1" <?= !empty($consentements[$key]['consenti']) ? 'checked' : '' ?>>
                            <span class="toggle-slider"></span>
                        </label>
                        <div class="consent-info">
                            <strong><?= htmlspecialchars($label) ?></strong>
                            <?php if (!empty($consentements[$key]['date_consentement'])): ?>
                            <small class="text-muted">Consenti le <?= formatDate($consentements[$key]['date_consentement']) ?></small>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Enregistrer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
