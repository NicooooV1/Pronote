<?php
/**
 * Module Notes — Page principale.
 * Affiche les notes par rôle : élève, professeur, admin/vie scolaire.
 * Utilise NoteService pour centraliser les requêtes SQL.
 */

// Boot standardisé — fournit $user, $user_role, $user_fullname, $user_initials, $pdo, $isAdmin, $rootPrefix
$pageTitle  = 'Notes';
$activePage = 'notes';
require_once __DIR__ . '/../API/module_boot.php';

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/NoteService.php';
$noteService = new NoteService($pdo);

// Récupérer le trimestre sélectionné (par défaut: actuel)
$selectedTrimestre = filter_input(INPUT_GET, 'trimestre', FILTER_VALIDATE_INT);
if (!$selectedTrimestre || $selectedTrimestre < 1 || $selectedTrimestre > 3) {
    $selectedTrimestre = NoteService::getTrimestreCourant();
}

// Parent : récupérer la liste des enfants
$enfants = [];
$selectedEnfantId = 0;
$selectedEnfantNom = '';
if ($user_role === 'parent') {
    try {
        $stmtEnfants = $pdo->prepare("
            SELECT e.id, e.nom, e.prenom, e.classe
            FROM parent_eleve pe
            JOIN eleves e ON pe.id_eleve = e.id
            WHERE pe.id_parent = ?
            ORDER BY e.prenom, e.nom
        ");
        $stmtEnfants->execute([$user['id']]);
        $enfants = $stmtEnfants->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {}

    $selectedEnfantId = (int) ($_GET['enfant'] ?? ($_SESSION['selected_enfant_id'] ?? 0));
    if (!$selectedEnfantId && !empty($enfants)) {
        $selectedEnfantId = $enfants[0]['id'];
    }
    $_SESSION['selected_enfant_id'] = $selectedEnfantId;

    foreach ($enfants as $e) {
        if ($e['id'] == $selectedEnfantId) {
            $selectedEnfantNom = trim($e['prenom'] . ' ' . $e['nom']);
            break;
        }
    }
}

// Charger les données via le service
$notes = [];
$moyennes_par_matiere = [];
$moyenneGenerale = null;
$classStats = null;
$totalNotes = 0;
$currentPage = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 50;

// Filtres prof/admin
$filterClasse  = $_GET['classe']  ?? '';
$filterMatiere = (int) ($_GET['matiere'] ?? 0);

try {
    if ($user_role === 'eleve') {
        $notes = $noteService->getNotesEleve($user['id'], $selectedTrimestre);
        $moyennes_par_matiere = $noteService->getMoyennesParMatiere($user['id'], $selectedTrimestre);
        $moyenneGenerale = $noteService->getMoyenneGenerale($user['id'], $selectedTrimestre);
        $totalNotes = count($notes);
    } elseif ($user_role === 'parent' && $selectedEnfantId > 0) {
        $notes = $noteService->getNotesEleve($selectedEnfantId, $selectedTrimestre);
        $moyennes_par_matiere = $noteService->getMoyennesParMatiere($selectedEnfantId, $selectedTrimestre);
        $moyenneGenerale = $noteService->getMoyenneGenerale($selectedEnfantId, $selectedTrimestre);
        $totalNotes = count($notes);
    } elseif ($user_role === 'professeur') {
        $notes = $noteService->getNotesProfesseur($user['id'], $selectedTrimestre);
        $totalNotes = count($notes);
        // Stats de classe si filtres renseignés
        if ($filterClasse && $filterMatiere) {
            $classStats = $noteService->getStatsClasse($filterClasse, $filterMatiere, $selectedTrimestre);
        }
    } else {
        // Admin/vie scolaire : pagination + filtres SQL
        $offset = ($currentPage - 1) * $perPage;
        $result = $noteService->getAllNotes($selectedTrimestre, $perPage, $offset, $filterClasse, $filterMatiere);
        $notes = $result['notes'];
        $totalNotes = $result['total'];
        if ($filterClasse && $filterMatiere) {
            $classStats = $noteService->getStatsClasse($filterClasse, $filterMatiere, $selectedTrimestre);
        }
    }
} catch (PDOException $e) {
    error_log("Erreur notes: " . $e->getMessage());
}

$totalPages = max(1, (int) ceil($totalNotes / $perPage));

// Données de référence pour les filtres prof/admin
$availableClasses  = [];
$availableMatieres = [];
if ($user_role !== 'eleve') {
    try {
        $availableClasses  = $noteService->getClasses();
        $availableMatieres = $noteService->getMatieres();
    } catch (PDOException $e) {}

    // Filtrer côté PHP pour le professeur (petit dataset)
    if ($user_role === 'professeur' && !empty($notes)) {
        if ($filterClasse) {
            $notes = array_filter($notes, fn($n) => ($n['classe'] ?? '') === $filterClasse);
        }
        if ($filterMatiere) {
            $notes = array_filter($notes, fn($n) => ($n['id_matiere'] ?? 0) == $filterMatiere);
        }
        $notes = array_values($notes);
        $totalNotes = count($notes);
    }
}

// Statistiques admin
$moyenneGlobale = 0;
$nbMatieresEvaluees = 0;
if ($user_role !== 'eleve' && !empty($notes)) {
    $moyenneGlobale = $noteService->calculerMoyenneGlobale($notes);
    $nbMatieresEvaluees = count(array_unique(array_column($notes, 'id_matiere')));
}

// Configuration des templates partagés
$pageTitle = 'Notes et résultats';
$activePage = 'notes';
$isAdmin = $user_role === 'administrateur';
$extraCss = ['assets/css/notes.css'];

// Feature flags
$features = null;
try { $features = app('features'); } catch (\Throwable $e) {}
$ffGraphs     = $features ? $features->isEnabled('notes.statistics_graphs') : true;
$ffBatchEntry = $features ? $features->isEnabled('notes.batch_entry') : true;
$ffExportPdf  = $features ? $features->isEnabled('notes.export_pdf') : true;
$ffLock       = $features ? $features->isEnabled('notes.lock_after_deadline') : true;

// Extra JS for graphs
$extraHeadHtml = '';
if ($ffGraphs) {
    $extraHeadHtml = '<script src="' . $rootPrefix . 'notes/assets/js/notes-graphs.js" defer></script>';
}

// Inclusion des templates partagés
include __DIR__ . '/../templates/shared_header.php';
include __DIR__ . '/../templates/shared_topbar.php';
?>

            <!-- Contenu principal -->
            <div class="content-container">

                <?php if (isAdmin()): ?>
                <div class="admin-toolbar">
                    <span class="admin-toolbar-badge"><i class="fas fa-shield-alt"></i> Administration</span>
                    <span style="font-size:13px;color:#4a5568">Vue complète — <?= $totalNotes ?> note(s) au total</span>
                    <a href="ajouter_note.php" class="btn-sm" style="background:#059669;color:white;text-decoration:none;margin-left:auto"><i class="fas fa-plus"></i> Ajouter une note</a>
                    <a href="../admin/scolaire/notes.php" class="btn-sm" style="background:#0f4c81;color:white;text-decoration:none"><i class="fas fa-cog"></i> Panneau admin</a>
                </div>
                <?php endif; ?>

                <!-- Export + Verrouillage (prof/admin) -->
                <?php if (in_array($user_role, ['professeur', 'administrateur'])): ?>
                <div style="display:flex;gap:0.5rem;margin-bottom:1rem;flex-wrap:wrap;">
                    <a href="export.php?format=csv&trimestre=<?= $selectedTrimestre ?><?= $filterClasse ? '&classe='.urlencode($filterClasse) : '' ?><?= $filterMatiere ? '&matiere='.$filterMatiere : '' ?>"
                       class="ds-btn ds-btn-outline ds-btn-sm"><i class="fas fa-file-csv"></i> Export CSV</a>
                    <?php if ($ffExportPdf): ?>
                    <a href="export.php?format=pdf&trimestre=<?= $selectedTrimestre ?><?= $filterClasse ? '&classe='.urlencode($filterClasse) : '' ?><?= $filterMatiere ? '&matiere='.$filterMatiere : '' ?>"
                       class="ds-btn ds-btn-outline ds-btn-sm"><i class="fas fa-file-pdf"></i> Export PDF</a>
                    <?php endif; ?>
                    <?php if ($ffLock && isAdmin() && $filterClasse && $filterMatiere): ?>
                    <form method="POST" action="lock_notes.php" style="margin-left:auto;">
                        <?= csrfField() ?>
                        <input type="hidden" name="classe" value="<?= htmlspecialchars($filterClasse) ?>">
                        <input type="hidden" name="matiere" value="<?= $filterMatiere ?>">
                        <input type="hidden" name="trimestre" value="<?= $selectedTrimestre ?>">
                        <button type="submit" class="ds-btn ds-btn-warning ds-btn-sm" onclick="return confirm('Verrouiller toutes les notes de cette classe/matière pour ce trimestre ?')">
                            <i class="fas fa-lock"></i> Verrouiller les notes
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- Sélecteur de trimestre -->
                <div class="trimestre-selector">
                    <span class="trimestre-label">Période :</span>
                    <?php for ($t = 1; $t <= 3; $t++): ?>
                    <a href="?trimestre=<?= $t ?>" class="btn <?= $selectedTrimestre === $t ? 'btn-primary' : 'btn-secondary' ?>">
                        <?= $t === 1 ? '1er' : $t . 'ème' ?> trimestre
                    </a>
                    <?php endfor; ?>
                </div>

                <?php if ($user_role === 'parent'): ?>
                <!-- ========== VUE PARENT ========== -->
                <?php if (count($enfants) > 1): ?>
                <div style="display:flex; gap:10px; flex-wrap:wrap; margin-bottom:20px; background:white; padding:15px 20px; border-radius:10px; box-shadow:0 2px 8px rgba(0,0,0,0.06);">
                    <span style="font-weight:600; color:#4a5568; line-height:36px;">Enfant :</span>
                    <?php foreach ($enfants as $e): ?>
                    <a href="?trimestre=<?= $selectedTrimestre ?>&enfant=<?= $e['id'] ?>"
                       class="btn <?= $e['id'] == $selectedEnfantId ? 'btn-primary' : 'btn-secondary' ?>">
                        <?= htmlspecialchars($e['prenom'] . ' ' . $e['nom']) ?>
                        <span style="font-size:11px; opacity:0.8;">(<?= htmlspecialchars($e['classe'] ?? '') ?>)</span>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php elseif (!empty($enfants)): ?>
                <p style="color:#4a5568; margin-bottom:16px;">Notes de <strong><?= htmlspecialchars($selectedEnfantNom) ?></strong></p>
                <?php endif; ?>

                <?php if (empty($enfants)): ?>
                    <div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> Aucun enfant associé à votre compte.</div>
                <?php elseif ($selectedEnfantId > 0): ?>

                    <?php if ($moyenneGenerale !== null): ?>
                    <div class="notes-hero-card">
                        <div>
                            <div class="notes-hero-label">Moyenne générale de <?= htmlspecialchars($selectedEnfantNom) ?> — <?= $selectedTrimestre === 1 ? '1er' : $selectedTrimestre . 'ème' ?> trimestre</div>
                            <div class="notes-hero-value"><?= $moyenneGenerale ?><span class="notes-hero-unit">/20</span></div>
                        </div>
                        <div class="notes-hero-icon"><i class="fas fa-graduation-cap"></i></div>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($moyennes_par_matiere)): ?>
                    <h2 class="section-title">Moyennes par matière</h2>
                    <div class="notes-grid">
                        <?php foreach ($moyennes_par_matiere as $m): ?>
                        <div class="notes-matiere-card" style="border-left-color:<?= htmlspecialchars($m['couleur'] ?? '#3498db') ?>">
                            <div class="notes-matiere-name"><?= htmlspecialchars($m['matiere_nom'] ?? 'Matière') ?></div>
                            <div class="notes-matiere-moyenne"><?= $m['moyenne'] ?><span class="notes-matiere-unit">/20</span></div>
                            <div class="notes-matiere-count"><?= $m['nb_notes'] ?> évaluation<?= $m['nb_notes'] > 1 ? 's' : '' ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <?php if ($ffGraphs && $selectedEnfantId > 0): ?>
                    <div class="notes-graphs-section">
                        <h2 class="section-title"><i class="fas fa-chart-line" style="margin-right:6px;color:var(--module-color)"></i> Évolution par trimestre</h2>
                        <div class="notes-graph-panel active"
                             data-graph-type="evolution"
                             data-graph-url="includes/ajax_stats.php?type=evolution&eleve_id=<?= $selectedEnfantId ?>"
                             data-graph-canvas="canvas-evolution-parent">
                            <canvas id="canvas-evolution-parent" class="notes-graph-canvas"></canvas>
                        </div>
                    </div>
                    <?php endif; ?>

                    <h2 class="section-title">Détail des notes</h2>
                    <?php if (empty($notes)): ?>
                        <div class="alert alert-info"><i class="fas fa-info-circle"></i> Aucune note pour ce trimestre.</div>
                    <?php else: ?>
                    <div class="notes-table-wrapper">
                        <table class="notes-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Matière</th>
                                    <th>Évaluation</th>
                                    <th class="text-center">Note</th>
                                    <th class="text-center">Coeff.</th>
                                    <th>Professeur</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($notes as $n): ?>
                                <tr>
                                    <td><?= date('d/m/Y', strtotime($n['date_note'])) ?></td>
                                    <td>
                                        <span class="badge-matiere" style="background:<?= htmlspecialchars($n['matiere_couleur'] ?? '#3498db') ?>20; color:<?= htmlspecialchars($n['matiere_couleur'] ?? '#3498db') ?>;">
                                            <?= htmlspecialchars($n['matiere_nom'] ?? '') ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($n['type_evaluation'] ?? '') ?></td>
                                    <td class="text-center note-value <?= ($n['note'] / ($n['note_sur'] ?: 20) * 20) >= 10 ? 'note-good' : 'note-bad' ?>">
                                        <?= $n['note'] ?><span class="note-sur">/<?= $n['note_sur'] ?></span>
                                    </td>
                                    <td class="text-center text-muted">×<?= $n['coefficient'] ?></td>
                                    <td class="text-muted"><?= htmlspecialchars($n['professeur_nom'] ?? '') ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>

                <?php elseif ($user_role === 'eleve'): ?>
                <!-- ========== VUE ÉLÈVE ========== -->

                <?php if ($moyenneGenerale !== null): ?>
                <div class="notes-hero-card">
                    <div>
                        <div class="notes-hero-label">Moyenne générale — <?= $selectedTrimestre === 1 ? '1er' : $selectedTrimestre . 'ème' ?> trimestre</div>
                        <div class="notes-hero-value"><?= $moyenneGenerale ?><span class="notes-hero-unit">/20</span></div>
                    </div>
                    <div class="notes-hero-icon"><i class="fas fa-graduation-cap"></i></div>
                </div>
                <?php endif; ?>

                <?php if (!empty($moyennes_par_matiere)): ?>
                <h2 class="section-title">Moyennes par matière</h2>
                <div class="notes-grid">
                    <?php foreach ($moyennes_par_matiere as $m): ?>
                    <div class="notes-matiere-card" style="border-left-color:<?= htmlspecialchars($m['couleur'] ?? '#3498db') ?>">
                        <div class="notes-matiere-name"><?= htmlspecialchars($m['matiere_nom'] ?? 'Matière') ?></div>
                        <div class="notes-matiere-moyenne">
                            <?= $m['moyenne'] ?><span class="notes-matiere-unit">/20</span>
                        </div>
                        <div class="notes-matiere-count"><?= $m['nb_notes'] ?> évaluation<?= $m['nb_notes'] > 1 ? 's' : '' ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <?php if ($ffGraphs): ?>
                <!-- Graphique évolution -->
                <div class="notes-graphs-section">
                    <h2 class="section-title"><i class="fas fa-chart-line" style="margin-right:6px;color:var(--module-color)"></i> Évolution par trimestre</h2>
                    <div class="notes-graph-panel active"
                         data-graph-type="evolution"
                         data-graph-url="includes/ajax_stats.php?type=evolution&eleve_id=<?= $user['id'] ?>"
                         data-graph-canvas="canvas-evolution-eleve">
                        <canvas id="canvas-evolution-eleve" class="notes-graph-canvas"></canvas>
                    </div>
                </div>
                <?php endif; ?>

                <h2 class="section-title">Détail des notes</h2>
                <?php if (empty($notes)): ?>
                    <div class="alert alert-info"><i class="fas fa-info-circle"></i> Aucune note pour ce trimestre.</div>
                <?php else: ?>
                <div class="notes-table-wrapper">
                    <table class="notes-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Matière</th>
                                <th>Évaluation</th>
                                <th class="text-center">Note</th>
                                <th class="text-center">Coeff.</th>
                                <th>Professeur</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($notes as $n): ?>
                            <tr>
                                <td><?= date('d/m/Y', strtotime($n['date_note'])) ?></td>
                                <td>
                                    <span class="badge-matiere" style="background:<?= htmlspecialchars($n['matiere_couleur'] ?? '#3498db') ?>20; color:<?= htmlspecialchars($n['matiere_couleur'] ?? '#3498db') ?>;">
                                        <?= htmlspecialchars($n['matiere_nom'] ?? '') ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($n['type_evaluation'] ?? '') ?></td>
                                <td class="text-center note-value <?= ($n['note'] / ($n['note_sur'] ?: 20) * 20) >= 10 ? 'note-good' : 'note-bad' ?>">
                                    <?= $n['note'] ?><span class="note-sur">/<?= $n['note_sur'] ?></span>
                                </td>
                                <td class="text-center text-muted">×<?= $n['coefficient'] ?></td>
                                <td class="text-muted"><?= htmlspecialchars($n['professeur_nom'] ?? '') ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>

                <?php elseif ($user_role === 'professeur'): ?>
                <!-- ========== VUE PROFESSEUR ========== -->

                <div class="section-header">
                    <h2 class="section-title">Notes attribuées — <?= $selectedTrimestre === 1 ? '1er' : $selectedTrimestre . 'ème' ?> trimestre</h2>
                    <a href="form_note.php" class="btn btn-primary"><i class="fas fa-plus"></i> Ajouter des notes</a>
                </div>

                <!-- Filtres classe / matière -->
                <?php if (!empty($availableClasses)): ?>
                <form method="get" class="notes-filter-bar" style="display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end; margin-bottom:20px; background:white; padding:15px 20px; border-radius:10px; box-shadow:0 2px 8px rgba(0,0,0,0.06);">
                    <input type="hidden" name="trimestre" value="<?= $selectedTrimestre ?>">
                    <div>
                        <label style="display:block; font-size:12px; font-weight:600; color:#4a5568; margin-bottom:4px;">Classe</label>
                        <select name="classe" class="form-control" style="min-width:140px;">
                            <option value="">Toutes</option>
                            <?php foreach ($availableClasses as $c): ?>
                            <option value="<?= htmlspecialchars($c) ?>" <?= $filterClasse === $c ? 'selected' : '' ?>><?= htmlspecialchars($c) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label style="display:block; font-size:12px; font-weight:600; color:#4a5568; margin-bottom:4px;">Matière</label>
                        <select name="matiere" class="form-control" style="min-width:160px;">
                            <option value="">Toutes</option>
                            <?php foreach ($availableMatieres as $m): ?>
                            <option value="<?= $m['id'] ?>" <?= $filterMatiere == $m['id'] ? 'selected' : '' ?>><?= htmlspecialchars($m['nom']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-secondary" style="height:38px;"><i class="fas fa-filter"></i> Filtrer</button>
                    <?php if ($filterClasse || $filterMatiere): ?>
                    <a href="?trimestre=<?= $selectedTrimestre ?>" class="btn btn-sm" style="color:#718096; height:38px; line-height:38px;">✕ Réinitialiser</a>
                    <?php endif; ?>
                </form>
                <?php endif; ?>

                <!-- Statistiques de classe -->
                <?php if ($classStats && $classStats['nb_notes'] > 0): ?>
                <div class="notes-stats-grid" style="margin-bottom:25px;">
                    <div class="notes-stat-card">
                        <div class="notes-stat-value primary"><?= $classStats['moyenne_classe'] ?></div>
                        <div class="notes-stat-label">Moyenne /20</div>
                    </div>
                    <div class="notes-stat-card">
                        <div class="notes-stat-value" style="color:#667eea;"><?= $classStats['mediane'] ?></div>
                        <div class="notes-stat-label">Médiane /20</div>
                    </div>
                    <div class="notes-stat-card">
                        <div class="notes-stat-value" style="color:#e53e3e;"><?= $classStats['note_min'] ?></div>
                        <div class="notes-stat-label">Note min /20</div>
                    </div>
                    <div class="notes-stat-card">
                        <div class="notes-stat-value success"><?= $classStats['note_max'] ?></div>
                        <div class="notes-stat-label">Note max /20</div>
                    </div>
                    <div class="notes-stat-card">
                        <div class="notes-stat-value info"><?= $classStats['nb_eleves'] ?></div>
                        <div class="notes-stat-label">Élèves évalués</div>
                    </div>
                </div>

                <?php if ($ffGraphs && $filterClasse && $filterMatiere): ?>
                <!-- Graphiques statistiques -->
                <div class="notes-graphs-section">
                    <div class="notes-graphs-tabs">
                        <button class="notes-graphs-tab active" data-tab="tab-histogram"><i class="fas fa-chart-bar"></i> Distribution</button>
                        <button class="notes-graphs-tab" data-tab="tab-boxplot"><i class="fas fa-chart-area"></i> Comparaison matières</button>
                    </div>
                    <div id="tab-histogram" class="notes-graph-panel active"
                         data-graph-type="histogram"
                         data-graph-url="includes/ajax_stats.php?type=distribution&classe=<?= urlencode($filterClasse) ?>&matiere=<?= $filterMatiere ?>&trimestre=<?= $selectedTrimestre ?>"
                         data-graph-canvas="canvas-histogram">
                        <div class="notes-graph-title">Distribution des notes — <?= htmlspecialchars($filterClasse) ?></div>
                        <canvas id="canvas-histogram" class="notes-graph-canvas"></canvas>
                    </div>
                    <div id="tab-boxplot" class="notes-graph-panel"
                         data-graph-type="boxplot"
                         data-graph-url="includes/ajax_stats.php?type=boxplot&classe=<?= urlencode($filterClasse) ?>&trimestre=<?= $selectedTrimestre ?>"
                         data-graph-canvas="canvas-boxplot">
                        <div class="notes-graph-title">Comparaison par matière — <?= htmlspecialchars($filterClasse) ?></div>
                        <canvas id="canvas-boxplot" class="notes-graph-canvas"></canvas>
                    </div>
                </div>
                <script>
                (function() {
                    var tabs = document.querySelectorAll('.notes-graphs-tab');
                    for (var i = 0; i < tabs.length; i++) {
                        tabs[i].addEventListener('click', function() {
                            var target = this.getAttribute('data-tab');
                            var panels = document.querySelectorAll('.notes-graph-panel');
                            for (var j = 0; j < tabs.length; j++) tabs[j].classList.remove('active');
                            for (var k = 0; k < panels.length; k++) panels[k].classList.remove('active');
                            this.classList.add('active');
                            var panel = document.getElementById(target);
                            if (panel) {
                                panel.classList.add('active');
                                // Trigger graph load if not yet loaded
                                var canvas = panel.querySelector('canvas');
                                if (canvas && !canvas.getAttribute('data-loaded')) {
                                    canvas.setAttribute('data-loaded', '1');
                                    var type = panel.getAttribute('data-graph-type');
                                    var url = panel.getAttribute('data-graph-url');
                                    var cId = panel.getAttribute('data-graph-canvas');
                                    var xhr = new XMLHttpRequest();
                                    xhr.open('GET', url, true);
                                    xhr.onload = function() {
                                        if (xhr.status === 200) {
                                            var data = JSON.parse(xhr.responseText);
                                            if (type === 'histogram') FronoteGraphs.histogram(cId, data);
                                            else if (type === 'boxplot') FronoteGraphs.boxPlot(cId, data);
                                        }
                                    };
                                    xhr.send();
                                }
                            }
                        });
                    }
                })();
                </script>
                <?php endif; ?>
                <?php endif; ?>

                <?php if (empty($notes)): ?>
                    <div class="alert alert-info"><i class="fas fa-info-circle"></i> Aucune note pour ce trimestre. Commencez par en ajouter.</div>
                <?php else: ?>
                <div class="notes-table-wrapper">
                    <table class="notes-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Élève</th>
                                <th>Classe</th>
                                <th>Matière</th>
                                <th>Évaluation</th>
                                <th class="text-center">Note</th>
                                <th class="text-center">Coeff.</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($notes as $n): ?>
                            <tr>
                                <td><?= date('d/m/Y', strtotime($n['date_note'])) ?></td>
                                <td class="font-medium"><?= htmlspecialchars($n['eleve_nom'] ?? '') ?></td>
                                <td class="text-muted"><?= htmlspecialchars($n['classe'] ?? '') ?></td>
                                <td>
                                    <span class="badge-matiere" style="background:<?= htmlspecialchars($n['matiere_couleur'] ?? '#3498db') ?>20; color:<?= htmlspecialchars($n['matiere_couleur'] ?? '#3498db') ?>;">
                                        <?= htmlspecialchars($n['matiere_nom'] ?? '') ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($n['type_evaluation'] ?? '') ?></td>
                                <td class="text-center note-value <?= ($n['note'] / ($n['note_sur'] ?: 20) * 20) >= 10 ? 'note-good' : 'note-bad' ?>">
                                    <?= $n['note'] ?>/<?= $n['note_sur'] ?>
                                </td>
                                <td class="text-center text-muted">×<?= $n['coefficient'] ?></td>
                                <td class="text-center">
                                    <a href="form_note.php?id=<?= $n['id'] ?>" class="btn btn-sm btn-secondary" title="Modifier"><i class="fas fa-edit"></i></a>
                                    <form method="POST" action="supprimer_note.php" style="display:inline;" onsubmit="return confirm('Supprimer cette note ?');">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_hdr_csrf_token ?? '') ?>">
                                        <input type="hidden" name="id" value="<?= $n['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-danger" title="Supprimer"><i class="fas fa-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>

                <?php else: ?>
                <!-- ========== VUE ADMIN / VIE SCOLAIRE ========== -->

                <div class="section-header">
                    <h2 class="section-title">Toutes les notes — <?= $selectedTrimestre === 1 ? '1er' : $selectedTrimestre . 'ème' ?> trimestre</h2>
                </div>

                <!-- Stats rapides -->
                <div class="notes-stats-grid">
                    <div class="notes-stat-card">
                        <div class="notes-stat-value primary"><?= count($notes) ?></div>
                        <div class="notes-stat-label">Notes enregistrées</div>
                    </div>
                    <div class="notes-stat-card">
                        <div class="notes-stat-value success"><?= $moyenneGlobale ?></div>
                        <div class="notes-stat-label">Moyenne globale /20</div>
                    </div>
                    <div class="notes-stat-card">
                        <div class="notes-stat-value info"><?= $nbMatieresEvaluees ?></div>
                        <div class="notes-stat-label">Matières évaluées</div>
                    </div>
                </div>

                <?php if (empty($notes)): ?>
                    <div class="alert alert-info"><i class="fas fa-info-circle"></i> Aucune note pour ce trimestre.</div>
                <?php else: ?>
                <div class="notes-table-wrapper">
                    <table class="notes-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Élève</th>
                                <th>Classe</th>
                                <th>Matière</th>
                                <th>Type</th>
                                <th class="text-center">Note</th>
                                <th>Professeur</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($notes as $n): ?>
                            <tr>
                                <td><?= date('d/m/Y', strtotime($n['date_note'])) ?></td>
                                <td class="font-medium"><?= htmlspecialchars($n['eleve_nom'] ?? '') ?></td>
                                <td class="text-muted"><?= htmlspecialchars($n['classe'] ?? '') ?></td>
                                <td>
                                    <span class="badge-matiere" style="background:<?= htmlspecialchars($n['matiere_couleur'] ?? '#3498db') ?>20; color:<?= htmlspecialchars($n['matiere_couleur'] ?? '#3498db') ?>;">
                                        <?= htmlspecialchars($n['matiere_nom'] ?? '') ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($n['type_evaluation'] ?? '') ?></td>
                                <td class="text-center note-value <?= ($n['note'] / ($n['note_sur'] ?: 20) * 20) >= 10 ? 'note-good' : 'note-bad' ?>">
                                    <?= $n['note'] ?>/<?= $n['note_sur'] ?>
                                </td>
                                <td class="text-muted"><?= htmlspecialchars($n['professeur_nom'] ?? '') ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
                <?php endif; ?>

                <?php if ($totalPages > 1 && $user_role !== 'eleve'): ?>
                <!-- Pagination -->
                <div class="pagination" style="display:flex; justify-content:center; align-items:center; gap:8px; margin-top:24px;">
                    <?php
                    $queryBase = http_build_query(array_filter([
                        'trimestre' => $selectedTrimestre,
                        'classe' => $filterClasse,
                        'matiere' => $filterMatiere ?: null,
                    ]));
                    ?>
                    <?php if ($currentPage > 1): ?>
                    <a href="?<?= $queryBase ?>&page=<?= $currentPage - 1 ?>" class="btn btn-sm btn-secondary">&laquo; Précédent</a>
                    <?php endif; ?>

                    <span style="font-size:14px;color:#4a5568;">
                        Page <?= $currentPage ?> / <?= $totalPages ?> (<?= $totalNotes ?> notes)
                    </span>

                    <?php if ($currentPage < $totalPages): ?>
                    <a href="?<?= $queryBase ?>&page=<?= $currentPage + 1 ?>" class="btn btn-sm btn-secondary">Suivant &raquo;</a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

            </div>

<?php
include __DIR__ . '/../templates/shared_footer.php';
?>
