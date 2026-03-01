<?php
/**
 * M06 – Discipline : Détail d'un incident
 */

require_once __DIR__ . '/includes/DisciplineService.php';

$pageTitle = 'Détail incident';
$currentPage = 'incidents';
require_once __DIR__ . '/includes/header.php';
requireAuth();

if (!isAdmin() && !isVieScolaire() && !isTeacher()) {
    echo '<div class="alert alert-danger">Accès non autorisé.</div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    echo '<div class="alert alert-danger">Aucun incident spécifié.</div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

$pdo = getPDO();
$service = new DisciplineService($pdo);
$incident = $service->getIncident($id);

if (!$incident) {
    echo '<div class="alert alert-danger">Incident introuvable.</div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

// Sanctions liées
$sanctions = $service->getSanctions(['eleve_id' => $incident['eleve_id']]);
$sanctionsIncident = array_filter($sanctions, fn($s) => ($s['incident_id'] ?? 0) == $id);

$typesIncident = DisciplineService::getTypesIncident();
$gravites = DisciplineService::getGravites();
$typesSanction = DisciplineService::getTypesSanction();
?>

<h1 class="page-title"><i class="fas fa-exclamation-triangle"></i> Incident #<?= $id ?></h1>

<div class="card detail-card">
    <div class="detail-grid">
        <div class="detail-main">
            <div class="detail-header">
                <span class="badge badge-gravite badge-gravite-<?= $incident['gravite'] ?>">
                    <?= $gravites[$incident['gravite']] ?? $incident['gravite'] ?>
                </span>
                <span class="badge badge-type">
                    <?= $typesIncident[$incident['type_incident']] ?? $incident['type_incident'] ?>
                </span>
                <span class="badge badge-statut badge-statut-<?= $incident['statut'] ?>">
                    <?= ucfirst(str_replace('_', ' ', $incident['statut'])) ?>
                </span>
            </div>

            <div class="detail-section">
                <h3>Description</h3>
                <p><?= nl2br(htmlspecialchars($incident['description'])) ?></p>
            </div>

            <?php if (!empty($sanctionsIncident)): ?>
            <div class="detail-section">
                <h3>Sanctions associées</h3>
                <?php foreach ($sanctionsIncident as $s): ?>
                <div class="sanction-mini-card">
                    <span class="badge badge-sanction"><?= $typesSanction[$s['type_sanction']] ?? $s['type_sanction'] ?></span>
                    <span><?= htmlspecialchars(mb_substr($s['motif'], 0, 80)) ?></span>
                    <span class="text-muted"><?= date('d/m/Y', strtotime($s['date_sanction'])) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="detail-sidebar">
            <div class="detail-info">
                <div class="detail-info-item">
                    <span class="detail-label"><i class="fas fa-user-graduate"></i> Élève</span>
                    <a href="fiche_eleve.php?id=<?= $incident['eleve_id'] ?>" class="link-eleve">
                        <?= htmlspecialchars($incident['eleve_prenom'] . ' ' . $incident['eleve_nom']) ?>
                    </a>
                </div>
                <div class="detail-info-item">
                    <span class="detail-label"><i class="fas fa-chalkboard"></i> Classe</span>
                    <span><?= htmlspecialchars($incident['eleve_classe'] ?? $incident['classe_nom'] ?? '-') ?></span>
                </div>
                <div class="detail-info-item">
                    <span class="detail-label"><i class="fas fa-calendar"></i> Date</span>
                    <span><?= date('d/m/Y à H:i', strtotime($incident['date_incident'])) ?></span>
                </div>
                <?php if ($incident['lieu']): ?>
                <div class="detail-info-item">
                    <span class="detail-label"><i class="fas fa-map-marker-alt"></i> Lieu</span>
                    <span><?= htmlspecialchars($incident['lieu']) ?></span>
                </div>
                <?php endif; ?>
                <?php if ($incident['temoins']): ?>
                <div class="detail-info-item">
                    <span class="detail-label"><i class="fas fa-users"></i> Témoins</span>
                    <span><?= htmlspecialchars($incident['temoins']) ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="form-actions">
    <?php if (isAdmin() || isVieScolaire()): ?>
    <a href="sanctions.php?incident_id=<?= $id ?>" class="btn btn-primary"><i class="fas fa-gavel"></i> Sanctionner</a>
    <a href="traiter_incident.php?id=<?= $id ?>" class="btn btn-warning"><i class="fas fa-edit"></i> Traiter</a>
    <?php endif; ?>
    <a href="incidents.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Retour</a>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
