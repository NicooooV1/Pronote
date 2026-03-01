<?php
/**
 * M15 – Trombinoscope — Page principale
 */
$pageTitle = 'Trombinoscope';
require_once __DIR__ . '/includes/header.php';

$classes  = $trombiService->getClasses();
$matieres = $trombiService->getMatieres();
$stats    = $trombiService->getStats();

$vue = $_GET['vue'] ?? 'eleves';
$classeId = isset($_GET['classe_id']) ? (int)$_GET['classe_id'] : ($classes[0]['id'] ?? 0);
$matiereId = isset($_GET['matiere_id']) ? (int)$_GET['matiere_id'] : 0;
$search = trim($_GET['q'] ?? '');

$personnes = [];
$classeNom = '';

if ($search) {
    $personnes = $trombiService->rechercher($search);
} elseif ($vue === 'profs') {
    $personnes = $trombiService->getProfesseurs($matiereId ?: null);
} elseif ($vue === 'vie_scolaire') {
    $personnes = $trombiService->getVieScolaire();
} else {
    $personnes = $trombiService->getElevesClasse($classeId);
    foreach ($classes as $c) {
        if ($c['id'] == $classeId) { $classeNom = $c['nom']; break; }
    }
}
?>

<div class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-users"></i> Trombinoscope</h1>
        <p class="page-subtitle"><?= $stats['eleves'] ?> élèves · <?= $stats['profs'] ?> professeurs · <?= $stats['classes'] ?> classes</p>
    </div>

    <!-- Barre de recherche -->
    <div class="trombi-search">
        <form method="get" class="search-form-trombi">
            <input type="hidden" name="vue" value="<?= h($vue) ?>">
            <div class="search-input-wrap">
                <i class="fas fa-search"></i>
                <input type="text" name="q" value="<?= h($search) ?>" placeholder="Rechercher un élève ou professeur..." class="form-control">
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Rechercher</button>
            <?php if ($search): ?>
                <a href="trombinoscope.php?vue=<?= h($vue) ?>" class="btn btn-secondary">Effacer</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Onglets -->
    <div class="trombi-tabs">
        <a href="?vue=eleves" class="trombi-tab <?= $vue === 'eleves' ? 'active' : '' ?>"><i class="fas fa-user-graduate"></i> Élèves</a>
        <a href="?vue=profs" class="trombi-tab <?= $vue === 'profs' ? 'active' : '' ?>"><i class="fas fa-chalkboard-teacher"></i> Professeurs</a>
        <a href="?vue=vie_scolaire" class="trombi-tab <?= $vue === 'vie_scolaire' ? 'active' : '' ?>"><i class="fas fa-user-shield"></i> Vie scolaire</a>
    </div>

    <!-- Filtres -->
    <?php if (!$search): ?>
    <div class="trombi-filter">
        <?php if ($vue === 'eleves'): ?>
            <form method="get">
                <input type="hidden" name="vue" value="eleves">
                <label>Classe :</label>
                <select name="classe_id" onchange="this.form.submit()" class="form-select">
                    <?php foreach ($classes as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $c['id'] == $classeId ? 'selected' : '' ?>>
                            <?= h($c['niveau'] . ' – ' . $c['nom']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        <?php elseif ($vue === 'profs'): ?>
            <form method="get">
                <input type="hidden" name="vue" value="profs">
                <label>Matière :</label>
                <select name="matiere_id" onchange="this.form.submit()" class="form-select">
                    <option value="0">Toutes les matières</option>
                    <?php foreach ($matieres as $m): ?>
                        <option value="<?= $m['id'] ?>" <?= $m['id'] == $matiereId ? 'selected' : '' ?>>
                            <?= h($m['nom']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($search): ?>
        <p class="trombi-result-info"><strong><?= count($personnes) ?></strong> résultat(s) pour « <?= h($search) ?> »</p>
    <?php elseif ($vue === 'eleves' && $classeNom): ?>
        <p class="trombi-result-info">Classe <strong><?= h($classeNom) ?></strong> — <?= count($personnes) ?> élève(s)</p>
    <?php endif; ?>

    <!-- Grille -->
    <div class="trombi-grid">
        <?php if (empty($personnes)): ?>
            <div class="empty-state">
                <i class="fas fa-users-slash fa-2x"></i>
                <p>Aucune personne trouvée.</p>
            </div>
        <?php else: ?>
            <?php foreach ($personnes as $p): ?>
                <?php
                    $initials = TrombinoscopeService::initials($p['prenom'], $p['nom']);
                    $color = TrombinoscopeService::avatarColor($p['nom'] . $p['prenom']);
                    $type = $p['type'] ?? ($vue === 'profs' ? 'professeur' : ($vue === 'vie_scolaire' ? 'vie_scolaire' : 'eleve'));
                    $detail = $p['detail'] ?? $p['classe_nom'] ?? $p['specialite'] ?? $p['matiere_nom'] ?? '';
                ?>
                <div class="trombi-card" data-type="<?= $type ?>">
                    <div class="trombi-avatar" style="background:<?= $color ?>">
                        <?= $initials ?>
                    </div>
                    <div class="trombi-name"><?= h($p['prenom'] . ' ' . $p['nom']) ?></div>
                    <div class="trombi-detail">
                        <?php if ($type === 'eleve'): ?>
                            <span class="badge badge-primary"><?= h($detail) ?></span>
                        <?php elseif ($type === 'professeur'): ?>
                            <span class="badge badge-info"><?= h($detail ?: 'Professeur') ?></span>
                        <?php else: ?>
                            <span class="badge badge-secondary">Vie scolaire</span>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($p['email'])): ?>
                        <div class="trombi-email"><i class="fas fa-envelope"></i> <?= h($p['email']) ?></div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
require_once __DIR__ . '/includes/footer.php';
