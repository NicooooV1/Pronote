<?php
/**
 * M14 – Réunions — Convocations
 */
$pageTitle = 'Convocations';
require_once __DIR__ . '/includes/header.php';

$userId = getUserId();
$userType = getUserRole();

// Créer convocation (admin/vie_scolaire)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCSRFToken() && (isAdmin() || isVieScolaire())) {
    $data = [
        'reunion_id'       => (int)($_POST['reunion_id'] ?? 0) ?: null,
        'destinataire_id'  => (int)$_POST['destinataire_id'],
        'destinataire_type'=> $_POST['destinataire_type'],
        'objet'            => trim($_POST['objet']),
        'contenu'          => trim($_POST['contenu'] ?? ''),
        'date_convocation' => $_POST['date_convocation'],
        'heure'            => $_POST['heure'] ?: null,
        'lieu'             => trim($_POST['lieu'] ?? ''),
        'type'             => $_POST['type_conv'] ?? 'reunion',
        'emetteur_id'      => $userId,
        'emetteur_type'    => $userType,
    ];
    $reunionService->creerConvocation($data);
    $_SESSION['success_message'] = 'Convocation envoyée.';
    header('Location: convocations.php');
    exit;
}

// Marquer comme lue
if (isset($_GET['lire'])) {
    $reunionService->marquerConvocationLue((int)$_GET['lire']);
    header('Location: convocations.php');
    exit;
}

$convocations = $reunionService->getConvocations($userId, $userType);
?>

<div class="content-wrapper">
    <div class="content-header">
        <h1><i class="fas fa-file-invoice"></i> Convocations</h1>
        <?php if (isAdmin() || isVieScolaire()): ?>
        <button class="btn btn-primary" onclick="document.getElementById('modal-convocation').style.display='flex'"><i class="fas fa-plus"></i> Nouvelle convocation</button>
        <?php endif; ?>
    </div>

    <?php if (!empty($_SESSION['success_message'])): ?>
        <div class="alert alert-success"><?= $_SESSION['success_message'] ?></div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>

    <?php if (empty($convocations)): ?>
        <div class="empty-state"><i class="fas fa-inbox"></i><p>Aucune convocation.</p></div>
    <?php else: ?>
        <div class="convocation-list">
            <?php foreach ($convocations as $conv): ?>
            <div class="convocation-item <?= !$conv['lue'] ? 'convocation-unread' : '' ?>">
                <div class="conv-icon"><i class="fas fa-file-invoice"></i></div>
                <div class="conv-content">
                    <h3><?= htmlspecialchars($conv['objet']) ?></h3>
                    <?php if ($conv['contenu']): ?>
                    <p><?= nl2br(htmlspecialchars($conv['contenu'])) ?></p>
                    <?php endif; ?>
                    <div class="conv-meta">
                        <span><i class="fas fa-calendar"></i> <?= formatDate($conv['date_convocation']) ?></span>
                        <?php if ($conv['heure']): ?><span><i class="fas fa-clock"></i> <?= substr($conv['heure'], 0, 5) ?></span><?php endif; ?>
                        <?php if ($conv['lieu']): ?><span><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($conv['lieu']) ?></span><?php endif; ?>
                    </div>
                </div>
                <div class="conv-actions">
                    <?php if (!$conv['lue']): ?>
                    <a href="?lire=<?= $conv['id'] ?>" class="btn btn-sm btn-outline"><i class="fas fa-check"></i> Marquer lu</a>
                    <?php else: ?>
                    <span class="badge badge-success">Lu</span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Modal nouvelle convocation (admin/vie_scolaire) -->
<?php if (isAdmin() || isVieScolaire()): ?>
<div id="modal-convocation" class="modal-overlay" style="display:none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Nouvelle convocation</h2>
            <button onclick="this.closest('.modal-overlay').style.display='none'" class="modal-close">&times;</button>
        </div>
        <form method="post">
            <?= csrfField() ?>
            <div class="form-group">
                <label>Objet *</label>
                <input type="text" name="objet" class="form-control" required>
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label>ID destinataire *</label>
                    <input type="number" name="destinataire_id" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Type destinataire *</label>
                    <select name="destinataire_type" class="form-control">
                        <option value="parent">Parent</option>
                        <option value="eleve">Élève</option>
                        <option value="professeur">Professeur</option>
                    </select>
                </div>
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label>Date *</label>
                    <input type="date" name="date_convocation" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Heure</label>
                    <input type="time" name="heure" class="form-control">
                </div>
                <div class="form-group">
                    <label>Type</label>
                    <select name="type_conv" class="form-control">
                        <option value="reunion">Réunion</option>
                        <option value="conseil">Conseil</option>
                        <option value="disciplinaire">Disciplinaire</option>
                        <option value="information">Information</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>Lieu</label>
                <input type="text" name="lieu" class="form-control">
            </div>
            <div class="form-group">
                <label>Contenu</label>
                <textarea name="contenu" class="form-control" rows="3"></textarea>
            </div>
            <input type="hidden" name="reunion_id" value="">
            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Envoyer</button>
                <button type="button" class="btn btn-outline" onclick="this.closest('.modal-overlay').style.display='none'">Annuler</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
