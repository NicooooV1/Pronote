<?php
/**
 * M14 – Réunions — Détail d'une réunion
 */
$pageTitle = 'Détail réunion';
require_once __DIR__ . '/includes/header.php';

$id = (int)($_GET['id'] ?? 0);
$reunion = $reunionService->getReunion($id);
if (!$reunion) { header('Location: reunions.php'); exit; }

$creneaux = $reunionService->getCreneaux($id);
$types = ReunionService::typesReunion();

// PV save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCSRFToken() && (isAdmin() || isTeacher() || isVieScolaire())) {
    $action = $_POST['action'] ?? '';
    if ($action === 'sauvegarder_pv') {
        $reunionService->sauvegarderPV($id, trim($_POST['pv'] ?? ''));
        $_SESSION['success_message'] = 'Procès-verbal enregistré.';
        header('Location: detail.php?id=' . $id);
        exit;
    }
}
?>

<div class="content-wrapper">
    <div class="content-header">
        <h1><i class="fas fa-calendar-alt"></i> <?= htmlspecialchars($reunion['titre']) ?></h1>
        <a href="reunions.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Retour</a>
    </div>

    <?php if (!empty($_SESSION['success_message'])): ?>
        <div class="alert alert-success"><?= $_SESSION['success_message'] ?></div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>

    <div class="detail-grid">
        <div class="card">
            <div class="card-header">Informations</div>
            <div class="card-body">
                <div class="info-grid">
                    <div class="info-item"><span class="info-label">Type</span><span class="info-value"><?= $types[$reunion['type']] ?? $reunion['type'] ?></span></div>
                    <div class="info-item"><span class="info-label">Statut</span><span class="info-value"><?= ReunionService::statutBadge($reunion['statut']) ?></span></div>
                    <div class="info-item"><span class="info-label">Date début</span><span class="info-value"><?= formatDateTime($reunion['date_debut']) ?></span></div>
                    <div class="info-item"><span class="info-label">Date fin</span><span class="info-value"><?= formatDateTime($reunion['date_fin']) ?></span></div>
                    <?php if ($reunion['lieu']): ?>
                    <div class="info-item"><span class="info-label">Lieu</span><span class="info-value"><?= htmlspecialchars($reunion['lieu']) ?></span></div>
                    <?php endif; ?>
                    <?php if ($reunion['classe_nom']): ?>
                    <div class="info-item"><span class="info-label">Classe</span><span class="info-value"><?= htmlspecialchars($reunion['classe_nom']) ?></span></div>
                    <?php endif; ?>
                </div>
                <?php if ($reunion['description']): ?>
                <div class="reunion-description"><?= nl2br(htmlspecialchars($reunion['description'])) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Créneaux de RDV -->
        <?php if (!empty($creneaux)): ?>
        <div class="card">
            <div class="card-header">Créneaux de rendez-vous (<?= count($creneaux) ?>)</div>
            <div class="card-body">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Professeur</th>
                            <th>Horaire</th>
                            <th>Salle</th>
                            <th>Statut</th>
                            <th>Réservé par</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($creneaux as $c): ?>
                        <tr>
                            <td><?= htmlspecialchars($c['prof_prenom'] . ' ' . $c['prof_nom']) ?></td>
                            <td><?= substr($c['heure_debut'], 0, 5) ?> — <?= substr($c['heure_fin'], 0, 5) ?></td>
                            <td><?= htmlspecialchars($c['salle'] ?? '—') ?></td>
                            <td>
                                <?php if ($c['reservation_id']): ?>
                                    <span class="badge badge-success">Réservé</span>
                                <?php else: ?>
                                    <span class="badge badge-info">Disponible</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($c['reservation_id']): ?>
                                    <?= htmlspecialchars($c['parent_prenom'] . ' ' . $c['parent_nom']) ?>
                                    <br><small>pour <?= htmlspecialchars($c['eleve_prenom'] . ' ' . $c['eleve_nom']) ?></small>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if (isParent() && $reunion['statut'] === 'planifiee'): ?>
                <div style="margin-top: 1rem;">
                    <a href="reserver.php?reunion_id=<?= $id ?>" class="btn btn-success"><i class="fas fa-bookmark"></i> Réserver un créneau</a>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- PV -->
        <?php if (isAdmin() || isTeacher() || isVieScolaire()): ?>
        <div class="card">
            <div class="card-header">Procès-verbal</div>
            <div class="card-body">
                <?php if ($reunion['pv_contenu']): ?>
                    <div class="pv-content"><?= nl2br(htmlspecialchars($reunion['pv_contenu'])) ?></div>
                <?php endif; ?>
                <form method="post" style="margin-top: 1rem;">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="sauvegarder_pv">
                    <textarea name="pv" class="form-control" rows="6" placeholder="Rédiger le procès-verbal..."><?= htmlspecialchars($reunion['pv_contenu'] ?? '') ?></textarea>
                    <button type="submit" class="btn btn-primary" style="margin-top: .5rem;"><i class="fas fa-save"></i> Enregistrer le PV</button>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
