<?php
/**
 * M06 – Discipline : Fiche discipline d'un élève
 */

require_once __DIR__ . '/includes/DisciplineService.php';

$pageTitle = 'Fiche discipline';
$currentPage = 'incidents';
require_once __DIR__ . '/includes/header.php';
requireAuth();

if (!isAdmin() && !isVieScolaire() && !isTeacher()) {
    echo '<div class="alert alert-danger">Accès non autorisé.</div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

$eleveId = (int)($_GET['id'] ?? 0);
if (!$eleveId) {
    echo '<div class="alert alert-danger">Aucun élève spécifié.</div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

$pdo = getPDO();
$service = new DisciplineService($pdo);

// Infos élève
$stmtEleve = $pdo->prepare("SELECT * FROM eleves WHERE id = ?");
$stmtEleve->execute([$eleveId]);
$eleve = $stmtEleve->fetch(PDO::FETCH_ASSOC);

if (!$eleve) {
    echo '<div class="alert alert-danger">Élève introuvable.</div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

$fiche = $service->getFicheEleve($eleveId);
$incidents = $fiche['incidents'];
$sanctions = $fiche['sanctions'];
$retenues  = $fiche['retenues'];

$typesIncident = DisciplineService::getTypesIncident();
$typesSanction = DisciplineService::getTypesSanction();
$gravites      = DisciplineService::getGravites();
?>

<h1 class="page-title">
    <i class="fas fa-user-graduate"></i> 
    Fiche discipline — <?= htmlspecialchars($eleve['prenom'] . ' ' . $eleve['nom']) ?>
</h1>

<div class="fiche-header card">
    <div class="fiche-info-grid">
        <div class="fiche-info-item">
            <span class="fiche-label">Classe</span>
            <span class="fiche-value"><?= htmlspecialchars($eleve['classe'] ?? '-') ?></span>
        </div>
        <div class="fiche-info-item">
            <span class="fiche-label">Total incidents</span>
            <span class="fiche-value fiche-value-danger"><?= count($incidents) ?></span>
        </div>
        <div class="fiche-info-item">
            <span class="fiche-label">Total sanctions</span>
            <span class="fiche-value fiche-value-warning"><?= count($sanctions) ?></span>
        </div>
        <div class="fiche-info-item">
            <span class="fiche-label">Retenues</span>
            <span class="fiche-value"><?= count($retenues) ?></span>
        </div>
    </div>
</div>

<!-- Onglets -->
<div class="tabs-container">
    <div class="tabs-header">
        <button class="tab-btn active" data-tab="incidents">
            <i class="fas fa-exclamation-triangle"></i> Incidents (<?= count($incidents) ?>)
        </button>
        <button class="tab-btn" data-tab="sanctions">
            <i class="fas fa-gavel"></i> Sanctions (<?= count($sanctions) ?>)
        </button>
        <button class="tab-btn" data-tab="retenues">
            <i class="fas fa-user-clock"></i> Retenues (<?= count($retenues) ?>)
        </button>
    </div>

    <!-- Tab : Incidents -->
    <div class="tab-content active" id="tab-incidents">
        <?php if (empty($incidents)): ?>
            <div class="empty-state"><i class="fas fa-check-circle"></i><p>Aucun incident signalé.</p></div>
        <?php else: ?>
        <div class="timeline">
            <?php foreach ($incidents as $inc): ?>
            <div class="timeline-item timeline-item-<?= $inc['gravite'] ?>">
                <div class="timeline-date"><?= date('d/m/Y H:i', strtotime($inc['date_incident'])) ?></div>
                <div class="timeline-content">
                    <div class="timeline-header">
                        <span class="badge badge-gravite badge-gravite-<?= $inc['gravite'] ?>">
                            <?= $gravites[$inc['gravite']] ?? $inc['gravite'] ?>
                        </span>
                        <span class="badge badge-type">
                            <?= $typesIncident[$inc['type_incident']] ?? $inc['type_incident'] ?>
                        </span>
                        <span class="badge badge-statut badge-statut-<?= $inc['statut'] ?>">
                            <?= ucfirst(str_replace('_', ' ', $inc['statut'])) ?>
                        </span>
                    </div>
                    <p><?= nl2br(htmlspecialchars($inc['description'])) ?></p>
                    <?php if ($inc['lieu']): ?>
                    <small class="text-muted"><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($inc['lieu']) ?></small>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Tab : Sanctions -->
    <div class="tab-content" id="tab-sanctions">
        <?php if (empty($sanctions)): ?>
            <div class="empty-state"><i class="fas fa-check-circle"></i><p>Aucune sanction.</p></div>
        <?php else: ?>
        <div class="timeline">
            <?php foreach ($sanctions as $s): ?>
            <div class="timeline-item">
                <div class="timeline-date"><?= date('d/m/Y', strtotime($s['date_sanction'])) ?></div>
                <div class="timeline-content">
                    <div class="timeline-header">
                        <span class="badge badge-sanction">
                            <?= $typesSanction[$s['type_sanction']] ?? $s['type_sanction'] ?>
                        </span>
                        <?php if ($s['convocation_parent']): ?>
                        <span class="badge badge-danger"><i class="fas fa-phone"></i> Parents convoqués</span>
                        <?php endif; ?>
                    </div>
                    <p><?= nl2br(htmlspecialchars($s['motif'])) ?></p>
                    <?php if ($s['duree']): ?>
                    <small class="text-muted"><i class="fas fa-clock"></i> Durée : <?= $s['duree'] ?> jour(s)</small>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Tab : Retenues -->
    <div class="tab-content" id="tab-retenues">
        <?php if (empty($retenues)): ?>
            <div class="empty-state"><i class="fas fa-check-circle"></i><p>Aucune retenue.</p></div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Horaire</th>
                        <th>Lieu</th>
                        <th>Présent</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($retenues as $r): ?>
                    <tr>
                        <td><?= date('d/m/Y', strtotime($r['date_retenue'])) ?></td>
                        <td><?= substr($r['heure_debut'], 0, 5) ?> – <?= substr($r['heure_fin'], 0, 5) ?></td>
                        <td><?= htmlspecialchars($r['lieu'] ?? '-') ?></td>
                        <td>
                            <?php if ($r['present'] === null): ?>
                                <span class="badge badge-secondary">Non renseigné</span>
                            <?php elseif ($r['present']): ?>
                                <span class="badge badge-success">Présent</span>
                            <?php else: ?>
                                <span class="badge badge-danger">Absent</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="form-actions">
    <?php if (isAdmin() || isVieScolaire()): ?>
    <a href="sanctions.php?new=1" class="btn btn-primary"><i class="fas fa-gavel"></i> Nouvelle sanction</a>
    <?php endif; ?>
    <a href="signaler.php" class="btn btn-danger"><i class="fas fa-exclamation-triangle"></i> Signaler un incident</a>
    <a href="incidents.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Retour</a>
</div>

<script>
// Gestion des onglets
document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
        this.classList.add('active');
        document.getElementById('tab-' + this.dataset.tab).classList.add('active');
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
