<?php
// Démarrer la mise en mémoire tampon de sortie pour éviter l'erreur "headers already sent"
ob_start();

// Inclure l'API centralisée
require_once __DIR__ . '/../API/core.php';

// Vérifier l'authentification et les permissions
requireAuth();
if (!canManageNotes()) {
    header('Location: notes.php');
    exit;
}

// Récupération des informations utilisateur via l'API
$user = getCurrentUser();
$user_role = getUserRole();
$user_fullname = getUserFullName();
$user_initials = getUserInitials();

$pageTitle = 'Ajouter une note';
$pageSubtitle = 'Entrez les informations de la note';

include 'includes/header.php';
?>

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
            
<?php
include 'includes/footer.php';
ob_end_flush();
?>