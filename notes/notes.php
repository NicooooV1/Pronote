<?php
/**
 * Module Notes — Page principale.
 * Affiche les notes par rôle : élève, professeur, admin/vie scolaire.
 * Utilise NoteService pour centraliser les requêtes SQL.
 */

// Inclure l'API centralisée
require_once __DIR__ . '/../API/core.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/NoteService.php';

// Vérifier l'authentification
requireAuth();

// Récupération des informations utilisateur via l'API
$user      = getCurrentUser();
$user_role = getUserRole();
$user_fullname = getUserFullName();
$user_initials = getUserInitials();

$pdo         = getPDO();
$noteService = new NoteService($pdo);

// Récupérer le trimestre sélectionné (par défaut: actuel)
$selectedTrimestre = filter_input(INPUT_GET, 'trimestre', FILTER_VALIDATE_INT);
if (!$selectedTrimestre || $selectedTrimestre < 1 || $selectedTrimestre > 3) {
    $selectedTrimestre = NoteService::getTrimestreCourant();
}

// Charger les données via le service
$notes = [];
$moyennes_par_matiere = [];
$moyenneGenerale = null;
$classStats = null;

// Filtres prof/admin
$filterClasse  = $_GET['classe']  ?? '';
$filterMatiere = (int) ($_GET['matiere'] ?? 0);

try {
    if ($user_role === 'eleve') {
        $notes = $noteService->getNotesEleve($user['id'], $selectedTrimestre);
        $moyennes_par_matiere = $noteService->getMoyennesParMatiere($user['id'], $selectedTrimestre);
        $moyenneGenerale = $noteService->getMoyenneGenerale($user['id'], $selectedTrimestre);
    } elseif ($user_role === 'professeur') {
        $notes = $noteService->getNotesProfesseur($user['id'], $selectedTrimestre);
        // Stats de classe si filtres renseignés
        if ($filterClasse && $filterMatiere) {
            $classStats = $noteService->getStatsClasse($filterClasse, $filterMatiere, $selectedTrimestre);
        }
    } else {
        $notes = $noteService->getAllNotes($selectedTrimestre);
        if ($filterClasse && $filterMatiere) {
            $classStats = $noteService->getStatsClasse($filterClasse, $filterMatiere, $selectedTrimestre);
        }
    }
} catch (PDOException $e) {
    error_log("Erreur notes: " . $e->getMessage());
}

// Données de référence pour les filtres prof/admin
$availableClasses  = [];
$availableMatieres = [];
if ($user_role !== 'eleve') {
    try {
        $availableClasses  = $noteService->getClasses();
        $availableMatieres = $noteService->getMatieres();
    } catch (PDOException $e) {}

    // Filtrer les notes affichées selon les critères sélectionnés
    if (!empty($notes)) {
        if ($filterClasse) {
            $notes = array_filter($notes, function ($n) use ($filterClasse) {
                return ($n['classe'] ?? '') === $filterClasse;
            });
        }
        if ($filterMatiere) {
            $notes = array_filter($notes, function ($n) use ($filterMatiere) {
                return ($n['id_matiere'] ?? 0) == $filterMatiere;
            });
        }
        $notes = array_values($notes);
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

// Contenu sidebar spécifique au module
ob_start();
?>
            <div class="sidebar-nav">
                <a href="notes.php" class="sidebar-nav-item active">
                    <span class="sidebar-nav-icon"><i class="fas fa-list"></i></span>
                    <span>Liste des notes</span>
                </a>
                <?php if (in_array($user_role, ['professeur', 'administrateur'])): ?>
                <a href="form_note.php" class="sidebar-nav-item">
                    <span class="sidebar-nav-icon"><i class="fas fa-plus"></i></span>
                    <span>Ajouter des notes</span>
                </a>
                <?php endif; ?>
            </div>
<?php
$sidebarExtraContent = ob_get_clean();

// Inclusion des templates partagés
include __DIR__ . '/../templates/shared_header.php';
include __DIR__ . '/../templates/shared_sidebar.php';
include __DIR__ . '/../templates/shared_topbar.php';
?>

            <!-- Contenu principal -->
            <div class="content-container">

                <?php if (isAdmin()): ?>
                <div class="admin-toolbar">
                    <span class="admin-toolbar-badge"><i class="fas fa-shield-alt"></i> Administration</span>
                    <span style="font-size:13px;color:#4a5568">Vue complète — <?= count($notes) ?> note(s) affichée(s)</span>
                    <a href="ajouter_note.php" class="btn-sm" style="background:#059669;color:white;text-decoration:none;margin-left:auto"><i class="fas fa-plus"></i> Ajouter une note</a>
                    <a href="../admin/scolaire/notes.php" class="btn-sm" style="background:#0f4c81;color:white;text-decoration:none"><i class="fas fa-cog"></i> Panneau admin</a>
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

                <?php if ($user_role === 'eleve'): ?>
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
                                    <a href="supprimer_note.php?id=<?= $n['id'] ?>" class="btn btn-sm btn-danger" title="Supprimer" onclick="return confirm('Supprimer cette note ?');"><i class="fas fa-trash"></i></a>
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

            </div>

<?php
include __DIR__ . '/../templates/shared_footer.php';
?>
