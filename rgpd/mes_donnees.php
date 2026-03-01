<?php
/**
 * M23 – Mes données / Demandes RGPD (utilisateur)
 */
$pageTitle = 'Mes données';
$activePage = 'mes_donnees';
require_once __DIR__ . '/includes/header.php';

$userId = getUserId();
$userType = getUserRole();
$typesDemande = AuditRgpdService::typesDemande();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCSRFToken()) {
    $type = $_POST['type_demande'] ?? '';
    $desc = trim($_POST['description'] ?? '');
    if (array_key_exists($type, $typesDemande) && $desc) {
        $rgpdService->creerDemande($userId, $userType, $type, $desc);
        $_SESSION['success_message'] = 'Votre demande a été enregistrée et sera traitée sous 30 jours.';
    } else {
        $_SESSION['error_message'] = 'Veuillez remplir tous les champs.';
    }
    header('Location: mes_donnees.php');
    exit;
}

$mesdemandes = $rgpdService->getMesDemandesUser($userId, $userType);
?>

<div class="content-wrapper">
    <div class="content-header">
        <h1><i class="fas fa-user-shield"></i> Mes données personnelles</h1>
    </div>

    <?php if (!empty($_SESSION['success_message'])): ?>
        <div class="alert alert-success"><?= $_SESSION['success_message'] ?></div><?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>
    <?php if (!empty($_SESSION['error_message'])): ?>
        <div class="alert alert-danger"><?= $_SESSION['error_message'] ?></div><?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>

    <div class="rgpd-info-banner">
        <i class="fas fa-shield-alt"></i>
        <p>Conformément au Règlement Général sur la Protection des Données (RGPD), vous disposez de droits sur vos données personnelles. Vous pouvez soumettre une demande ci-dessous.</p>
    </div>

    <div class="card">
        <div class="card-header"><h2>Nouvelle demande</h2></div>
        <div class="card-body">
            <form method="post">
                <?= csrfField() ?>
                <div class="form-group">
                    <label>Type de demande</label>
                    <select name="type_demande" class="form-control" required>
                        <option value="">— Sélectionner —</option>
                        <?php foreach ($typesDemande as $k => $v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Description de votre demande</label>
                    <textarea name="description" class="form-control" rows="3" required placeholder="Décrivez votre demande…"></textarea>
                </div>
                <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Soumettre</button>
            </form>
        </div>
    </div>

    <?php if (!empty($mesdemandes)): ?>
    <div class="card">
        <div class="card-header"><h2>Mes demandes (<?= count($mesdemandes) ?>)</h2></div>
        <div class="card-body">
            <?php foreach ($mesdemandes as $d): ?>
            <div class="demande-item">
                <div class="demande-header">
                    <span class="demande-type"><?= $typesDemande[$d['type_demande']] ?? $d['type_demande'] ?></span>
                    <?= AuditRgpdService::statutBadge($d['statut']) ?>
                    <span class="text-muted"><?= formatDate($d['date_demande']) ?></span>
                </div>
                <p><?= htmlspecialchars($d['description']) ?></p>
                <?php if ($d['reponse']): ?>
                <div class="demande-reponse"><strong>Réponse :</strong> <?= nl2br(htmlspecialchars($d['reponse'])) ?></div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
