<?php
/**
 * Vie scolaire — Suivi individuel d'un élève
 */
require_once __DIR__ . '/includes/VieScolaireService.php';
$currentPage = 'suivi';
$pageTitle = 'Suivi élève';
require_once __DIR__ . '/includes/header.php';
requireAuth();

if (!isAdmin() && !isVieScolaire() && !isTeacher()) {
    header('Location: ../accueil/accueil.php');
    exit;
}

$pdo = getPDO();
$service = new VieScolaireService($pdo);
$eleveId = (int)($_GET['id'] ?? 0);

// Recherche AJAX
if (isset($_GET['ajax']) && $_GET['ajax'] === 'search') {
    header('Content-Type: application/json');
    echo json_encode($service->rechercherEleves($_GET['q'] ?? ''));
    exit;
}

$fiche = null;
if ($eleveId) {
    $fiche = $service->getFicheEleve($eleveId);
}
$activeTab = $_GET['tab'] ?? 'resume';
?>

<div class="page-header">
    <h1><i class="fas fa-user-graduate"></i> Suivi élève</h1>
</div>

<!-- Barre de recherche -->
<div class="search-bar-vs">
    <div class="search-input-wrap">
        <i class="fas fa-search"></i>
        <input type="text" id="searchEleve" class="form-control" placeholder="Rechercher un élève (nom, prénom, classe)..." autocomplete="off">
        <div id="searchResults" class="search-dropdown"></div>
    </div>
</div>

<?php if ($fiche && $fiche['eleve']): ?>
<?php $e = $fiche['eleve']; $s = $fiche['stats']; ?>

<div class="fiche-header">
    <div class="fiche-avatar"><?= strtoupper(mb_substr($e['prenom'], 0, 1) . mb_substr($e['nom'], 0, 1)) ?></div>
    <div class="fiche-info">
        <h2><?= htmlspecialchars($e['prenom'] . ' ' . $e['nom']) ?></h2>
        <span class="badge badge-info"><?= htmlspecialchars($e['classe']) ?></span>
        <span class="text-muted"><?= htmlspecialchars($e['date_naissance'] ? formatDate($e['date_naissance']) : '') ?></span>
    </div>
    <div class="fiche-counters">
        <div class="fc <?= $s['abs_injustifiees'] > 3 ? 'fc-alert' : '' ?>"><span class="fc-value"><?= $s['absences'] ?></span><span class="fc-label">Absences <small>(<?= $s['abs_injustifiees'] ?> inj.)</small></span></div>
        <div class="fc <?= $s['retards'] > 5 ? 'fc-alert' : '' ?>"><span class="fc-value"><?= $s['retards'] ?></span><span class="fc-label">Retards</span></div>
        <div class="fc"><span class="fc-value"><?= $s['incidents'] ?></span><span class="fc-label">Incidents</span></div>
        <div class="fc"><span class="fc-value"><?= $s['sanctions'] ?></span><span class="fc-label">Sanctions</span></div>
    </div>
</div>

<!-- Tabs -->
<div class="tabs-bar">
    <a href="?id=<?= $eleveId ?>&tab=resume" class="tab-item <?= $activeTab === 'resume' ? 'active' : '' ?>">Résumé</a>
    <a href="?id=<?= $eleveId ?>&tab=absences" class="tab-item <?= $activeTab === 'absences' ? 'active' : '' ?>">Absences (<?= $s['absences'] ?>)</a>
    <a href="?id=<?= $eleveId ?>&tab=retards" class="tab-item <?= $activeTab === 'retards' ? 'active' : '' ?>">Retards (<?= $s['retards'] ?>)</a>
    <a href="?id=<?= $eleveId ?>&tab=incidents" class="tab-item <?= $activeTab === 'incidents' ? 'active' : '' ?>">Incidents (<?= $s['incidents'] ?>)</a>
    <a href="?id=<?= $eleveId ?>&tab=sanctions" class="tab-item <?= $activeTab === 'sanctions' ? 'active' : '' ?>">Sanctions (<?= $s['sanctions'] ?>)</a>
</div>

