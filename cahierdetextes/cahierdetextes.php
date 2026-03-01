<?php
/**
 * cahierdetextes.php — Page principale du cahier de textes
 *
 * Liste paginée (UX-1) + calendrier (REF-3) + recherche + stats SQL (PERF-1)
 * Badge « Nouveau » (UX-2) + case « devoir fait » (UX-3)
 * Pièces jointes (PJ-5) affichées dans les cartes
 */
ob_start();

require_once __DIR__ . '/../API/core.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/DevoirService.php';
require_once __DIR__ . '/includes/CalendarRenderer.php';

use API\Services\FileUploadService;

$pdo = getPDO();
requireAuth();

$user          = getCurrentUser();
$user_role     = getUserRole();
$user_fullname = getUserFullName();
$user_initials = getUserInitials();
$service       = new DevoirService($pdo);

// ── AJAX : toggle devoir fait (UX-3) ──
if (isset($_GET['ajax']) && $_GET['ajax'] === 'toggle_fait' && isStudent()) {
    header('Content-Type: application/json');
    $devoirId = intval($_GET['devoir_id'] ?? 0);
    $newState = $service->toggleDevoirFait($user['id'], $devoirId);
    echo json_encode(['fait' => $newState]);
    exit;
}

// ── Paramètres ──
$orderField    = $_GET['order'] ?? 'date_rendu';
$orderDir      = ($_GET['dir'] ?? '') === 'asc' ? 'asc' : 'desc';
$displayMode   = $_GET['mode']   ?? 'list';
$page          = max(1, intval($_GET['page'] ?? 1));
$perPage       = 20;
$search        = isset($_GET['q']) && $_GET['q'] !== '' ? trim($_GET['q']) : null;
$filterClasse  = $_GET['classe']  ?? '';
$filterMatiere = $_GET['matiere'] ?? '';

$filters = [];
if ($filterClasse)  $filters['classe']  = $filterClasse;
if ($filterMatiere) $filters['matiere'] = $filterMatiere;

// ── Données ──
try {
    $stats        = $service->getStatsSql($user_role, $user['id'], $user_fullname, $filters, $search);
    $filterOpts   = $service->getFilterOptions($user_role, $user['id'], $user_fullname);
    $query        = $service->buildQuery($user_role, $user['id'], $user_fullname, $filters, $orderField, $orderDir, $page, $perPage, $search);
    [$devoirs, $totalDevoirs] = $service->executeQuery($query);
    $totalPages   = max(1, (int) ceil($totalDevoirs / $perPage));

    // Pour le calendrier, on charge TOUS les devoirs du mois (pas paginé)
    if ($displayMode === 'calendar') {
        $allQuery = $service->buildQuery($user_role, $user['id'], $user_fullname, $filters, $orderField, $orderDir, 1, 9999, $search);
        [$allDevoirs] = $service->executeQuery($allQuery);
    }

    // IDs devoirs faits (élève)
    $devoirsFaits = isStudent() ? $service->getDevoirsFaitsIds($user['id']) : [];

} catch (\PDOException $e) {
    logError("Erreur cahierdetextes.php: " . $e->getMessage());
    $devoirs = []; $totalDevoirs = 0; $totalPages = 1;
    $stats = ['total' => 0, 'urgent' => 0, 'soon' => 0, 'expired' => 0];
    $filterOpts = ['classes' => [], 'matieres' => [], 'professeurs' => []];
    $devoirsFaits = [];
}

// ── Variables template ──
$pageTitle   = "Cahier de textes";
$activePage  = 'cahierdetextes';
$isAdmin     = $user_role === 'administrateur';
$extraCss    = ['assets/css/cahierdetextes.css'];
$extraJs     = ['assets/js/cahierdetextes.js'];

// Construire query string pour les liens (conserver les filtres)
$qs = http_build_query(array_filter([
    'mode' => $displayMode !== 'list' ? $displayMode : null,
    'q'    => $search,
    'classe'  => $filterClasse  ?: null,
    'matiere' => $filterMatiere ?: null,
]));

