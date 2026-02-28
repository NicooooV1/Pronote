<?php
<?php
/**
 * Sidebar partagée unique — Pronote
 * Inclus sur TOUTES les pages, y compris /admin/
 * Le contenu s'adapte au rôle de l'utilisateur connecté.
 */

// L'API doit être chargée avant l'inclusion de ce fichier
$currentUser = getCurrentUser();
$userRole = $currentUser['role'] ?? ($currentUser['profil'] ?? '');
$isAdmin = ($userRole === 'administrateur');

// Déterminer la page active pour le highlight
$activePage = $activePage ?? '';
?>
<div class="sidebar">
    <!-- Logo -->
    <a href="/accueil/accueil.php" class="logo-container">
        <div class="app-logo">P</div>
        <div class="app-title">PRONOTE</div>
    </a>

    <!-- ===== SECTION 1 : Navigation principale (tous les rôles) ===== -->
    <div class="sidebar-section">
        <div class="sidebar-section-header">NAVIGATION</div>
        <div class="sidebar-nav">
            <a href="/accueil/accueil.php" 
               class="sidebar-nav-item <?= $activePage === 'accueil' ? 'active' : '' ?>">
                <span class="sidebar-nav-icon"><i class="fas fa-home"></i></span>
                <span>Accueil</span>
            </a>
            <a href="/notes/notes.php" 
               class="sidebar-nav-item <?= $activePage === 'notes' ? 'active' : '' ?>">
                <span class="sidebar-nav-icon"><i class="fas fa-chart-bar"></i></span>
                <span>Notes</span>
            </a>
            <a href="/agenda/agenda.php" 
               class="sidebar-nav-item <?= $activePage === 'agenda' ? 'active' : '' ?>">
                <span class="sidebar-nav-icon"><i class="fas fa-calendar-alt"></i></span>
                <span>Agenda</span>
            </a>
            <a href="/cahierdetextes/cahierdetextes.php" 
               class="sidebar-nav-item <?= $activePage === 'cahier' ? 'active' : '' ?>">
                <span class="sidebar-nav-icon"><i class="fas fa-book"></i></span>
                <span>Cahier de textes</span>
            </a>
            <a href="/messagerie/index.php" 
               class="sidebar-nav-item <?= $activePage === 'messagerie' ? 'active' : '' ?>">
                <span class="sidebar-nav-icon"><i class="fas fa-envelope"></i></span>
                <span>Messagerie</span>
            </a>
            <a href="/absences/absences.php" 
               class="sidebar-nav-item <?= $activePage === 'absences' ? 'active' : '' ?>">
                <span class="sidebar-nav-icon"><i class="fas fa-calendar-times"></i></span>
                <span>Absences</span>
            </a>
        </div>
    </div>

    <!-- ===== SECTION 2 : Administration (admin seulement) ===== -->
    <?php if ($isAdmin): ?>
    <?php
        // Charger les compteurs de badges pour les alertes admin
        $adminBadgeCount = 0;
        try {
            $pdo = getPDO();
            $stmt = $pdo->query("SELECT COUNT(*) FROM demandes_reinitialisation WHERE status = 'pending'");
            $adminBadgeCount += (int)$stmt->fetchColumn();
            $stmt = $pdo->query("SELECT COUNT(*) FROM justificatifs WHERE traite = 0");
            $adminBadgeCount += (int)$stmt->fetchColumn();
        } catch (Exception $e) { /* silently fail */ }
    ?>
    <div class="sidebar-section">
        <div class="sidebar-section-header">ADMINISTRATION</div>
        <div class="sidebar-nav">
            <a href="/admin/dashboard.php" 
               class="sidebar-nav-item <?= $activePage === 'admin' ? 'active' : '' ?>">
                <span class="sidebar-nav-icon"><i class="fas fa-cogs"></i></span>
                <span>Panneau d'administration</span>
                <?php if ($adminBadgeCount > 0): ?>
                    <span class="sidebar-badge"><?= $adminBadgeCount ?></span>
                <?php endif; ?>
            </a>
        </div>
    </div>
    <?php endif; ?>

    <!-- ===== SECTION 3 : Contenu spécifique au module (optionnel) ===== -->
    <?php if (!empty($sidebarExtraContent)): ?>
        <?= $sidebarExtraContent ?>
    <?php endif; ?>

    <!-- ===== SECTION 4 : Compte utilisateur ===== -->
    <div class="sidebar-section" style="margin-top: auto;">
        <div class="sidebar-section-header">COMPTE</div>
        <div class="sidebar-nav">
            <a href="/profil/profil.php" 
               class="sidebar-nav-item <?= $activePage === 'profil' ? 'active' : '' ?>">
                <span class="sidebar-nav-icon"><i class="fas fa-user"></i></span>
                <span>Mon profil</span>
            </a>
            <a href="/login/public/logout.php" class="sidebar-nav-item sidebar-nav-item--logout">
                <span class="sidebar-nav-icon"><i class="fas fa-sign-out-alt"></i></span>
                <span>Déconnexion</span>
            </a>
        </div>
    </div>

    <!-- Footer sidebar -->
    <div class="sidebar-footer">
        <span>© <?= date('Y') ?> Pronote</span>
    </div>
