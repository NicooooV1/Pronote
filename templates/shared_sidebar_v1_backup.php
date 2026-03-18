<?php
/**
 * Sidebar partagée unique — Pronote
 * Inclus sur TOUTES les pages, y compris /admin/
 * Le contenu s'adapte au rôle de l'utilisateur connecté.
 *
 * Variables attendues (définies par shared_header.php) :
 *   $activePage  — string : page active pour le highlight
 *   $rootPrefix  — string : préfixe relatif vers la racine (ex: '../')
 *
 * Variables optionnelles :
 *   $sidebarExtraContent — string : HTML supplémentaire (actions spécifiques au module)
 */

// L'API doit être chargée avant l'inclusion de ce fichier
$currentUser = getCurrentUser();
$userRole = $currentUser['role'] ?? ($currentUser['profil'] ?? '');
$isAdmin = ($userRole === 'administrateur');

$activePage = $activePage ?? '';
$rootPrefix = $rootPrefix ?? '../';
$sidebarExtraContent = $sidebarExtraContent ?? '';

// Récupérer les informations contextuelles pour la sidebar
$_sb_nom_etablissement = 'Établissement Scolaire';
try {
    $_sb_etab_service = app('etablissement');
    $_sb_etab_info = $_sb_etab_service->getInfo();
    $_sb_nom_etablissement = $_sb_etab_info['nom'] ?? $_sb_nom_etablissement;
} catch (Exception $e) {
    // Fallback JSON pour compatibilité arrière (première installation)
    $_sb_etablissement_file = dirname(__DIR__) . '/login/data/etablissement.json';
    if (file_exists($_sb_etablissement_file)) {
        $_sb_etablissement_data = json_decode(file_get_contents($_sb_etablissement_file), true);
        $_sb_nom_etablissement = $_sb_etablissement_data['nom'] ?? $_sb_nom_etablissement;
    }
}

// Charger le service modules pour contrôler la visibilité de chaque lien
$_sb_module_check = null;
try {
    $_sb_module_check = app('modules');
} catch (Exception $e) {
    // Table pas encore créée — tous les modules restent visibles
}

/** Retourne true si le module est activé (ou si le service n'est pas dispo) */
function _sbModOn(string $key): bool {
    global $_sb_module_check;
    if (!$_sb_module_check) return true;
    try { return $_sb_module_check->isEnabled($key); } catch (Exception $e) { return true; }
}
$_sb_trimestre = function_exists('getTrimestre') ? getTrimestre() : '';
$_sb_date = date('d/m/Y');
$_sb_user_fullname = getUserFullName();
$_sb_user_role = getUserRole();
$_sb_profil_labels = [
    'administrateur' => 'Administrateur',
    'professeur'     => 'Professeur',
    'eleve'          => 'Élève',
    'parent'         => 'Parent',
    'vie_scolaire'   => 'Vie scolaire',
];
$_sb_profil_label = $_sb_profil_labels[$_sb_user_role] ?? ucfirst($_sb_user_role);
?>