// ── Sidebar ──
ob_start();
?>
        <div class="sidebar-nav">
            <a href="cahierdetextes.php" class="sidebar-nav-item">
                <span class="sidebar-nav-icon"><i class="fas fa-list"></i></span>
                <span>Liste des devoirs</span>
            </a>
            <?php if (canManageDevoirs()): ?>
            <a href="form_devoir.php" class="sidebar-nav-item">
                <span class="sidebar-nav-icon"><i class="fas fa-plus"></i></span>
                <span>Ajouter un devoir</span>
            </a>
            <?php endif; ?>
        </div>
        <div class="sidebar-section">
            <div class="sidebar-section-header">Filtres</div>
            <div class="sidebar-nav">
                <a href="#" class="sidebar-nav-item filter-link" data-filter="urgent">
                    <span class="sidebar-nav-icon"><i class="fas fa-exclamation-circle"></i></span>
                    <span>Urgents (&lt; 3 jours)</span>
                </a>
                <a href="#" class="sidebar-nav-item filter-link" data-filter="soon">
                    <span class="sidebar-nav-icon"><i class="fas fa-clock"></i></span>
                    <span>Cette semaine</span>
                </a>
                <a href="#" class="sidebar-nav-item filter-link" data-filter="all">
                    <span class="sidebar-nav-icon"><i class="fas fa-list"></i></span>
                    <span>Tous</span>
                </a>
            </div>
        </div>
<?php
$sidebarExtraContent = ob_get_clean();

// Topbar actions
ob_start();
?>
                <div class="view-toggle" style="display:flex;gap:5px;">
                    <a href="?mode=list<?= $qs ? '&' . $qs : '' ?>" class="btn btn-sm <?= $displayMode !== 'calendar' ? 'btn-primary' : 'btn-secondary' ?>">
                        <i class="fas fa-list"></i> Liste
                    </a>
                    <a href="?mode=calendar<?= $qs ? '&' . $qs : '' ?>" class="btn btn-sm <?= $displayMode === 'calendar' ? 'btn-primary' : 'btn-secondary' ?>">
                        <i class="fas fa-calendar-alt"></i> Calendrier
                    </a>
                </div>
<?php
$headerExtraActions = ob_get_clean();

