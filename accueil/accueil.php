<?php
ob_start();

// Inclure l'API centralisée
require_once dirname(__DIR__) . '/API/core.php';
require_once __DIR__ . '/includes/DashboardService.php';

// Authentification via API
requireAuth();

// ─── Données utilisateur ─────────────────────────────────────────
$user          = getCurrentUser();
$user_fullname = getUserFullName();
$user_role     = getUserRole();
$user_initials = getUserInitials();
$classe        = $user['classe'] ?? '';

$aujourdhui = date('d/m/Y');
$trimestre  = function_exists('getTrimestre') ? getTrimestre() : '';
$jours      = ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
$jour       = $jours[date('w')];

// ─── Service métier ──────────────────────────────────────────────
$pdo       = getPDO();
$dashboard = new DashboardService($pdo);

// Cache établissement (REF-4)
$etablissement_data = DashboardService::getEtablissementData();
$nom_etablissement  = $etablissement_data['nom'] ?? 'Établissement Scolaire';

// Greeting contextuel (FEAT-6)
$greeting = DashboardService::getGreeting();

// Modules adaptés au rôle (FEAT-3 + UX-1)
$modules = $dashboard->getModulesForRole($user_role);

// Badge messagerie (FEAT-4)
$unreadCount = $dashboard->getUnreadMessageCount($user['id'] ?? 0, $user_role);

// Résumé par rôle (FEAT-2)
$resume = $dashboard->getResume($user);

// Widgets dynamiques (FEAT-1)
$widgetList = $dashboard->getWidgetsForRole($user_role);

$prochains_evenements = $dashboard->getProchainEvenements($user, 3);
$devoirs_a_faire      = $dashboard->getDevoirsAFaire($user, 3);
$dernieres_notes      = $dashboard->getDernieresNotes($user, 3);
$absences_jour        = ($user_role === 'vie_scolaire') ? $dashboard->getAbsencesDuJour() : [];

// Détermination admin
$isAdmin = $user_role === 'administrateur';

// Configuration des templates partagés
$pageTitle  = 'Tableau de bord';
$activePage = 'accueil';
$extraCss   = ['assets/css/accueil.css'];

include __DIR__ . '/../templates/shared_header.php';
include __DIR__ . '/../templates/shared_sidebar.php';
include __DIR__ . '/../templates/shared_topbar.php';
?>

        <!-- Welcome Banner (FEAT-6 greeting contextuel) -->
        <div class="welcome-banner">
            <div class="welcome-content">
                <h2><?= $greeting ?>, <?= htmlspecialchars($user_fullname) ?></h2>
                <?php if (!empty($classe)): ?>
                <p>Classe de <?= htmlspecialchars($classe) ?></p>
                <?php endif; ?>
                <p class="welcome-date"><?= $jour . ' ' . $aujourdhui ?> - <?= $trimestre ?></p>
            </div>
            <div class="welcome-logo">
                <i class="fas fa-school"></i>
            </div>
        </div>

        <!-- Main Dashboard Content -->
        <div class="dashboard-content">

            <!-- Summary Cards (FEAT-2) -->
            <?php if (!empty($resume)): ?>
            <div class="summary-grid">
                <?php foreach ($resume as $card): ?>
                <div class="summary-card">
                    <div class="summary-icon summary-<?= $card['color'] ?>">
                        <i class="<?= $card['icon'] ?>"></i>
                    </div>
                    <div class="summary-content">
                        <div class="summary-value"><?= htmlspecialchars((string) $card['value']) ?></div>
                        <div class="summary-label"><?= htmlspecialchars($card['label']) ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Modules Grid (FEAT-3 + UX-1 : ordered & adapted to role) -->
            <div class="modules-grid">
                <?php foreach ($modules as $mod): ?>
                <a href="<?= htmlspecialchars($mod['href']) ?>" class="module-card <?= $mod['css'] ?>">
                    <div class="module-icon">
                        <i class="<?= $mod['icon'] ?>"></i>
                        <?php if ($mod['css'] === 'messagerie-card' && $unreadCount > 0): ?>
                            <span class="badge-count"><?= $unreadCount ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="module-info">
                        <h3><?= htmlspecialchars($mod['title']) ?></h3>
                        <p><?= htmlspecialchars($mod['desc']) ?></p>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>

            <!-- Widgets Section (FEAT-1 : dynamic by role) -->
            <div class="widgets-section">
                <?php foreach ($widgetList as $widget): ?>
                    <?php include __DIR__ . '/includes/widgets/widget_' . $widget . '.php'; ?>
                <?php endforeach; ?>
            </div>
        </div>

<?php
include __DIR__ . '/../templates/shared_footer.php';
ob_end_flush();
?>