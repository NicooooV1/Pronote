<?php
/**
 * Sidebar partagée — FRONOTE
 * Version 2 : data-driven depuis modules_config
 *
 * Variables attendues (définies par shared_header.php) :
 *   $activePage  — page active pour le highlight
 *   $rootPrefix  — préfixe relatif vers la racine
 *
 * Variables optionnelles :
 *   $sidebarExtraContent — HTML supplémentaire (actions spécifiques au module)
 */

use API\Services\ModuleService;

$currentUser   = getCurrentUser();
$userRole      = $currentUser['role'] ?? ($currentUser['profil'] ?? '');
$isAdmin       = ($userRole === 'administrateur');
$activePage    = $activePage ?? '';
$rootPrefix    = $rootPrefix ?? '../';
$sidebarExtraContent = $sidebarExtraContent ?? '';

// ─── Infos établissement ─────────────────────────────────────────
$_sb_nom_etablissement = 'Établissement Scolaire';
try {
    $_sb_etab_service = app('etablissement');
    $_sb_etab_info = $_sb_etab_service->getInfo();
    $_sb_nom_etablissement = $_sb_etab_info['nom'] ?? $_sb_nom_etablissement;
} catch (Exception $e) {
    $_sb_etablissement_file = dirname(__DIR__) . '/login/data/etablissement.json';
    if (file_exists($_sb_etablissement_file)) {
        $_sb_etablissement_data = json_decode(file_get_contents($_sb_etablissement_file), true);
        $_sb_nom_etablissement = $_sb_etablissement_data['nom'] ?? $_sb_nom_etablissement;
    }
}

// ─── Module data for sidebar ─────────────────────────────────────
$_sb_sidebarModules = [];
$_sb_categoryMeta = ModuleService::categoryMeta();
try {
    /** @var ModuleService $_sb_ms */
    $_sb_ms = app('modules');
    $_sb_sidebarModules = $_sb_ms->getForSidebar($userRole);
} catch (Exception $e) {
    // modules_config not ready — sidebar shows nothing dynamic
}

// ─── User info ───────────────────────────────────────────────────
$_sb_user_fullname = getUserFullName();
$_sb_user_role     = getUserRole();
$_sb_user_initials = getUserInitials();
$_sb_profil_labels = [
    'administrateur' => 'Administrateur',
    'professeur'     => 'Professeur',
    'eleve'          => 'Élève',
    'parent'         => 'Parent',
    'vie_scolaire'   => 'Vie scolaire',
];
$_sb_profil_label = $_sb_profil_labels[$_sb_user_role] ?? ucfirst($_sb_user_role);

// ─── Badge counts ────────────────────────────────────────────────
$_sb_unread_messages = 0;
$_sb_admin_badge = 0;
try {
    $_sb_pdo = getPDO();
    // Unread messages badge
    $stmt = $_sb_pdo->prepare("SELECT COALESCE(SUM(cp.unread_count), 0) FROM conversation_participants cp WHERE cp.user_id = ? AND cp.user_type = ? AND cp.is_deleted = 0");
    $stmt->execute([$currentUser['id'] ?? 0, $userRole]);
    $_sb_unread_messages = (int) $stmt->fetchColumn();

    // Admin badges
    if ($isAdmin) {
        $stmt = $_sb_pdo->query("SELECT COUNT(*) FROM demandes_reinitialisation WHERE status = 'pending'");
        $_sb_admin_badge += (int) $stmt->fetchColumn();
        $stmt = $_sb_pdo->query("SELECT COUNT(*) FROM justificatifs WHERE traite = 0");
        $_sb_admin_badge += (int) $stmt->fetchColumn();
    }
} catch (Exception $e) { /* silent */ }

// ─── User theme ──────────────────────────────────────────────────
$_sb_theme = 'light';
try {
    $stmt = $_sb_pdo->prepare("SELECT theme FROM user_settings WHERE user_id = ? AND user_type = ?");
    $stmt->execute([$currentUser['id'] ?? 0, $userRole]);
    $_sb_theme = $stmt->fetchColumn() ?: 'light';
} catch (Exception $e) { /* fallback */ }
?>

