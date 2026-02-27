<?php
// Inclure l'API centralisée
require_once __DIR__ . '/../API/core.php';

// Vérifier l'authentification
requireAuth();

// Récupération des informations utilisateur via l'API
$user = getCurrentUser();
$user_role = getUserRole();
$user_fullname = getUserFullName();
$user_initials = getUserInitials();

// Définition des couleurs pour les matières
$couleurs_matieres = [
    'Français' => 'francais',
    'Mathématiques' => 'mathematiques',
    'Histoire-Géographie' => 'histoire-geo',
    'Anglais' => 'anglais',
    'Espagnol' => 'espagnol',
    'Allemand' => 'allemand',
    'Physique-Chimie' => 'physique-chimie',
    'SVT' => 'svt',
    'Technologie' => 'technologie',
    'Arts Plastiques' => 'arts',
    'Musique' => 'musique',
    'EPS' => 'eps'
];

// getTrimestre() est fourni par l'API (Bridge)

// Récupérer la date du jour et le trimestre
$aujourdhui = date('d/m/Y');
$trimestre = getTrimestre();
$jours = ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
$jour = $jours[date('w')];

// Récupérer le nom de l'établissement depuis le fichier JSON
$json_file = __DIR__ . '/../login/data/etablissement.json';
$etablissement_data = [];
if (file_exists($json_file)) {
    $etablissement_data = json_decode(file_get_contents($json_file), true);
}
$nom_etablissement = $etablissement_data['nom'] ?? 'Établissement Scolaire';

// Configuration des templates partagés
$pageTitle = 'Notes et résultats';
$activePage = 'notes';
$isAdmin = $user_role === 'administrateur';
$extraCss = ['assets/css/modules/notes.css'];

// Inclusion des templates partagés
include __DIR__ . '/../templates/shared_header.php';
include __DIR__ . '/../templates/shared_sidebar.php';
include __DIR__ . '/../templates/shared_topbar.php';
?>

            <!-- Contenu principal -->
            <div class="content-container">
                <!-- Contenu à compléter -->
            </div>

<?php
include __DIR__ . '/../templates/shared_footer.php';
?>