<div class="tab-content">
<?php if ($activeTab === 'resume'): ?>
    <div class="resume-grid">
        <div class="card">
            <div class="card-header"><h3>Dernières absences</h3></div>
            <div class="card-body p-0">
                <table class="data-table">
                    <thead><tr><th>Date</th><th>Type</th><th>Justifié</th></tr></thead>
                    <tbody>
                    <?php foreach (array_slice($fiche['absences'], 0, 5) as $a): ?>
                        <tr>
                            <td><?= formatDate($a['date_debut']) ?></td>
                            <td><?= htmlspecialchars($a['type_absence']) ?></td>
                            <td><?= $a['justifie'] ? '<span class="badge badge-success">Oui</span>' : '<span class="badge badge-danger">Non</span>' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><h3>Derniers incidents</h3></div>
            <div class="card-body p-0">
                <table class="data-table">
                    <thead><tr><th>Date</th><th>Type</th><th>Gravité</th></tr></thead>
                    <tbody>
                    <?php foreach (array_slice($fiche['incidents'] ?? [], 0, 5) as $i): ?>
                        <tr>
                            <td><?= formatDate($i['date_incident']) ?></td>
                            <td><?= htmlspecialchars($i['type_incident']) ?></td>
                            <td><span class="badge badge-<?= $i['gravite'] === 'grave' || $i['gravite'] === 'tres_grave' ? 'danger' : 'warning' ?>"><?= htmlspecialchars($i['gravite']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

<?php elseif ($activeTab === 'absences'): ?>
    <table class="data-table">
        <thead><tr><th>Début</th><th>Fin</th><th>Type</th><th>Motif</th><th>Justifié</th></tr></thead>
        <tbody>
        <?php foreach ($fiche['absences'] as $a): ?>
            <tr>
                <td><?= formatDateTime($a['date_debut']) ?></td>
                <td><?= formatDateTime($a['date_fin']) ?></td>
                <td><?= htmlspecialchars($a['type_absence']) ?></td>
                <td><?= htmlspecialchars($a['motif'] ?? '-') ?></td>
                <td><?= $a['justifie'] ? '<span class="badge badge-success">Oui</span>' : '<span class="badge badge-danger">Non</span>' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

<?php elseif ($activeTab === 'retards'): ?>
    <table class="data-table">
        <thead><tr><th>Date</th><th>Durée</th><th>Motif</th><th>Justifié</th></tr></thead>
        <tbody>
        <?php foreach ($fiche['retards'] as $r): ?>
            <tr>
                <td><?= formatDateTime($r['date_retard']) ?></td>
                <td><?= $r['duree_minutes'] ?> min</td>
                <td><?= htmlspecialchars($r['motif'] ?? '-') ?></td>
                <td><?= $r['justifie'] ? '<span class="badge badge-success">Oui</span>' : '<span class="badge badge-danger">Non</span>' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

<?php elseif ($activeTab === 'incidents'): ?>
    <table class="data-table">
        <thead><tr><th>Date</th><th>Type</th><th>Gravité</th><th>Lieu</th><th>Statut</th></tr></thead>
        <tbody>
        <?php foreach ($fiche['incidents'] ?? [] as $i): ?>
            <tr>
                <td><?= formatDateTime($i['date_incident']) ?></td>
                <td><?= htmlspecialchars($i['type_incident']) ?></td>
                <td><span class="badge badge-<?= in_array($i['gravite'], ['grave','tres_grave']) ? 'danger' : 'warning' ?>"><?= htmlspecialchars($i['gravite']) ?></span></td>
                <td><?= htmlspecialchars($i['lieu'] ?? '-') ?></td>
                <td><?= htmlspecialchars($i['statut']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

<?php elseif ($activeTab === 'sanctions'): ?>
    <table class="data-table">
        <thead><tr><th>Date</th><th>Type</th><th>Motif</th><th>Statut</th></tr></thead>
        <tbody>
        <?php foreach ($fiche['sanctions'] ?? [] as $sa): ?>
            <tr>
                <td><?= formatDate($sa['date_sanction']) ?></td>
                <td><?= htmlspecialchars($sa['type_sanction']) ?></td>
                <td><?= htmlspecialchars(mb_substr($sa['motif'], 0, 80)) ?></td>
                <td><?= htmlspecialchars($sa['statut']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
</div>

<?php else: ?>
<div class="empty-state">
    <i class="fas fa-search"></i>
    <p>Recherchez un élève pour afficher son suivi complet.</p>
</div>
<?php endif; ?>

<script>
(function() {
    const input = document.getElementById('searchEleve');
    const results = document.getElementById('searchResults');
    let timeout;
    input.addEventListener('input', function() {
        clearTimeout(timeout);
        const q = this.value.trim();
        if (q.length < 2) { results.innerHTML = ''; results.style.display = 'none'; return; }
        timeout = setTimeout(() => {
            fetch('suivi_eleve.php?ajax=search&q=' + encodeURIComponent(q))
                .then(r => r.json())
                .then(data => {
                    if (!data.length) { results.innerHTML = '<div class="search-item text-muted">Aucun résultat</div>'; }
                    else { results.innerHTML = data.map(e => `<a href="suivi_eleve.php?id=${e.id}" class="search-item"><strong>${e.prenom} ${e.nom}</strong> <span class="text-muted">${e.classe}</span></a>`).join(''); }
                    results.style.display = 'block';
                });
        }, 300);
    });
    document.addEventListener('click', e => { if (!e.target.closest('.search-bar-vs')) results.style.display = 'none'; });
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
