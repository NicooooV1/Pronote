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
    <title>Notes - Pronote</title>
    <link rel="stylesheet" href="../assets/css/pronote-core.css">
    <link rel="stylesheet" href="assets/css/notes.css">
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
                    <?php if ($user_role === 'vie_scolaire' || $user_role === 'administrateur'): ?>
                    <a href="../absences/absences.php" class="sidebar-nav-item">
                        <span class="sidebar-nav-icon"><i class="fas fa-calendar-times"></i></span>
                        <span>Absences</span>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="sidebar-section">
                <div class="sidebar-section-header">Informations</div>
                <div class="info-item">
                    <div class="info-label">Établissement</div>
                    <div class="info-value"><?= htmlspecialchars($nom_etablissement) ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Date</div>
                    <div class="info-value"><?= $jour . ' ' . $aujourdhui ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Période</div>
                    <div class="info-value"><?= $trimestre ?></div>
                </div>
                <?php if ($user_role === 'eleve'): ?>
                <div class="info-item">
                    <div class="info-label">Classe</div>
                    <div class="info-value"><?= htmlspecialchars($user_classe) ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Contenu principal -->
        <div class="main-content">
            <!-- En-tête -->
            <div class="top-header">
                <div class="page-title">
                    <h1>Notes et moyennes</h1>
                    <div class="subtitle">Consultez vos résultats scolaires</div>
                </div>
                
                <div class="header-actions">
                    <a href="../login/public/logout.php" class="logout-button" title="Déconnexion">
                        <i class="fas fa-sign-out-alt"></i>
                    </a>
                    <div class="user-avatar"><?= $user_initials ?></div>
                </div>
            </div>
            
            <!-- Bannière de bienvenue -->
            <div class="welcome-banner">
                <div class="welcome-content">
                    <h2>Vos résultats scolaires</h2>
                    <p>Suivi de votre progression pour <?= $trimestre ?></p>
                </div>
                <div class="welcome-logo">
                    <i class="fas fa-chart-line"></i>
                </div>
            </div>
            
            <!-- Contenu des notes -->
            <div class="content-container">
                <?php if ($user_role === 'eleve'): ?>
                <!-- Résumé des moyennes -->
                <div class="notes-summary">
                    <div class="summary-grid">
                        <div class="summary-card">
                            <div class="summary-icon">
                                <i class="fas fa-calculator"></i>
                            </div>
                            <div class="summary-content">
                                <div class="summary-value moyenne-generale">14,35</div>
                                <div class="summary-label">Moyenne générale</div>
                            </div>
                        </div>
                        
                        <div class="summary-card">
                            <div class="summary-icon">
                                <i class="fas fa-trophy"></i>
                            </div>
                            <div class="summary-content">
                                <div class="summary-value">18,50</div>
                                <div class="summary-label">Meilleure moyenne</div>
                            </div>
                        </div>
                        
                        <div class="summary-card">
                            <div class="summary-icon">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                            <div class="summary-content">
                                <div class="summary-value">9,75</div>
                                <div class="summary-label">Moyenne la plus basse</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Filtres pour la période -->
                <div class="filter-toolbar">
                    <div class="filter-label">Période :</div>
                    <div class="filter-buttons">
                        <button class="btn btn-primary">Trimestre 1</button>
                        <button class="btn btn-secondary">Trimestre 2</button>
                        <button class="btn btn-secondary">Trimestre 3</button>
                        <button class="btn btn-secondary">Année complète</button>
                    </div>
                </div>
                
                <!-- Liste des matières avec les notes -->
                <div class="matieres-list">
                    <!-- Exemple de matière -->
                    <div class="matiere-card">
                        <div class="matiere-header" data-toggle="collapse" data-target="#matiere-1">
                            <div class="matiere-title">
                                <div class="matiere-indicator color-mathematiques"></div>
                                Mathématiques
                            </div>
                            <div class="matiere-moyenne">15,50</div>
                        </div>
                        <div class="matiere-content" id="matiere-1">
                            <div class="notes-list">
                                <div class="note-item">
                                    <div class="note-date">15/09/2023</div>
                                    <div class="note-description">Contrôle sur les fonctions</div>
                                    <div class="note-coefficient">coef. 2</div>
                                    <div class="note-value good">16,50/20</div>
                                </div>
                                <div class="note-item">
                                    <div class="note-date">30/09/2023</div>
                                    <div class="note-description">Interrogation équations</div>
                                    <div class="note-coefficient">coef. 1</div>
                                    <div class="note-value average">14,00/20</div>
                                </div>
                                <div class="note-item">
                                    <div class="note-date">15/10/2023</div>
                                    <div class="note-description">Devoir maison géométrie</div>
                                    <div class="note-coefficient">coef. 0.5</div>
                                    <div class="note-value good">18,00/20</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Exemple de matière 2 -->
                    <div class="matiere-card">
                        <div class="matiere-header" data-toggle="collapse" data-target="#matiere-2">
                            <div class="matiere-title">
                                <div class="matiere-indicator color-francais"></div>
                                Français
                            </div>
                            <div class="matiere-moyenne">13,25</div>
                        </div>
                        <div class="matiere-content" id="matiere-2">
                            <div class="notes-list">
                                <div class="note-item">
                                    <div class="note-date">20/09/2023</div>
                                    <div class="note-description">Dissertation sur Molière</div>
                                    <div class="note-coefficient">coef. 2</div>
                                    <div class="note-value average">12,50/20</div>
                                </div>
                                <div class="note-item">
                                    <div class="note-date">05/10/2023</div>
                                    <div class="note-description">Commentaire de texte</div>
                                    <div class="note-coefficient">coef. 1</div>
                                    <div class="note-value good">15,00/20</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Exemple de matière 3 -->
                    <div class="matiere-card">
                        <div class="matiere-header" data-toggle="collapse" data-target="#matiere-3">
                            <div class="matiere-title">
                                <div class="matiere-indicator color-histoire-geo"></div>
                                Histoire-Géographie
                            </div>
                            <div class="matiere-moyenne">11,75</div>
                        </div>
                        <div class="matiere-content" id="matiere-3">
                            <div class="notes-list">
                                <div class="note-item">
                                    <div class="note-date">18/09/2023</div>
                                    <div class="note-description">Contrôle sur la Seconde Guerre mondiale</div>
                                    <div class="note-coefficient">coef. 2</div>
                                    <div class="note-value average">12,50/20</div>
                                </div>
                                <div class="note-item">
                                    <div class="note-date">08/10/2023</div>
                                    <div class="note-description">Cartographie</div>
                                    <div class="note-coefficient">coef. 1</div>
                                    <div class="note-value bad">9,00/20</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Exemple de matière 4 -->
                    <div class="matiere-card">
                        <div class="matiere-header" data-toggle="collapse" data-target="#matiere-4">
                            <div class="matiere-title">
                                <div class="matiere-indicator color-anglais"></div>
                                Anglais
                            </div>
                            <div class="matiere-moyenne">16,25</div>
                        </div>
                        <div class="matiere-content" id="matiere-4">
                            <div class="notes-list">
                                <div class="note-item">
                                    <div class="note-date">12/09/2023</div>
                                    <div class="note-description">Compréhension orale</div>
                                    <div class="note-coefficient">coef. 1</div>
                                    <div class="note-value good">17,00/20</div>
                                </div>
                                <div class="note-item">
                                    <div class="note-date">25/09/2023</div>
                                    <div class="note-description">Test de grammaire</div>
                                    <div class="note-coefficient">coef. 1</div>
                                    <div class="note-value good">15,50/20</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php elseif ($user_role === 'professeur' || $user_role === 'administrateur'): ?>
                <!-- Interface pour les professeurs et administrateurs -->
                <div class="filter-toolbar">
                    <div class="filter-buttons">
                        <a href="ajouter_note.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Ajouter une note
                        </a>
                        <button class="btn btn-secondary">
                            <i class="fas fa-filter"></i> Filtrer
                        </button>
                        <button class="btn btn-secondary">
                            <i class="fas fa-download"></i> Exporter
                        </button>
                    </div>
                </div>
                
                <!-- Sélection de classe -->
                <div class="class-selector">
                    <div class="form-group">
                        <label for="classe">Sélectionner une classe</label>
                        <select id="classe" class="form-control">
                            <option>6ème A</option>
                            <option>6ème B</option>
                            <option>5ème A</option>
                            <option>5ème B</option>
                            <option>4ème A</option>
                            <option>4ème B</option>
                            <option>3ème A</option>
                            <option>3ème B</option>
                        </select>
                    </div>
                </div>
                
                <!-- Tableau des élèves et leurs notes -->
                <div class="content-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Élève</th>
                                <th>Note 1</th>
                                <th>Note 2</th>
                                <th>Note 3</th>
                                <th>Moyenne</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Dupont Jean</td>
                                <td>15,5/20</td>
                                <td>12/20</td>
                                <td>14/20</td>
                                <td>13,83/20</td>
                                <td class="table-actions">
                                    <button class="btn-icon"><i class="fas fa-edit"></i></button>
                                    <button class="btn-icon btn-danger"><i class="fas fa-trash"></i></button>
                                </td>
                            </tr>
                            <tr>
                                <td>Martin Sophie</td>
                                <td>18/20</td>
                                <td>16,5/20</td>
                                <td>17/20</td>
                                <td>17,17/20</td>
                                <td class="table-actions">
                                    <button class="btn-icon"><i class="fas fa-edit"></i></button>
                                    <button class="btn-icon btn-danger"><i class="fas fa-trash"></i></button>
                                </td>
                            </tr>
                            <tr>
                                <td>Bernard Thomas</td>
                                <td>10/20</td>
                                <td>9,5/20</td>
                                <td>11/20</td>
                                <td>10,17/20</td>
                                <td class="table-actions">
                                    <button class="btn-icon"><i class="fas fa-edit"></i></button>
                                    <button class="btn-icon btn-danger"><i class="fas fa-trash"></i></button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Footer -->
            <div class="footer">
                <div class="footer-content">
                    <div class="footer-links">
                        <a href="#">Mentions Légales</a>
                    </div>
                    <div class="footer-copyright">
                        &copy; <?= date('Y') ?> PRONOTE - Tous droits réservés
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Gestion de l'expansion des matières
        const matiereHeaders = document.querySelectorAll('.matiere-header');
        
        matiereHeaders.forEach(header => {
            header.addEventListener('click', function() {
                const content = this.nextElementSibling;
                if (content.style.display === 'block' || content.style.display === '') {
                    content.style.display = 'none';
                } else {
                    content.style.display = 'block';
                }
            });
        });
    });
    </script>
</body>
</html>