<!-- Sidebar -->
<div class="sidebar" id="mainSidebar">

    <!-- Logo + établissement -->
    <a href="<?= $rootPrefix ?>accueil/accueil.php" class="sidebar-brand">
        <div class="sidebar-brand-logo">F</div>
        <div class="sidebar-brand-text">
            <span class="sidebar-brand-name">FRONOTE</span>
            <span class="sidebar-brand-sub"><?= htmlspecialchars(mb_strimwidth($_sb_nom_etablissement, 0, 30, '…')) ?></span>
        </div>
    </a>

    <!-- Search -->
    <div class="sidebar-search">
        <i class="fas fa-search sidebar-search-icon"></i>
        <input type="text" id="sidebarSearch" placeholder="Rechercher un module..." autocomplete="off">
        <kbd class="sidebar-search-kbd">Ctrl+K</kbd>
    </div>

    <!-- Scrollable nav area -->
    <nav class="sidebar-nav-scroll" id="sidebarNavScroll">

        <!-- Accueil (always visible) -->
        <a href="<?= $rootPrefix ?>accueil/accueil.php" class="sidebar-link <?= $activePage === 'accueil' ? 'active' : '' ?>" data-search="accueil tableau de bord dashboard">
            <span class="sidebar-link-icon"><i class="fas fa-home"></i></span>
            <span class="sidebar-link-text">Accueil</span>
        </a>

        <!-- Dynamic module categories -->
        <?php foreach ($_sb_sidebarModules as $catKey => $_sb_catMods): ?>
        <?php
            $catMeta = $_sb_categoryMeta[$catKey] ?? ['label' => ucfirst($catKey), 'icon' => 'fas fa-folder', 'order' => 99];
            $catId = 'sbcat-' . $catKey;
        ?>
        <div class="sidebar-category" data-category="<?= $catKey ?>">
            <button class="sidebar-category-header" data-target="<?= $catId ?>" type="button">
                <span class="sidebar-category-icon"><i class="<?= $catMeta['icon'] ?>"></i></span>
                <span class="sidebar-category-label"><?= htmlspecialchars($catMeta['label']) ?></span>
                <span class="sidebar-category-count"><?= count($_sb_catMods) ?></span>
                <i class="fas fa-chevron-down sidebar-category-chevron"></i>
            </button>
            <div class="sidebar-category-body" id="<?= $catId ?>">
                <?php foreach ($_sb_catMods as $mod): ?>
                <?php
                    // Determine active page match
                    $modKey = $mod['module_key'];
                    $isActive = ($activePage === $modKey)
                        || ($modKey === 'cahierdetextes' && $activePage === 'cahierdetextes');
                    $badge = '';
                    if ($modKey === 'messagerie') {
                        $_sb_cnt  = (int) $_sb_unread_messages;
                        $_sb_disp = $_sb_cnt > 0 ? 'inline-flex' : 'none';
                        $_sb_lbl  = $_sb_cnt > 99 ? '99+' : ($_sb_cnt > 0 ? (string)$_sb_cnt : '');
                        $badge = '<span class="sidebar-badge" id="sidebarMsgBadge" style="display:' . $_sb_disp . '">' . $_sb_lbl . '</span>';
                    }
                    if ($modKey === 'notifications') {
                        try {
                            $stmt = $_sb_pdo->prepare("SELECT COUNT(*) FROM notifications_globales WHERE user_id = ? AND user_type = ? AND lu = 0");
                            $stmt->execute([$currentUser['id'] ?? 0, $userRole]);
                            $notifCount = (int) $stmt->fetchColumn();
                            if ($notifCount > 0) {
                                $badge = '<span class="sidebar-badge">' . $notifCount . '</span>';
                            }
                        } catch (Exception $e) {}
                    }
                ?>
                <a href="<?= $rootPrefix . htmlspecialchars($mod['route']) ?>"
                   class="sidebar-link <?= $isActive ? 'active' : '' ?>"
                   data-search="<?= htmlspecialchars(strtolower($mod['label'] . ' ' . ($mod['description'] ?? ''))) ?>"
                   title="<?= htmlspecialchars($mod['description'] ?? $mod['label']) ?>">
                    <span class="sidebar-link-icon"><i class="<?= htmlspecialchars($mod['icon']) ?>"></i></span>
                    <span class="sidebar-link-text"><?= htmlspecialchars($mod['label']) ?></span>
                    <?= $badge ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>

        <!-- Module-specific extra content -->
        <?php if (!empty($sidebarExtraContent)): ?>
        <div class="sidebar-category">
            <button class="sidebar-category-header" data-target="sbcat-extra" type="button">
                <span class="sidebar-category-icon"><i class="fas fa-bolt"></i></span>
                <span class="sidebar-category-label">Actions</span>
                <i class="fas fa-chevron-down sidebar-category-chevron"></i>
            </button>
            <div class="sidebar-category-body" id="sbcat-extra">
                <?= $sidebarExtraContent ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- No results message (hidden by default) -->
        <div class="sidebar-no-results" id="sidebarNoResults" style="display:none;">
            <i class="fas fa-search"></i>
            <span>Aucun module trouvé</span>
        </div>

    </nav>

    <!-- Bottom section (fixed) -->
    <div class="sidebar-bottom">
        <!-- Admin link -->
        <?php if ($isAdmin): ?>
        <a href="<?= $rootPrefix ?>admin/dashboard.php" class="sidebar-link sidebar-link--admin <?= $activePage === 'admin' ? 'active' : '' ?>">
            <span class="sidebar-link-icon"><i class="fas fa-shield-alt"></i></span>
            <span class="sidebar-link-text">Administration</span>
            <?php if ($_sb_admin_badge > 0): ?>
                <span class="sidebar-badge sidebar-badge--warning"><?= $_sb_admin_badge ?></span>
            <?php endif; ?>
        </a>
        <?php endif; ?>

        <!-- Theme toggle -->
        <button class="sidebar-link sidebar-theme-toggle" id="sidebarThemeToggle" type="button" title="Changer le thème">
            <span class="sidebar-link-icon">
                <i class="fas fa-moon" id="themeIconDark" style="display:<?= $_sb_theme === 'light' ? 'inline' : 'none' ?>"></i>
                <i class="fas fa-sun" id="themeIconLight" style="display:<?= $_sb_theme === 'dark' ? 'inline' : 'none' ?>"></i>
                <i class="fas fa-adjust" id="themeIconAuto" style="display:<?= $_sb_theme === 'auto' ? 'inline' : 'none' ?>"></i>
            </span>
            <span class="sidebar-link-text" id="themeLabel"><?= $_sb_theme === 'dark' ? 'Thème clair' : ($_sb_theme === 'auto' ? 'Thème auto' : 'Thème sombre') ?></span>
        </button>

        <!-- User card -->
        <div class="sidebar-user">
            <div class="sidebar-user-avatar"><?= htmlspecialchars($_sb_user_initials) ?></div>
            <div class="sidebar-user-info">
                <div class="sidebar-user-name"><?= htmlspecialchars($_sb_user_fullname) ?></div>
                <div class="sidebar-user-role"><?= htmlspecialchars($_sb_profil_label) ?></div>
            </div>
            <div class="sidebar-user-actions">
                <a href="<?= $rootPrefix ?>parametres/parametres.php" title="Paramètres"><i class="fas fa-cog"></i></a>
                <a href="<?= $rootPrefix ?>login/logout.php" title="Déconnexion" class="sidebar-logout-icon"><i class="fas fa-sign-out-alt"></i></a>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    // ─── Collapsible categories ─────────────────────────────────
    const STORAGE_KEY = 'fronote_sidebar_cats';

    function getSaved() {
        try { return JSON.parse(localStorage.getItem(STORAGE_KEY)) || {}; } catch(e) { return {}; }
    }
    function saveCats(s) {
        try { localStorage.setItem(STORAGE_KEY, JSON.stringify(s)); } catch(e) {}
    }

    const catState = getSaved();

    document.querySelectorAll('.sidebar-category-header').forEach(function(btn) {
        const targetId = btn.getAttribute('data-target');
        const body = document.getElementById(targetId);
        if (!body) return;

        // Restore saved state (default: expanded)
        if (catState[targetId] === false) {
            body.classList.add('collapsed');
            btn.classList.add('collapsed');
        }

        btn.addEventListener('click', function() {
            const isCollapsed = body.classList.toggle('collapsed');
            btn.classList.toggle('collapsed', isCollapsed);
            catState[targetId] = !isCollapsed;
            saveCats(catState);
        });
    });

    // ─── Sidebar search ─────────────────────────────────────────
    const searchInput = document.getElementById('sidebarSearch');
    const navScroll = document.getElementById('sidebarNavScroll');
    const noResults = document.getElementById('sidebarNoResults');

    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const q = this.value.toLowerCase().trim();
            let anyVisible = false;

            // Filter individual links
            navScroll.querySelectorAll('.sidebar-link').forEach(function(link) {
                const searchData = (link.getAttribute('data-search') || '') + ' ' + link.textContent.toLowerCase();
                const match = !q || searchData.includes(q);
                link.style.display = match ? '' : 'none';
                if (match) anyVisible = true;
            });

            // Show/hide categories based on whether they have visible children
            navScroll.querySelectorAll('.sidebar-category').forEach(function(cat) {
                const links = cat.querySelectorAll('.sidebar-link');
                let catVisible = false;
                links.forEach(function(l) { if (l.style.display !== 'none') catVisible = true; });
                cat.style.display = catVisible ? '' : 'none';
                // Auto-expand when searching
                if (q && catVisible) {
                    cat.querySelector('.sidebar-category-body')?.classList.remove('collapsed');
                    cat.querySelector('.sidebar-category-header')?.classList.remove('collapsed');
                }
            });

            noResults.style.display = (!anyVisible && q) ? '' : 'none';
        });

        // Ctrl+K shortcut
        document.addEventListener('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                searchInput.focus();
                searchInput.select();
            }
            if (e.key === 'Escape' && document.activeElement === searchInput) {
                searchInput.value = '';
                searchInput.dispatchEvent(new Event('input'));
                searchInput.blur();
            }
        });
    }

    // ─── Theme toggle ───────────────────────────────────────────
    const themeToggle = document.getElementById('sidebarThemeToggle');
    if (themeToggle) {
        themeToggle.addEventListener('click', function() {
            const html = document.documentElement;
            const current = html.getAttribute('data-theme') || 'light';
            const cycle = { light: 'dark', dark: 'auto', auto: 'light' };
            const next = cycle[current] || 'light';

            applyTheme(next);

            // Save preference via AJAX
            fetch('<?= $rootPrefix ?>parametres/api_theme.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'theme=' + encodeURIComponent(next) + '&csrf_token=' + encodeURIComponent(document.querySelector('meta[name=csrf-token]')?.content || '')
            }).catch(function() {});
        });
    }

    function applyTheme(theme) {
        const html = document.documentElement;
        const iconDark = document.getElementById('themeIconDark');
        const iconLight = document.getElementById('themeIconLight');
        const iconAuto = document.getElementById('themeIconAuto');
        const label = document.getElementById('themeLabel');

        if (theme === 'auto') {
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            html.setAttribute('data-theme', prefersDark ? 'dark' : 'light');
        } else {
            html.setAttribute('data-theme', theme);
        }
        html.setAttribute('data-theme-pref', theme);

        if (iconDark) iconDark.style.display = theme === 'light' ? 'inline' : 'none';
        if (iconLight) iconLight.style.display = theme === 'dark' ? 'inline' : 'none';
        if (iconAuto) iconAuto.style.display = theme === 'auto' ? 'inline' : 'none';
        if (label) label.textContent = theme === 'dark' ? 'Thème clair' : (theme === 'auto' ? 'Thème auto' : 'Thème sombre');

        try { localStorage.setItem('fronote_theme', theme); } catch(e) {}
    }

    // Listen for OS theme changes when in auto mode
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function() {
        const pref = document.documentElement.getAttribute('data-theme-pref');
        if (pref === 'auto') applyTheme('auto');
    });
})();
</script>
