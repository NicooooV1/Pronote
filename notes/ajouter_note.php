<?php
// Démarrer la mise en mémoire tampon de sortie pour éviter l'erreur "headers already sent"
ob_start();

// Démarrer la session si nécessaire
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Vérifier si l'utilisateur est professeur ou administrateur
$user = $_SESSION['user'] ?? null;
$user_role = $user['profil'] ?? '';
if (!in_array($user_role, ['professeur', 'administrateur'])) {
    header('Location: notes.php');
    exit;
}

// Récupération des informations utilisateur
$user_nom = $user['nom'] ?? '';
$user_prenom = $user['prenom'] ?? '';
$user_initials = strtoupper(mb_substr($user_prenom, 0, 1) . mb_substr($user_nom, 0, 1));

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
    <title>Ajouter une note - Pronote</title>
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
            </div>
        </div>
        
        <!-- Contenu principal -->
        <div class="main-content">
            <!-- En-tête -->
            <div class="top-header">
                <div class="page-title">
                    <h1>Ajouter une note</h1>
                    <div class="subtitle">Entrez les informations de la note</div>
                </div>
                
                <div class="header-actions">
                    <a href="../login/public/logout.php" class="logout-button" title="Déconnexion">
                        <i class="fas fa-sign-out-alt"></i>
                    </a>
                    <div class="user-avatar"><?= $user_initials ?></div>
                </div>
            </div>
            
            <!-- Formulaire d'ajout de note -->
            <div class="content-container">
                <form class="form-container">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="classe">Classe <span class="required">*</span></label>
                            <select id="classe" name="classe" class="form-control" required>
                                <option value="">Sélectionnez une classe</option>
                                <option value="6A">6ème A</option>
                                <option value="6B">6ème B</option>
                                <option value="5A">5ème A</option>
                                <option value="5B">5ème B</option>
                                <option value="4A">4ème A</option>
                                <option value="4B">4ème B</option>
                                <option value="3A">3ème A</option>
                                <option value="3B">3ème B</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="matiere">Matière <span class="required">*</span></label>
                            <select id="matiere" name="matiere" class="form-control" required>
                                <option value="">Sélectionnez une matière</option>
                                <option value="francais">Français</option>
                                <option value="mathematiques">Mathématiques</option>
                                <option value="histoire-geo">Histoire-Géographie</option>
                                <option value="anglais">Anglais</option>
                                <option value="espagnol">Espagnol</option>
                                <option value="allemand">Allemand</option>
                                <option value="physique-chimie">Physique-Chimie</option>
                                <option value="svt">SVT</option>
                                <option value="technologie">Technologie</option>
                                <option value="arts">Arts Plastiques</option>
                                <option value="musique">Musique</option>
                                <option value="eps">EPS</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="date_evaluation">Date de l'évaluation <span class="required">*</span></label>
                            <input type="date" id="date_evaluation" name="date_evaluation" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="trimestre">Trimestre <span class="required">*</span></label>
                            <select id="trimestre" name="trimestre" class="form-control" required>
                                <option value="1">1er trimestre</option>
                                <option value="2">2ème trimestre</option>
                                <option value="3">3ème trimestre</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="coefficient">Coefficient</label>
                            <input type="number" id="coefficient" name="coefficient" min="0.5" max="5" step="0.5" value="1" class="form-control">
                        </div>
                        
                        <div class="form-group">
                            <label for="note_sur">Note sur</label>
                            <input type="number" id="note_sur" name="note_sur" min="10" max="100" step="1" value="20" class="form-control">
                        </div>
                        
                        <div class="form-group form-full">
                            <label for="description">Description de l'évaluation <span class="required">*</span></label>
                            <input type="text" id="description" name="description" class="form-control" placeholder="Ex: Contrôle sur les fractions" required>
                        </div>
                    </div>
                    
                    <!-- Tableau d'élèves à noter -->
                    <div class="students-table-container">
                        <h3>Attribuer les notes aux élèves</h3>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Élève</th>
                                    <th>Note</th>
                                    <th>Absence</th>
                                    <th>Commentaire</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Dupont Jean</td>
                                    <td><input type="number" name="notes[1]" min="0" max="20" step="0.25" class="form-control"></td>
                                    <td>
                                        <select name="absence[1]" class="form-control">
                                            <option value="">Non</option>
                                            <option value="abs">Absent</option>
                                            <option value="disp">Dispensé</option>
                                        </select>
                                    </td>
                                    <td><input type="text" name="commentaire[1]" class="form-control"></td>
                                </tr>
                                <tr>
                                    <td>Martin Sophie</td>
                                    <td><input type="number" name="notes[2]" min="0" max="20" step="0.25" class="form-control"></td>
                                    <td>
                                        <select name="absence[2]" class="form-control">
                                            <option value="">Non</option>
                                            <option value="abs">Absent</option>
                                            <option value="disp">Dispensé</option>
                                        </select>
                                    </td>
                                    <td><input type="text" name="commentaire[2]" class="form-control"></td>
                                </tr>
                                <tr>
                                    <td>Bernard Thomas</td>
                                    <td><input type="number" name="notes[3]" min="0" max="20" step="0.25" class="form-control"></td>
                                    <td>
                                        <select name="absence[3]" class="form-control">
                                            <option value="">Non</option>
                                            <option value="abs">Absent</option>
                                            <option value="disp">Dispensé</option>
                                        </select>
                                    </td>
                                    <td><input type="text" name="commentaire[3]" class="form-control"></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="form-actions">
                        <a href="notes.php" class="btn btn-secondary">Annuler</a>
                        <button type="submit" class="btn btn-primary">Enregistrer les notes</button>
                    </div>
                </form>
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
</body>
</html>

<?php
// Terminer la mise en mémoire tampon et envoyer la sortie
ob_end_flush();
?>