include __DIR__ . '/../templates/shared_header.php';
include __DIR__ . '/../templates/shared_sidebar.php';
include __DIR__ . '/../templates/shared_topbar.php';
?>

        <!-- Welcome Banner -->
        <div class="welcome-banner">
            <div class="welcome-content">
                <h2>Cahier de textes</h2>
                <p>Consultez et gérez les devoirs à faire</p>
            </div>
            <div class="welcome-logo"><i class="fas fa-book"></i></div>
        </div>

        <div class="dashboard-content">

            <!-- Stats (PERF-1 : SQL) -->
            <div class="devoirs-dashboard">
                <div class="summary-card total-summary">
                    <div class="summary-icon"><i class="fas fa-book"></i></div>
                    <div class="summary-content">
                        <div class="summary-value"><?= $stats['total'] ?></div>
                        <div class="summary-label">Total des devoirs</div>
                    </div>
                </div>
                <div class="summary-card urgent-summary">
                    <div class="summary-icon"><i class="fas fa-exclamation-circle"></i></div>
                    <div class="summary-content">
                        <div class="summary-value"><?= $stats['urgent'] ?></div>
                        <div class="summary-label">Urgents (&lt; 3 jours)</div>
                    </div>
                </div>
                <div class="summary-card soon-summary">
                    <div class="summary-icon"><i class="fas fa-clock"></i></div>
                    <div class="summary-content">
                        <div class="summary-value"><?= $stats['soon'] ?></div>
                        <div class="summary-label">Cette semaine</div>
                    </div>
                </div>
            </div>

            <!-- Barre recherche + filtres (UX-1) -->
            <div class="filter-toolbar">
                <form method="get" class="search-bar" style="display:flex;gap:8px;flex:1;max-width:400px;">
                    <?php if ($displayMode === 'calendar'): ?><input type="hidden" name="mode" value="calendar"><?php endif; ?>
                    <input type="text" name="q" class="form-control" placeholder="Rechercher un devoir…"
                           value="<?= htmlspecialchars($search ?? '') ?>">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
                    <?php if ($search): ?>
                        <a href="?<?= $displayMode === 'calendar' ? 'mode=calendar&' : '' ?>order=<?= htmlspecialchars($orderField) ?>"
                           class="btn btn-secondary" title="Effacer"><i class="fas fa-times"></i></a>
                    <?php endif; ?>
                </form>

                <div class="filter-buttons">
                    <?php
                    $orderLinks = [
                        'date_rendu'  => ['icon' => 'fa-calendar-day', 'label' => 'Date de rendu'],
                        'date_ajout'  => ['icon' => 'fa-clock',        'label' => 'Date d\'ajout'],
                        'nom_matiere' => ['icon' => 'fa-book',         'label' => 'Matière'],
                    ];
                    if (!isStudent() && !isParent()) {
                        $orderLinks['classe'] = ['icon' => 'fa-users', 'label' => 'Classe'];
                    }
                    foreach ($orderLinks as $field => $info): ?>
                        <a href="?order=<?= $field ?><?= $qs ? '&' . $qs : '' ?>"
                           class="btn <?= $orderField === $field ? 'btn-primary' : 'btn-secondary' ?>">
                            <i class="fas <?= $info['icon'] ?>"></i> <?= htmlspecialchars($info['label']) ?>
                        </a>
                    <?php endforeach; ?>
                </div>

                <?php if (canManageDevoirs()): ?>
                <a href="form_devoir.php" class="btn btn-primary"><i class="fas fa-plus"></i> Ajouter</a>
                <?php endif; ?>
            </div>

            <!-- Filtres dropdown (classe / matière) -->
            <?php if (count($filterOpts['classes']) > 1 || count($filterOpts['matieres']) > 1): ?>
            <div class="filter-toolbar" style="padding:10px 20px;">
                <form method="get" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                    <input type="hidden" name="order" value="<?= htmlspecialchars($orderField) ?>">
                    <?php if ($displayMode === 'calendar'): ?><input type="hidden" name="mode" value="calendar"><?php endif; ?>
                    <?php if ($search): ?><input type="hidden" name="q" value="<?= htmlspecialchars($search) ?>"><?php endif; ?>
                    <select name="classe" class="form-select" style="max-width:180px;" onchange="this.form.submit()">
                        <option value="">Toutes les classes</option>
                        <?php foreach ($filterOpts['classes'] as $c): ?>
                            <option value="<?= htmlspecialchars($c, ENT_QUOTES) ?>" <?= $filterClasse === $c ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <select name="matiere" class="form-select" style="max-width:200px;" onchange="this.form.submit()">
                        <option value="">Toutes les matières</option>
                        <?php foreach ($filterOpts['matieres'] as $m): ?>
                            <option value="<?= htmlspecialchars($m, ENT_QUOTES) ?>" <?= $filterMatiere === $m ? 'selected' : '' ?>>
                                <?= htmlspecialchars($m) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($filterClasse || $filterMatiere): ?>
                        <a href="?order=<?= htmlspecialchars($orderField) ?><?= $displayMode === 'calendar' ? '&mode=calendar' : '' ?>"
                           class="btn btn-sm btn-secondary">Réinitialiser</a>
                    <?php endif; ?>
                </form>
            </div>
            <?php endif; ?>

            <!-- Messages flash -->
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert-banner alert-success"><i class="fas fa-check-circle"></i><div><?= htmlspecialchars($_SESSION['success_message']) ?></div><button class="alert-close">&times;</button></div>
                <?php unset($_SESSION['success_message']); ?>
            <?php endif; ?>
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert-banner alert-error"><i class="fas fa-exclamation-circle"></i><div><?= htmlspecialchars($_SESSION['error_message']) ?></div><button class="alert-close">&times;</button></div>
                <?php unset($_SESSION['error_message']); ?>
            <?php endif; ?>

            <!-- ════════ VUE LISTE ════════ -->
            <?php if ($displayMode !== 'calendar'): ?>
                <?php if (empty($devoirs)): ?>
                    <div class="alert-banner alert-info"><i class="fas fa-info-circle"></i><div>Aucun devoir trouvé.</div></div>
                <?php else: ?>
                    <div class="devoirs-list">
                        <?php foreach ($devoirs as $devoir):
                            $status   = $service->computeStatus($devoir['date_rendu']);
                            $isNew    = $service->isNew($devoir);
                            $isFait   = in_array($devoir['id'], $devoirsFaits);
                            $fichiers = $service->getFichiers($devoir['id']);
                        ?>
                        <div class="devoir-card <?= $status['class'] ?> <?= $isFait ? 'devoir-fait' : '' ?>" data-date="<?= $devoir['date_rendu'] ?>">
                            <div class="card-header">
                                <div class="devoir-title">
                                    <i class="fas <?= $status['icon'] ?>"></i>
                                    <?= htmlspecialchars($devoir['titre']) ?>
                                    <?php if ($status['class']): ?>
                                        <span class="badge badge-<?= $status['class'] ?>"><i class="fas <?= $status['icon'] ?>"></i><?= $status['label'] ?></span>
                                    <?php endif; ?>
                                    <?php if ($isNew): ?>
                                        <span class="badge badge-new"><i class="fas fa-sparkles"></i>Nouveau</span>
                                    <?php endif; ?>
                                </div>
                                <div class="devoir-meta">
                                    <span>Ajouté le: <?= date('d/m/Y', strtotime($devoir['date_ajout'])) ?></span>
                                </div>
                            </div>

                            <div class="card-body">
                                <div class="devoir-info-grid">
                                    <div class="devoir-info"><div class="info-label">Classe:</div><div class="info-value"><?= htmlspecialchars($devoir['classe']) ?></div></div>
                                    <div class="devoir-info"><div class="info-label">Matière:</div><div class="info-value"><?= htmlspecialchars($devoir['nom_matiere']) ?></div></div>
                                    <div class="devoir-info"><div class="info-label">Professeur:</div><div class="info-value"><?= htmlspecialchars($devoir['nom_professeur']) ?></div></div>
                                    <div class="devoir-info"><div class="info-label">À rendre pour le:</div><div class="info-value date-rendu <?= $status['class'] ?>"><?= date('d/m/Y', strtotime($devoir['date_rendu'])) ?></div></div>
                                </div>

                                <div class="devoir-description">
                                    <h4>Description:</h4>
                                    <p><?= nl2br(htmlspecialchars($devoir['description'])) ?></p>
                                </div>

                                <!-- Pièces jointes (PJ-5) -->
                                <?php if (!empty($fichiers)): ?>
                                <div class="fichiers-list">
                                    <h4><i class="fas fa-paperclip"></i> Pièces jointes</h4>
                                    <?php foreach ($fichiers as $f): ?>
                                        <a href="telecharger.php?id=<?= $f['id'] ?>" class="fichier-item">
                                            <i class="fas fa-<?= FileUploadService::getFileIcon($f['type_mime']) ?>"></i>
                                            <span><?= htmlspecialchars($f['nom_original']) ?></span>
                                            <span class="fichier-taille"><?= FileUploadService::formatBytes($f['taille']) ?></span>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>

                                <div class="card-actions">
                                    <?php if (isStudent()): ?>
                                        <button class="btn <?= $isFait ? 'btn-success' : 'btn-secondary' ?> devoir-fait-toggle"
                                                data-devoir-id="<?= $devoir['id'] ?>">
                                            <i class="<?= $isFait ? 'fas fa-check-circle' : 'far fa-circle' ?>"></i>
                                            <?= $isFait ? 'Fait' : 'À faire' ?>
                                        </button>
                                    <?php endif; ?>
                                    <?php if (canManageDevoirs() && $service->canUserEdit($devoir, $user_fullname, $user_role)): ?>
                                        <a href="form_devoir.php?id=<?= $devoir['id'] ?>" class="btn btn-secondary">
                                            <i class="fas fa-edit"></i> Modifier
                                        </a>
                                        <a href="supprimer_devoir.php?id=<?= $devoir['id'] ?>" class="btn btn-danger"
                                           onclick="return confirm('Supprimer ce devoir ?');">
                                            <i class="fas fa-trash"></i> Supprimer
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Pagination (UX-1) -->
                    <?php if ($totalPages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?= $page - 1 ?>&order=<?= htmlspecialchars($orderField) ?><?= $qs ? '&' . $qs : '' ?>" class="btn btn-secondary">&laquo; Précédent</a>
                        <?php endif; ?>
                        <span class="pagination-info">Page <?= $page ?> / <?= $totalPages ?> (<?= $totalDevoirs ?> devoirs)</span>
                        <?php if ($page < $totalPages): ?>
                            <a href="?page=<?= $page + 1 ?>&order=<?= htmlspecialchars($orderField) ?><?= $qs ? '&' . $qs : '' ?>" class="btn btn-secondary">Suivant &raquo;</a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>

            <!-- ════════ VUE CALENDRIER ════════ -->
            <?php else: ?>
                <?php
                $calMonth = isset($_GET['month']) ? (int) $_GET['month'] : null;
                $calYear  = isset($_GET['year'])  ? (int) $_GET['year']  : null;
                $calendar = new CalendarRenderer($allDevoirs ?? [], $calMonth, $calYear, $orderField);
                echo $calendar->render([$service, 'computeStatus']);
                ?>
            <?php endif; ?>

        </div>

<?php
include __DIR__ . '/../templates/shared_footer.php';
ob_end_flush();
?>
