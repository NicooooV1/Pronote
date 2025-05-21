<?php
// Démarrer la session si ce n'est pas déjà fait
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Récupération des informations utilisateur
$user = $_SESSION['user'] ?? null;
$user_role = $user['profil'] ?? 'eleve';
$user_nom = $user['nom'] ?? '';
$user_prenom = $user['prenom'] ?? '';
$user_classe = $user['classe'] ?? '';
$user_initials = strtoupper(mb_substr($user_prenom, 0, 1) . mb_substr($user_nom, 0, 1));

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

// Fonction pour calculer le trimestre actuel
function getTrimestre() {
    $mois = date('n');
    if ($mois >= 9 && $mois <= 12) {
        return "1er trimestre";
    } elseif ($mois >= 1 && $mois <= 3) {
        return "2ème trimestre";
    } elseif ($mois >= 4 && $mois <= 6) {
        return "3ème trimestre";
    } else {
        return "Période estivale";
    }
}

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
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notes - PRONOTE</title>
    <link rel="stylesheet" href="assets/css/modules/notes.css">
    <link rel="stylesheet" href="../assets/css/pronote-theme.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <div class="app-container">
        <!-- Barre latérale -->
        <div class="sidebar">
            <a href="../accueil/accueil.php" class="logo-container">
                <div class="app-logo">P</div>
                <div class="app-title">PRONOTE</div>
            </a>
            
            <div class="sidebar-section">
                <div class="sidebar-section-header">Navigation</div>
                <div class="sidebar-nav">
                    <a href="../accueil/accueil.php" class="sidebar-nav-item">
                        <span class="sidebar-nav-icon"><i class="fas fa-home"></i></span>
                        <span>Accueil</span>
                    </a>
                    <a href="notes.php" class="sidebar-nav-item active">
                        <span class="sidebar-nav-icon"><i class="fas fa-chart-bar"></i></span>
                        <span>Notes</span>
                    </a>
                    <a href="../agenda/agenda.php" class="sidebar-nav-item">
                        <span class="sidebar-nav-icon"><i class="fas fa-calendar"></i></span>
                        <span>Agenda</span>
                    </a>
                    <a href="../cahierdetextes/cahierdetextes.php" class="sidebar-nav-item">
                        <span class="sidebar-nav-icon"><i class="fas fa-book"></i></span>
                        <span>Cahier de textes</span>
                    </a>
                    <a href="../messagerie/index.php" class="sidebar-nav-item">
                        <span class="sidebar-nav-icon"><i class="fas fa-envelope"></i></span>
                        <span>Messagerie</span>
                    </a>
                    <a href="../absences/absences.php" class="sidebar-nav-item">
                        <span class="sidebar-nav-icon"><i class="fas fa-calendar-times"></i></span>
                        <span>Absences</span>
                    </a>
                </div>
            </div>
        </div>

        <!-- Contenu principal -->
        <div class="main-content">
            <!-- En-tête de page -->
            <div class="top-header">
                <div class="page-title">
                    <h1>Notes et résultats</h1>
                </div>
                
                <div class="header-actions">
                    <a href="../login/public/logout.php" class="logout-button" title="Déconnexion">
                        <i class="fas fa-sign-out-alt"></i>
                    </a>
                    <div class="user-avatar" title="Prénom Nom">XY</div>
                </div>
            </div>

            <!-- Contenu principal -->
            <div class="content-container">
                <!-- Contenu à compléter -->
            </div>
        </div>
    </div>
</body>
</html>