</div>
$user_role = $user_role ?? '';
$sidebarExtraContent = $sidebarExtraContent ?? '';
$currentPage = $currentPage ?? '';

// Préfixe racine
$rootPrefix = $rootPrefix ?? '../';

// Récupérer les informations contextuelles pour la sidebar
$_sb_user = getCurrentUser();
$_sb_etablissement_file = dirname(__DIR__) . '/login/data/etablissement.json';
$_sb_etablissement_data = file_exists($_sb_etablissement_file) ? json_decode(file_get_contents($_sb_etablissement_file), true) : [];
$_sb_nom_etablissement = $_sb_etablissement_data['nom'] ?? 'Établissement Scolaire';
$_sb_trimestre = function_exists('getTrimestre') ? getTrimestre() : '';
$_sb_date = date('d/m/Y');
$_sb_user_fullname = getUserFullName();
$_sb_user_role = getUserRole();
$_sb_profil_labels = [
    'administrateur' => 'Administrateur',
    'professeur' => 'Professeur',
    'eleve' => 'Élève',
    'parent' => 'Parent',
    'vie_scolaire' => 'Vie scolaire',
];
$_sb_profil_label = $_sb_profil_labels[$_sb_user_role] ?? ucfirst($_sb_user_role);
?>

<!-- Sidebar -->
<div class="sidebar">
    <a href="<?= $rootPrefix ?>accueil/accueil.php" class="logo-container">
        <div class="app-logo">F</div>
        <div class="app-title">FRONOTE</div>
    </a>

    <!-- Navigation -->
    <div class="sidebar-section" data-section="nav">
        <div class="sidebar-section-header sidebar-collapsible" data-target="nav">
            <span>Navigation</span>
            <i class="fas fa-chevron-down sidebar-chevron"></i>
        </div>
        <div class="sidebar-section-body" id="section-nav">
            <div class="sidebar-nav">
                <a href="<?= $rootPrefix ?>accueil/accueil.php" class="sidebar-nav-item <?= $activePage === 'accueil' ? 'active' : '' ?>">
                    <span class="sidebar-nav-icon"><i class="fas fa-home"></i></span>
                    <span>Accueil</span>
                </a>
                <a href="<?= $rootPrefix ?>notes/notes.php" class="sidebar-nav-item <?= $activePage === 'notes' ? 'active' : '' ?>">
                    <span class="sidebar-nav-icon"><i class="fas fa-chart-bar"></i></span>
                    <span>Notes</span>
                </a>
                <a href="<?= $rootPrefix ?>agenda/agenda.php" class="sidebar-nav-item <?= $activePage === 'agenda' ? 'active' : '' ?>">
                    <span class="sidebar-nav-icon"><i class="fas fa-calendar"></i></span>
                    <span>Agenda</span>
                </a>
                <a href="<?= $rootPrefix ?>cahierdetextes/cahierdetextes.php" class="sidebar-nav-item <?= $activePage === 'cahierdetextes' ? 'active' : '' ?>">
                    <span class="sidebar-nav-icon"><i class="fas fa-book"></i></span>
                    <span>Cahier de textes</span>
                </a>
                <a href="<?= $rootPrefix ?>messagerie/index.php" class="sidebar-nav-item <?= $activePage === 'messagerie' ? 'active' : '' ?>">
                    <span class="sidebar-nav-icon"><i class="fas fa-envelope"></i></span>
                    <span>Messagerie</span>
                </a>
                <a href="<?= $rootPrefix ?>absences/absences.php" class="sidebar-nav-item <?= $activePage === 'absences' ? 'active' : '' ?>">
                    <span class="sidebar-nav-icon"><i class="fas fa-calendar-times"></i></span>
                    <span>Absences</span>
                </a>
            </div>
        </div>
    </div>

    <?php
    // Module-specific actions (calendrier agenda, ajout de notes, etc.)
    if (!empty($sidebarExtraContent)):
    ?>
    <div class="sidebar-section" data-section="module">
        <div class="sidebar-section-header sidebar-collapsible" data-target="module">
            <span>Actions</span>
            <i class="fas fa-chevron-down sidebar-chevron"></i>
        </div>
        <div class="sidebar-section-body" id="section-module">
            <?= $sidebarExtraContent ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Informations contextuelles -->
    <div class="sidebar-section" data-section="info">
        <div class="sidebar-section-header sidebar-collapsible" data-target="info">
            <span>Informations</span>
            <i class="fas fa-chevron-down sidebar-chevron"></i>
        </div>
        <div class="sidebar-section-body" id="section-info">
            <div class="sidebar-info-list">
                <div class="info-item">
                    <div class="info-label"><i class="fas fa-school"></i> Établissement</div>
                    <div class="info-value"><?= htmlspecialchars($_sb_nom_etablissement) ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label"><i class="fas fa-calendar-day"></i> Date</div>
                    <div class="info-value"><?= $_sb_date ?></div>
                </div>
                <?php if (!empty($_sb_trimestre)): ?>
                <div class="info-item">
                    <div class="info-label"><i class="fas fa-clock"></i> Période</div>
                    <div class="info-value"><?= htmlspecialchars($_sb_trimestre) ?></div>
                </div>
                <?php endif; ?>
                <div class="info-item">
                    <div class="info-label"><i class="fas fa-user"></i> Utilisateur</div>
                    <div class="info-value"><?= htmlspecialchars($_sb_user_fullname) ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label"><i class="fas fa-id-badge"></i> Profil</div>
                    <div class="info-value"><?= htmlspecialchars($_sb_profil_label) ?></div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($isAdmin): ?>
    <?php
    // Compteurs pour les badges admin
    $_admin_badges = ['passwords' => 0, 'justificatifs' => 0];
    try {
        $_admin_pdo = getPDO();
        $stmt = $_admin_pdo->query("SELECT COUNT(*) FROM demandes_reinitialisation WHERE status = 'pending'");
        $_admin_badges['passwords'] = (int)$stmt->fetchColumn();
        $stmt = $_admin_pdo->query("SELECT COUNT(*) FROM justificatifs WHERE traite = 0");
        $_admin_badges['justificatifs'] = (int)$stmt->fetchColumn();
    } catch (Exception $e) { /* silently ignore */ }
    $_adminBase = $rootPrefix . 'admin/';
    ?>

    <!-- Tableau de bord -->
    <div class="sidebar-section" data-section="admin-dashboard">
        <div class="sidebar-section-header sidebar-collapsible" data-target="admin-dashboard">
            <span>Tableau de bord</span>
            <i class="fas fa-chevron-down sidebar-chevron"></i>
        </div>
        <div class="sidebar-section-body" id="section-admin-dashboard">
            <div class="sidebar-nav">
                <a href="<?= $_adminBase ?>dashboard.php" class="sidebar-nav-item <?= $currentPage === 'dashboard' ? 'active' : '' ?>">
                    <span class="sidebar-nav-icon"><i class="fas fa-tachometer-alt"></i></span>
                    <span>Vue d'ensemble</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Gestion des utilisateurs -->
    <div class="sidebar-section" data-section="admin-users">
        <div class="sidebar-section-header sidebar-collapsible" data-target="admin-users">
            <span>Utilisateurs</span>
            <i class="fas fa-chevron-down sidebar-chevron"></i>
        </div>
        <div class="sidebar-section-body" id="section-admin-users">
            <div class="sidebar-nav">
                <a href="<?= $_adminBase ?>users/index.php" class="sidebar-nav-item <?= $currentPage === 'users' ? 'active' : '' ?>">
                    <span class="sidebar-nav-icon"><i class="fas fa-users"></i></span>
                    <span>Tous les utilisateurs</span>
                </a>
                <a href="<?= $_adminBase ?>users/create.php" class="sidebar-nav-item <?= $currentPage === 'users_create' ? 'active' : '' ?>">
                    <span class="sidebar-nav-icon"><i class="fas fa-user-plus"></i></span>
                    <span>Ajouter un utilisateur</span>
                </a>
                <a href="<?= $_adminBase ?>users/admins.php" class="sidebar-nav-item <?= $currentPage === 'admins' ? 'active' : '' ?>">
                    <span class="sidebar-nav-icon"><i class="fas fa-user-shield"></i></span>
                    <span>Administrateurs</span>
                </a>
                <a href="<?= $_adminBase ?>users/passwords.php" class="sidebar-nav-item <?= $currentPage === 'passwords' ? 'active' : '' ?>">
                    <span class="sidebar-nav-icon"><i class="fas fa-key"></i></span>
                    <span>Mots de passe</span>
                    <?php if ($_admin_badges['passwords'] > 0): ?>
                        <span class="sidebar-badge"><?= $_admin_badges['passwords'] ?></span>
                    <?php endif; ?>
                </a>
                <a href="<?= $_adminBase ?>users/sessions.php" class="sidebar-nav-item <?= $currentPage === 'sessions' ? 'active' : '' ?>">
                    <span class="sidebar-nav-icon"><i class="fas fa-desktop"></i></span>
                    <span>Sessions actives</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Vie scolaire -->
    <div class="sidebar-section" data-section="admin-scolaire">
        <div class="sidebar-section-header sidebar-collapsible" data-target="admin-scolaire">
            <span>Vie scolaire</span>
            <i class="fas fa-chevron-down sidebar-chevron"></i>
        </div>
        <div class="sidebar-section-body" id="section-admin-scolaire">
            <div class="sidebar-nav">
                <a href="<?= $_adminBase ?>scolaire/notes.php" class="sidebar-nav-item <?= $currentPage === 'notes' ? 'active' : '' ?>">
                    <span class="sidebar-nav-icon"><i class="fas fa-graduation-cap"></i></span>
                    <span>Notes & Évaluations</span>
                </a>
                <a href="<?= $_adminBase ?>scolaire/absences.php" class="sidebar-nav-item <?= $currentPage === 'absences' ? 'active' : '' ?>">
                    <span class="sidebar-nav-icon"><i class="fas fa-calendar-times"></i></span>
                    <span>Absences & Retards</span>
                </a>
                <a href="<?= $_adminBase ?>scolaire/justificatifs.php" class="sidebar-nav-item <?= $currentPage === 'justificatifs' ? 'active' : '' ?>">
                    <span class="sidebar-nav-icon"><i class="fas fa-file-medical"></i></span>
                    <span>Justificatifs</span>
                    <?php if ($_admin_badges['justificatifs'] > 0): ?>
                        <span class="sidebar-badge"><?= $_admin_badges['justificatifs'] ?></span>
                    <?php endif; ?>
                </a>
                <a href="<?= $_adminBase ?>scolaire/devoirs.php" class="sidebar-nav-item <?= $currentPage === 'devoirs' ? 'active' : '' ?>">
                    <span class="sidebar-nav-icon"><i class="fas fa-book"></i></span>
                    <span>Devoirs</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Classes & Enseignement -->
    <div class="sidebar-section" data-section="admin-classes">
        <div class="sidebar-section-header sidebar-collapsible" data-target="admin-classes">
            <span>Classes & Enseignement</span>
            <i class="fas fa-chevron-down sidebar-chevron"></i>
        </div>
        <div class="sidebar-section-body" id="section-admin-classes">
            <div class="sidebar-nav">
                <a href="<?= $_adminBase ?>classes/index.php" class="sidebar-nav-item <?= $currentPage === 'classes' ? 'active' : '' ?>">
                    <span class="sidebar-nav-icon"><i class="fas fa-chalkboard"></i></span>
                    <span>Gestion des classes</span>
                </a>
                <a href="<?= $_adminBase ?>classes/affectations.php" class="sidebar-nav-item <?= $currentPage === 'affectations' ? 'active' : '' ?>">
                    <span class="sidebar-nav-icon"><i class="fas fa-project-diagram"></i></span>
                    <span>Affectations professeurs</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Messagerie -->
    <div class="sidebar-section" data-section="admin-messagerie">
        <div class="sidebar-section-header sidebar-collapsible" data-target="admin-messagerie">
            <span>Messagerie</span>
            <i class="fas fa-chevron-down sidebar-chevron"></i>
        </div>
        <div class="sidebar-section-body" id="section-admin-messagerie">
            <div class="sidebar-nav">
                <a href="<?= $_adminBase ?>messagerie/moderation.php" class="sidebar-nav-item <?= $currentPage === 'moderation' ? 'active' : '' ?>">
                    <span class="sidebar-nav-icon"><i class="fas fa-shield-alt"></i></span>
                    <span>Modération</span>
                </a>
                <a href="<?= $_adminBase ?>messagerie/conversations.php" class="sidebar-nav-item <?= $currentPage === 'conversations' ? 'active' : '' ?>">
                    <span class="sidebar-nav-icon"><i class="fas fa-comments"></i></span>
                    <span>Conversations</span>
                </a>
                <a href="<?= $_adminBase ?>messagerie/annonces.php" class="sidebar-nav-item <?= $currentPage === 'annonces' ? 'active' : '' ?>">
                    <span class="sidebar-nav-icon"><i class="fas fa-bullhorn"></i></span>
                    <span>Annonces globales</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Établissement -->
    <div class="sidebar-section" data-section="admin-etab">
        <div class="sidebar-section-header sidebar-collapsible" data-target="admin-etab">
            <span>Établissement</span>
            <i class="fas fa-chevron-down sidebar-chevron"></i>
        </div>
        <div class="sidebar-section-body" id="section-admin-etab">
            <div class="sidebar-nav">
                <a href="<?= $_adminBase ?>etablissement/info.php" class="sidebar-nav-item <?= $currentPage === 'etab_info' ? 'active' : '' ?>">
                    <span class="sidebar-nav-icon"><i class="fas fa-school"></i></span>
                    <span>Informations</span>
                </a>
                <a href="<?= $_adminBase ?>etablissement/matieres.php" class="sidebar-nav-item <?= $currentPage === 'matieres' ? 'active' : '' ?>">
                    <span class="sidebar-nav-icon"><i class="fas fa-palette"></i></span>
                    <span>Matières & Coefficients</span>
                </a>
                <a href="<?= $_adminBase ?>etablissement/periodes.php" class="sidebar-nav-item <?= $currentPage === 'periodes' ? 'active' : '' ?>">
                    <span class="sidebar-nav-icon"><i class="fas fa-calendar-alt"></i></span>
                    <span>Périodes scolaires</span>
                </a>
                <a href="<?= $_adminBase ?>etablissement/evenements.php" class="sidebar-nav-item <?= $currentPage === 'evenements' ? 'active' : '' ?>">
                    <span class="sidebar-nav-icon"><i class="fas fa-calendar-check"></i></span>
                    <span>Événements</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Système -->
    <div class="sidebar-section" data-section="admin-systeme">
        <div class="sidebar-section-header sidebar-collapsible" data-target="admin-systeme">
            <span>Système</span>
            <i class="fas fa-chevron-down sidebar-chevron"></i>
        </div>
        <div class="sidebar-section-body" id="section-admin-systeme">
            <div class="sidebar-nav">
                <a href="<?= $_adminBase ?>systeme/audit.php" class="sidebar-nav-item <?= $currentPage === 'audit' ? 'active' : '' ?>">
                    <span class="sidebar-nav-icon"><i class="fas fa-history"></i></span>
                    <span>Journal d'audit</span>
                </a>
                <a href="<?= $_adminBase ?>systeme/stats.php" class="sidebar-nav-item <?= $currentPage === 'stats' ? 'active' : '' ?>">
                    <span class="sidebar-nav-icon"><i class="fas fa-chart-bar"></i></span>
                    <span>Statistiques</span>
                </a>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="sidebar-footer">
        &copy; <?= date('Y') ?> FRONOTE
    </div>
</div>

<script>
(function() {
    // Collapsible sidebar sections — persisted per user via localStorage
    const STORAGE_KEY = 'fronote_sidebar_state';

    function getSavedState() {
        try { return JSON.parse(localStorage.getItem(STORAGE_KEY)) || {}; } catch(e) { return {}; }
    }

    function saveState(state) {
        try { localStorage.setItem(STORAGE_KEY, JSON.stringify(state)); } catch(e) {}
    }

    const state = getSavedState();

    document.querySelectorAll('.sidebar-collapsible').forEach(function(header) {
        const target = header.getAttribute('data-target');
        const body = document.getElementById('section-' + target);
        if (!body) return;

        // Apply saved state (default: expanded)
        if (state[target] === false) {
            body.classList.add('collapsed');
            header.classList.add('collapsed');
        }

        header.addEventListener('click', function() {
            const isCollapsed = body.classList.toggle('collapsed');
            header.classList.toggle('collapsed', isCollapsed);
            state[target] = !isCollapsed;
            saveState(state);
        });
    });
})();
</script>