<!-- Sidebar -->
<div class="sidebar">
    <a href="<?= $rootPrefix ?>accueil/accueil.php" class="logo-container">
        <div class="app-logo">F</div>
        <div class="app-title">FRONOTE</div>
    </a>

    <!-- ===== SECTION 1 : Navigation principale (tous les rôles) ===== -->
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
                <?php if (_sbModOn('notes')): ?>
                <a href="<?= $rootPrefix ?>notes/notes.php" class="sidebar-nav-item <?= $activePage === 'notes' ? 'active' : '' ?>">
                    <span class="sidebar-nav-icon"><i class="fas fa-chart-bar"></i></span>
                    <span>Notes</span>
                </a>
                <?php endif; ?>
                <?php if (_sbModOn('agenda')): ?>
                <a href="<?= $rootPrefix ?>agenda/agenda.php" class="sidebar-nav-item <?= $activePage === 'agenda' ? 'active' : '' ?>">
                    <span class="sidebar-nav-icon"><i class="fas fa-calendar"></i></span>
                    <span>Agenda</span>
                </a>
                <?php endif; ?>
                <?php if (_sbModOn('cahier_textes')): ?>
                <a href="<?= $rootPrefix ?>cahierdetextes/cahierdetextes.php" class="sidebar-nav-item <?= $activePage === 'cahierdetextes' ? 'active' : '' ?>">
                    <span class="sidebar-nav-icon"><i class="fas fa-book"></i></span>
                    <span>Cahier de textes</span>
                </a>
                <?php endif; ?>
                <?php if (_sbModOn('messagerie')): ?>
                <a href="<?= $rootPrefix ?>messagerie/index.php" class="sidebar-nav-item <?= $activePage === 'messagerie' ? 'active' : '' ?>">
                    <span class="sidebar-nav-icon"><i class="fas fa-envelope"></i></span>
                    <span>Messagerie</span>
                </a>
                <?php endif; ?>
                <?php if (_sbModOn('annonces')): ?>
                <a href="<?= $rootPrefix ?>annonces/annonces.php" class="sidebar-nav-item <?= $activePage === 'annonces' ? 'active' : '' ?>">
                    <span class="sidebar-nav-icon"><i class="fas fa-bullhorn"></i></span>
                    <span>Annonces</span>
                </a>
                <?php endif; ?>
                <?php if (_sbModOn('emploi_du_temps')): ?>
                <a href="<?= $rootPrefix ?>emploi_du_temps/emploi_du_temps.php" class="sidebar-nav-item <?= $activePage === 'emploi_du_temps' ? 'active' : '' ?>">
                    <span class="sidebar-nav-icon"><i class="fas fa-table"></i></span>
                    <span>Emploi du temps</span>
                </a>
                <?php endif; ?>
                <?php if ((isAdmin() || isVieScolaire() || isTeacher()) && _sbModOn('absences')): ?>
                <a href="<?= $rootPrefix ?>absences/absences.php" class="sidebar-nav-item <?= $activePage === 'absences' ? 'active' : '' ?>">
                    <span class="sidebar-nav-icon"><i class="fas fa-calendar-times"></i></span>
                    <span>Absences</span>
                </a>
                <?php endif; ?>
                <?php if ((isAdmin() || isVieScolaire() || isTeacher()) && _sbModOn('appel')): ?>
                <a href="<?= $rootPrefix ?>appel/appel.php" class="sidebar-nav-item <?= $activePage === 'appel' ? 'active' : '' ?>">
                    <span class="sidebar-nav-icon"><i class="fas fa-clipboard-check"></i></span>
                    <span>Appel</span>
                </a>
                <?php endif; ?>
                <?php if ((isAdmin() || isVieScolaire() || isTeacher()) && _sbModOn('discipline')): ?>
                <a href="<?= $rootPrefix ?>discipline/incidents.php" class="sidebar-nav-item <?= $activePage === 'discipline' ? 'active' : '' ?>">
                    <span class="sidebar-nav-icon"><i class="fas fa-gavel"></i></span>
                    <span>Discipline</span>
                </a>
                <?php endif; ?>
                <?php if ((isAdmin() || isVieScolaire() || isTeacher()) && _sbModOn('vie_scolaire')): ?>
                <a href="<?= $rootPrefix ?>vie_scolaire/dashboard.php" class="sidebar-nav-item <?= $activePage === 'vie_scolaire' ? 'active' : '' ?>">
                    <span class="sidebar-nav-icon"><i class="fas fa-user-shield"></i></span>
                    <span>Vie scolaire</span>
                </a>
                <?php endif; ?>
                <?php if ((isAdmin() || isVieScolaire() || isTeacher()) && _sbModOn('reporting')): ?>
                <a href="<?= $rootPrefix ?>reporting/reporting.php" class="sidebar-nav-item <?= $activePage === 'reporting' ? 'active' : '' ?>">
                    <span class="sidebar-nav-icon"><i class="fas fa-chart-line"></i></span>
                    <span>Reporting</span>
                </a>
                <?php endif; ?>
                <?php if (_sbModOn('bulletins')): ?>
                <a href="<?= $rootPrefix ?>bulletins/bulletins.php" class="sidebar-nav-item <?= $activePage === 'bulletins' ? 'active' : '' ?>">
                    <span class="sidebar-nav-icon"><i class="fas fa-file-alt"></i></span>
                    <span>Bulletins</span>
                </a>
                <?php endif; ?>
                <?php if (_sbModOn('devoirs')): ?>
                <a href="<?= $rootPrefix ?>devoirs/mes_devoirs.php" class="sidebar-nav-item <?= $activePage === 'devoirs' ? 'active' : '' ?>">
                    <span class="sidebar-nav-icon"><i class="fas fa-tasks"></i></span>
                    <span>Devoirs en ligne</span>
                </a>
                <?php endif; ?>
                <?php if (_sbModOn('competences')): ?>
                <a href="<?= $rootPrefix ?>competences/competences.php" class="sidebar-nav-item <?= $activePage === 'competences' ? 'active' : '' ?>">
                    <span class="sidebar-nav-icon"><i class="fas fa-clipboard-list"></i></span>
                    <span>Compétences</span>
                </a>
                <?php endif; ?>
                <?php if (_sbModOn('trombinoscope')): ?>
                <a href="<?= $rootPrefix ?>trombinoscope/trombinoscope.php" class="sidebar-nav-item <?= $activePage === 'trombinoscope' ? 'active' : '' ?>">
                    <span class="sidebar-nav-icon"><i class="fas fa-users"></i></span>
                    <span>Trombinoscope</span>
                </a>
                <?php endif; ?>
                <?php if (_sbModOn('documents')): ?>
                <a href="<?= $rootPrefix ?>documents/documents.php" class="sidebar-nav-item <?= $activePage === 'documents' ? 'active' : '' ?>">
                    <span class="sidebar-nav-icon"><i class="fas fa-folder-open"></i></span>
                    <span>Documents</span>
                </a>
                <?php endif; ?>
                <?php if (_sbModOn('notifications')): ?>
                <a href="<?= $rootPrefix ?>notifications/notifications.php" class="sidebar-nav-item <?= $activePage === 'notifications' ? 'active' : '' ?>">
                    <span class="sidebar-nav-icon"><i class="fas fa-bell"></i></span>
                    <span>Notifications</span>
                </a>
                <?php endif; ?>
                <?php if (_sbModOn('reunions')): ?>
                <a href="<?= $rootPrefix ?>reunions/reunions.php" class="sidebar-nav-item <?= $activePage === 'reunions' ? 'active' : '' ?>">
                    <span class="sidebar-nav-icon"><i class="fas fa-handshake"></i></span>
                    <span>Réunions</span>
                </a>
                <?php endif; ?>
                <?php if (_sbModOn('bibliotheque')): ?>
                <a href="<?= $rootPrefix ?>bibliotheque/catalogue.php" class="sidebar-nav-item <?= $activePage === 'bibliotheque' ? 'active' : '' ?>">
                    <span class="sidebar-nav-icon"><i class="fas fa-book-reader"></i></span>
                    <span>Bibliothèque</span>
                </a>
                <?php endif; ?>
                <?php if (_sbModOn('clubs')): ?>
                <a href="<?= $rootPrefix ?>clubs/clubs.php" class="sidebar-nav-item <?= $activePage === 'clubs' ? 'active' : '' ?>">
                    <span class="sidebar-nav-icon"><i class="fas fa-users"></i></span>
                    <span>Clubs</span>
                </a>
                <?php endif; ?>
                <?php if (_sbModOn('orientation')): ?>
                <a href="<?= $rootPrefix ?>orientation/orientation.php" class="sidebar-nav-item <?= $activePage === 'orientation' ? 'active' : '' ?>">
                    <span class="sidebar-nav-icon"><i class="fas fa-compass"></i></span>
                    <span>Orientation</span>
                </a>
                <?php endif; ?>
                <?php if (isParent() && _sbModOn('inscriptions')): ?>
                <a href="<?= $rootPrefix ?>inscriptions/inscriptions.php" class="sidebar-nav-item <?= $activePage === 'inscriptions' ? 'active' : '' ?>">
                    <span class="sidebar-nav-icon"><i class="fas fa-user-plus"></i></span>
                    <span>Inscriptions</span>
                </a>
                <?php endif; ?>
                <?php if (_sbModOn('signalements')): ?>
                <a href="<?= $rootPrefix ?>signalements/signaler.php" class="sidebar-nav-item <?= $activePage === 'signalements' ? 'active' : '' ?>">
                    <span class="sidebar-nav-icon"><i class="fas fa-shield-alt"></i></span>
                    <span>Signalements</span>
                </a>
                <?php endif; ?>
                <?php if ((isAdmin() || isVieScolaire() || isParent() || isStudent()) && _sbModOn('infirmerie')): ?>
                <a href="<?= $rootPrefix ?>infirmerie/infirmerie.php" class="sidebar-nav-item <?= $activePage === 'infirmerie' ? 'active' : '' ?>">
                    <span class="sidebar-nav-icon"><i class="fas fa-heartbeat"></i></span>
                    <span>Infirmerie</span>
                </a>
                <?php endif; ?>
                <?php if (_sbModOn('examens')): ?>
                <a href="<?= $rootPrefix ?>examens/examens.php" class="sidebar-nav-item <?= $activePage === 'examens' ? 'active' : '' ?>">
                    <span class="sidebar-nav-icon"><i class="fas fa-file-signature"></i></span>
                    <span>Examens</span>
                </a>
                <?php endif; ?>
                <?php if (_sbModOn('ressources')): ?>
                <a href="<?= $rootPrefix ?>ressources/ressources.php" class="sidebar-nav-item <?= $activePage === 'ressources' ? 'active' : '' ?>">
                    <span class="sidebar-nav-icon"><i class="fas fa-book-open"></i></span>
                    <span>Ressources</span>
                </a>
                <?php endif; ?>
                <?php if (_sbModOn('diplomes')): ?>
                <a href="<?= $rootPrefix ?>diplomes/diplomes.php" class="sidebar-nav-item <?= $activePage === 'diplomes' ? 'active' : '' ?>">
                    <span class="sidebar-nav-icon"><i class="fas fa-graduation-cap"></i></span>
                    <span>Diplômes</span>
                </a>
                <?php endif; ?>
                <?php if (_sbModOn('periscolaire')): ?>
                <a href="<?= $rootPrefix ?>periscolaire/services.php" class="sidebar-nav-item <?= $activePage === 'periscolaire' ? 'active' : '' ?>">
                    <span class="sidebar-nav-icon"><i class="fas fa-utensils"></i></span>
                    <span>Périscolaire</span>
                </a>
                <?php endif; ?>
                <?php if (_sbModOn('stages')): ?>
                <a href="<?= $rootPrefix ?>stages/stages.php" class="sidebar-nav-item <?= $activePage === 'stages' ? 'active' : '' ?>">
                    <span class="sidebar-nav-icon"><i class="fas fa-briefcase"></i></span>
                    <span>Stages</span>
                </a>
                <?php endif; ?>
                <?php if (_sbModOn('transports')): ?>
                <a href="<?= $rootPrefix ?>transports/lignes.php" class="sidebar-nav-item <?= $activePage === 'transports' ? 'active' : '' ?>">
                    <span class="sidebar-nav-icon"><i class="fas fa-bus"></i></span>
                    <span>Transports</span>
                </a>
                <?php endif; ?>
                <?php if ((isParent() || isAdmin() || isVieScolaire()) && _sbModOn('facturation')): ?>
                <a href="<?= $rootPrefix ?>facturation/factures.php" class="sidebar-nav-item <?= $activePage === 'facturation' ? 'active' : '' ?>">
                    <span class="sidebar-nav-icon"><i class="fas fa-file-invoice-dollar"></i></span>
                    <span>Facturation</span>
                </a>
                <?php endif; ?>
                <?php if ((isAdmin() || isVieScolaire() || isTeacher()) && _sbModOn('besoins')): ?>
                <a href="<?= $rootPrefix ?>besoins/besoins.php" class="sidebar-nav-item <?= $activePage === 'besoins' ? 'active' : '' ?>">
                    <span class="sidebar-nav-icon"><i class="fas fa-hand-holding-heart"></i></span>
                    <span>Besoins particuliers</span>
                </a>
                <?php endif; ?>
                <?php if ((isAdmin() || isVieScolaire() || isTeacher()) && _sbModOn('salles')): ?>
                <a href="<?= $rootPrefix ?>salles/reservations.php" class="sidebar-nav-item <?= $activePage === 'salles' ? 'active' : '' ?>">
                    <span class="sidebar-nav-icon"><i class="fas fa-door-open"></i></span>
                    <span>Salles & Matériels</span>
                </a>
                <?php endif; ?>
                <?php if ((isAdmin() || isVieScolaire()) && _sbModOn('personnel')): ?>
                <a href="<?= $rootPrefix ?>personnel/absences.php" class="sidebar-nav-item <?= $activePage === 'personnel' ? 'active' : '' ?>">
                    <span class="sidebar-nav-icon"><i class="fas fa-user-tie"></i></span>
                    <span>Gestion personnel</span>
                </a>
                <?php endif; ?>
                <?php if ((isAdmin() || isVieScolaire()) && _sbModOn('rgpd')): ?>
                <a href="<?= $rootPrefix ?>rgpd/demandes.php" class="sidebar-nav-item <?= $activePage === 'rgpd' ? 'active' : '' ?>">
                    <span class="sidebar-nav-icon"><i class="fas fa-shield-alt"></i></span>
                    <span>RGPD & Audit</span>
                </a>
                <?php endif; ?>
                <?php if (_sbModOn('support')): ?>
                <a href="<?= $rootPrefix ?>support/aide.php" class="sidebar-nav-item <?= $activePage === 'support' ? 'active' : '' ?>">
                    <span class="sidebar-nav-icon"><i class="fas fa-question-circle"></i></span>
                    <span>Aide</span>
                </a>
                <?php endif; ?>
                <?php if (isAdmin() && _sbModOn('archivage')): ?>
                <a href="<?= $rootPrefix ?>archivage/archivage.php" class="sidebar-nav-item <?= $activePage === 'archivage' ? 'active' : '' ?>">
                    <span class="sidebar-nav-icon"><i class="fas fa-archive"></i></span>
                    <span>Archivage</span>
                </a>
                <?php endif; ?>
                <?php if ((isAdmin() || isVieScolaire()) && _sbModOn('inscriptions')): ?>
                <a href="<?= $rootPrefix ?>inscriptions/inscriptions.php" class="sidebar-nav-item <?= $activePage === 'inscriptions_admin' ? 'active' : '' ?>">
                    <span class="sidebar-nav-icon"><i class="fas fa-user-plus"></i></span>
                    <span>Inscriptions</span>
                </a>
                <?php endif; ?>
                <?php if (_sbModOn('cantine')): ?>
                <a href="<?= $rootPrefix ?>cantine/menus.php" class="sidebar-nav-item <?= $activePage === 'cantine' ? 'active' : '' ?>">
                    <span class="sidebar-nav-icon"><i class="fas fa-utensils"></i></span>
                    <span>Cantine</span>
                </a>
                <?php endif; ?>
                <?php if ((isAdmin() || isVieScolaire()) && _sbModOn('internat')): ?>
                <a href="<?= $rootPrefix ?>internat/chambres.php" class="sidebar-nav-item <?= $activePage === 'internat' ? 'active' : '' ?>">
                    <span class="sidebar-nav-icon"><i class="fas fa-bed"></i></span>
                    <span>Internat</span>
                </a>
                <?php endif; ?>
                <?php if (_sbModOn('garderie')): ?>
                <a href="<?= $rootPrefix ?>garderie/creneaux.php" class="sidebar-nav-item <?= $activePage === 'garderie' ? 'active' : '' ?>">
                    <span class="sidebar-nav-icon"><i class="fas fa-child"></i></span>
                    <span>Garderie</span>
                </a>
                <?php endif; ?>
                <?php if (_sbModOn('projets_pedagogiques')): ?>
                <a href="<?= $rootPrefix ?>projets_pedagogiques/projets.php" class="sidebar-nav-item <?= $activePage === 'projets' ? 'active' : '' ?>">
                    <span class="sidebar-nav-icon"><i class="fas fa-project-diagram"></i></span>
                    <span>Projets pédagogiques</span>
                </a>
                <?php endif; ?>
                <?php if (_sbModOn('parcours_educatifs')): ?>
                <a href="<?= $rootPrefix ?>parcours_educatifs/parcours.php" class="sidebar-nav-item <?= $activePage === 'parcours' ? 'active' : '' ?>">
                    <span class="sidebar-nav-icon"><i class="fas fa-route"></i></span>
                    <span>Parcours éducatifs</span>
                </a>
                <?php endif; ?>
                <?php if (_sbModOn('vie_associative')): ?>
                <a href="<?= $rootPrefix ?>vie_associative/associations.php" class="sidebar-nav-item <?= $activePage === 'associations' ? 'active' : '' ?>">
                    <span class="sidebar-nav-icon"><i class="fas fa-hands-helping"></i></span>
                    <span>Vie associative</span>
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ===== SECTION 2 : Administration (admin seulement) ===== -->
    <?php if ($isAdmin): ?>
    <?php
        // Charger les compteurs de badges pour les alertes admin
        $_adminBadgeCount = 0;
        try {
            $_admin_pdo = getPDO();
            $stmt = $_admin_pdo->query("SELECT COUNT(*) FROM demandes_reinitialisation WHERE status = 'pending'");
            $_adminBadgeCount += (int)$stmt->fetchColumn();
            $stmt = $_admin_pdo->query("SELECT COUNT(*) FROM justificatifs WHERE traite = 0");
            $_adminBadgeCount += (int)$stmt->fetchColumn();
        } catch (Exception $e) { /* silently fail */ }
    ?>
    <div class="sidebar-section" data-section="admin">
        <div class="sidebar-section-header sidebar-collapsible" data-target="admin">
            <span>Administration</span>
            <i class="fas fa-chevron-down sidebar-chevron"></i>
        </div>
        <div class="sidebar-section-body" id="section-admin">
            <div class="sidebar-nav">
                <a href="<?= $rootPrefix ?>admin/dashboard.php" class="sidebar-nav-item <?= $activePage === 'admin' ? 'active' : '' ?>">
                    <span class="sidebar-nav-icon"><i class="fas fa-cogs"></i></span>
                    <span>Panneau d'administration</span>
                    <?php if ($_adminBadgeCount > 0): ?>
                        <span class="sidebar-badge"><?= $_adminBadgeCount ?></span>
                    <?php endif; ?>
                </a>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ===== SECTION 3 : Contenu spécifique au module (optionnel) ===== -->
    <?php if (!empty($sidebarExtraContent)): ?>
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

    <!-- ===== SECTION 4 : Informations contextuelles ===== -->
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

    <!-- ===== SECTION 5 : Compte utilisateur ===== -->
    <div class="sidebar-section" style="margin-top: auto;">
        <div class="sidebar-section-header">COMPTE</div>
        <div class="sidebar-nav">
            <a href="<?= $rootPrefix ?>parametres/parametres.php" class="sidebar-nav-item <?= $activePage === 'parametres' ? 'active' : '' ?>">
                <span class="sidebar-nav-icon"><i class="fas fa-cog"></i></span>
                <span>Paramètres</span>
            </a>
            <a href="<?= $rootPrefix ?>login/logout.php" class="sidebar-nav-item sidebar-nav-item--logout">
                <span class="sidebar-nav-icon"><i class="fas fa-sign-out-alt"></i></span>
                <span>Déconnexion</span>
            </a>
        </div>
    </div>

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
