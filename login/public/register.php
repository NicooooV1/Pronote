<?php
/**
 * Page d'inscription - Version intégrée à l'API centralisée
 */

// Inclure l'API centralisée - chemin uniformisé
require_once __DIR__ . '/../../API/core.php';

// Démarrer la session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Vérifier l'authentification et les droits d'administration
requireAuth();
requireRole('administrateur');

$user = getCurrentUser();
$admin_initials = strtoupper(substr($user['prenom'], 0, 1) . substr($user['nom'], 0, 1));

$error = '';
$success = '';
$generatedPassword = '';
$identifiant = '';

// Traitement du formulaire d'inscription
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    $profil = $_POST['profil'] ?? '';
    
    // Empêcher la création d'administrateurs supplémentaires
    if ($profil === 'administrateur') {
        $error = "La création de comptes administrateurs n'est pas autorisée.";
    } else {
        try {
            // Utiliser l'API centralisée pour créer l'utilisateur
            $userData = [
                'nom' => $_POST['nom'] ?? '',
                'prenom' => $_POST['prenom'] ?? '',
                'mail' => $_POST['mail'] ?? '',
                'adresse' => $_POST['adresse'] ?? ''
            ];
            
            // Ajouter les champs spécifiques selon le profil
            switch ($profil) {
                case 'eleve':
                    $userData['date_naissance'] = $_POST['date_naissance'] ?? '';
                    $userData['lieu_naissance'] = $_POST['lieu_naissance'] ?? '';
                    $userData['classe'] = $_POST['classe'] ?? '';
                    break;
                case 'professeur':
                    $userData['matiere'] = $_POST['matiere'] ?? '';
                    $userData['est_pp'] = $_POST['est_pp'] ?? 'non';
                    break;
                case 'vie_scolaire':
                    $userData['est_CPE'] = $_POST['est_CPE'] ?? 'non';
                    $userData['est_infirmerie'] = $_POST['est_infirmerie'] ?? 'non';
                    break;
            }
            
            // Appel cohérent avec la signature de l'API
            $result = createUser($profil, $userData);
            
            if ($result && isset($result['success']) && $result['success']) {
                $success = 'Inscription réussie !';
                $generatedPassword = $result['password'];
                $identifiant = $result['identifiant'];
                
                // Log de sécurité
                if (function_exists('logUserAction')) {
                    logUserAction('user_created', 'Nouvel utilisateur créé', [
                        'created_user_type' => $profil,
                        'created_user_id' => $identifiant,
                        'created_by' => $user['identifiant']
                    ]);
                }
            } else {
                $error = $result['message'] ?? 'Erreur inconnue lors de la création';
            }
        } catch (Exception $e) {
            $error = "Erreur lors de la création de l'utilisateur.";
            if (function_exists('logError')) {
                logError('User creation error: ' . $e->getMessage());
            }
        }
    }
}

// Charger les données d'établissement
$etablissementData = getEtablissementData();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription - PRONOTE</title>
    <link rel="stylesheet" href="../../assets/css/pronote-theme.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>

<div class="app-container">
    <!-- Sidebar -->
    <div class="sidebar">
        <a href="../../accueil/accueil.php" class="logo-container">
            <div class="app-logo">P</div>
            <div class="app-title">PRONOTE</div>
        </a>
        
        <div class="sidebar-section">
            <div class="sidebar-section-header">Navigation</div>
            <div class="sidebar-nav">
                <a href="../../accueil/accueil.php" class="sidebar-nav-item">
                    <span class="sidebar-nav-icon"><i class="fas fa-home"></i></span>
                    <span>Accueil</span>
                </a>
                <a href="../../notes/notes.php" class="sidebar-nav-item">
                    <span class="sidebar-nav-icon"><i class="fas fa-chart-bar"></i></span>
                    <span>Notes</span>
                </a>
                <a href="../../agenda/agenda.php" class="sidebar-nav-item">
                    <span class="sidebar-nav-icon"><i class="fas fa-calendar"></i></span>
                    <span>Agenda</span>
                </a>
                <a href="../../cahierdetextes/cahierdetextes.php" class="sidebar-nav-item">
                    <span class="sidebar-nav-icon"><i class="fas fa-book"></i></span>
                    <span>Cahier de textes</span>
                </a>
                <a href="../../messagerie/index.php" class="sidebar-nav-item">
                    <span class="sidebar-nav-icon"><i class="fas fa-envelope"></i></span>
                    <span>Messagerie</span>
                </a>
                <a href="../../absences/absences.php" class="sidebar-nav-item">
                    <span class="sidebar-nav-icon"><i class="fas fa-calendar-times"></i></span>
                    <span>Absences</span>
                </a>
            </div>
        </div>
        
        <div class="sidebar-section">
            <div class="sidebar-section-header">Administration</div>
            <div class="sidebar-nav">
                <a href="register.php" class="sidebar-nav-item active">
                    <span class="sidebar-nav-icon"><i class="fas fa-user-plus"></i></span>
                    <span>Ajouter un utilisateur</span>
                </a>
                <a href="../../admin/reset_user_password.php" class="sidebar-nav-item">
                    <span class="sidebar-nav-icon"><i class="fas fa-key"></i></span>
                    <span>Réinitialiser mot de passe</span>
                </a>
                <a href="../../admin/reset_requests.php" class="sidebar-nav-item">
                    <span class="sidebar-nav-icon"><i class="fas fa-clipboard-list"></i></span>
                    <span>Demandes de réinitialisation</span>
                </a>
                <a href="../../admin/admin_accounts.php" class="sidebar-nav-item">
                    <span class="sidebar-nav-icon"><i class="fas fa-user-shield"></i></span>
                    <span>Gestion des administrateurs</span>
                </a>
                <a href="../../admin/user_accounts.php" class="sidebar-nav-item">
                    <span class="sidebar-nav-icon"><i class="fas fa-users-cog"></i></span>
                    <span>Gestion des utilisateurs</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="top-header">
            <div class="page-title">
                <h1>Ajouter un utilisateur</h1>
            </div>
            
            <div class="header-actions">
                <a href="logout.php" class="logout-button" title="Déconnexion">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
                <div class="user-avatar" title="<?= htmlspecialchars($user['prenom'] . ' ' . $user['nom']) ?>">
                    <?= $admin_initials ?>
                </div>
            </div>
        </div>

        <!-- Content -->
        <div class="content-container">
            <div class="card">
                <div class="card-header">
                    <h2>Inscription d'un utilisateur</h2>
                </div>
                
                <div class="card-body">
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-error">
                            <i class="fas fa-exclamation-circle"></i>
                            <div><?= htmlspecialchars($error) ?></div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($success)): ?>
                        <div class="alert alert-success">
                            <h3><i class="fas fa-check-circle"></i> Inscription réussie !</h3>
                            <p>Le compte utilisateur a été créé avec succès.</p>
                            
                            <div class="credentials-info">
                                <p><strong>Identifiant :</strong> <?= htmlspecialchars($identifiant) ?></p>
                                <p><strong>Mot de passe :</strong> <?= htmlspecialchars($generatedPassword) ?></p>
                                <p class="warning">Veuillez communiquer ces informations à l'utilisateur de façon sécurisée.</p>
                            </div>
                            
                            <div class="form-actions">
                                <a href="../../accueil/accueil.php" class="btn btn-secondary">
                                    <i class="fas fa-home"></i> Retour à l'accueil
                                </a>
                                <a href="register.php" class="btn btn-primary">
                                    <i class="fas fa-user-plus"></i> Inscrire un autre utilisateur
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <form method="post" action="" class="form">
                            <div class="form-group">
                                <label for="profil" class="required-field">Type d'utilisateur</label>
                                <select id="profil" name="profil" class="form-control" required onchange="showFields()">
                                    <option value="" disabled selected>Choisir...</option>
                                    <option value="eleve">Élève</option>
                                    <option value="parent">Parent</option>
                                    <option value="professeur">Professeur</option>
                                    <option value="vie_scolaire">Vie Scolaire</option>
                                </select>
                            </div>
                            
                            <div class="required-notice">* Champs obligatoires</div>
                            
                            <div id="commonFields">
                                <div class="form-group">
                                    <label for="nom" class="required-field">Nom</label>
                                    <input type="text" id="nom" name="nom" class="form-control" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="prenom" class="required-field">Prénom</label>
                                    <input type="text" id="prenom" name="prenom" class="form-control" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="mail" class="required-field">Adresse email</label>
                                    <input type="email" id="mail" name="mail" class="form-control" required>
                                </div>
                            </div>
                            
                            <div id="dynamicFields"></div>
                            
                            <div class="form-actions">
                                <a href="../../accueil/accueil.php" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Annuler
                                </a>
                                <button type="submit" name="submit" class="btn btn-primary">
                                    <i class="fas fa-user-plus"></i> Inscrire l'utilisateur
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Fonction pour afficher les champs spécifiques au profil
    function showFields() {
        const profil = document.getElementById('profil').value;
        const dynamicFieldsDiv = document.getElementById('dynamicFields');
        
        if (!profil) return;
        
        let fields = '';
        
        // Champs pour tous les profils avec adresse
        fields += `
            <div class="form-group">
                <label for="adresse" class="required-field">Adresse</label>
                <div class="input-group">
                    <i class="input-group-icon fas fa-map-marker-alt"></i>
                    <input type="text" id="adresse" name="adresse" class="form-control input-with-icon" required>
                </div>
            </div>
        `;
        
        if (profil === 'eleve') {
            fields += `
                <div class="form-group">
                    <label for="date_naissance" class="required-field">Date de naissance</label>
                    <div class="input-group">
                        <i class="input-group-icon fas fa-calendar-alt"></i>
                        <input type="date" id="date_naissance" name="date_naissance" class="form-control input-with-icon" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="lieu_naissance" class="required-field">Lieu de naissance</label>
                    <div class="input-group">
                        <i class="input-group-icon fas fa-map-pin"></i>
                        <input type="text" id="lieu_naissance" name="lieu_naissance" class="form-control input-with-icon" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="classe" class="required-field">Classe</label>
                    <div class="input-group">
                        <i class="input-group-icon fas fa-users"></i>
                        <select id="classe" name="classe" class="form-select" required>
                            <option value="" disabled selected>Choisir...</option>
                            ${getClassesOptions()}
                        </select>
                    </div>
                </div>
            `;
        } else if (profil === 'professeur') {
            fields += `
                <div class="form-group">
                    <label for="matiere" class="required-field">Matière enseignée</label>
                    <div class="input-group">
                        <i class="input-group-icon fas fa-book"></i>
                        <select id="matiere" name="matiere" class="form-select" required>
                            <option value="" disabled selected>Choisir...</option>
                            ${getMatieresOptions()}
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label for="est_pp">Professeur principal</label>
                    <select id="est_pp" name="est_pp" class="form-select">
                        <option value="0" selected>Non</option>
                        <option value="1">Oui</option>
                    </select>
                </div>
            `;
        } else if (profil === 'parent') {
            fields += `
                <div class="form-group">
                    <label for="enfant">Nom de l'enfant (facultatif)</label>
                    <div class="input-group">
                        <i class="input-group-icon fas fa-child"></i>
                        <input type="text" id="enfant" name="enfant" class="form-control input-with-icon" placeholder="Vous pourrez associer l'enfant plus tard">
                    </div>
                </div>
            `;
        } else if (profil === 'vie_scolaire') {
            fields += `
                <div class="form-group">
                    <label for="est_CPE">CPE</label>
                    <select id="est_CPE" name="est_CPE" class="form-select">
                        <option value="0" selected>Non</option>
                        <option value="1">Oui</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="est_infirmerie">Infirmerie</label>
                    <select id="est_infirmerie" name="est_infirmerie" class="form-select">
                        <option value="0" selected>Non</option>
                        <option value="1">Oui</option>
                    </select>
                </div>
            `;
        }
        
        dynamicFieldsDiv.innerHTML = fields;
    }
    
    // Fonction pour générer les options de classes
    function getClassesOptions() {
        const classesData = <?= json_encode($etablissementData['classes'] ?? []) ?>;
        let options = '';
        
        // Parcourir la structure des classes (qui peut avoir plusieurs niveaux)
        for (const niveau in classesData) {
            options += `<optgroup label="${niveau}">`;
            
            for (const sousNiveau in classesData[niveau]) {
                // Si c'est un sous-niveau avec des classes
                if (Array.isArray(classesData[niveau][sousNiveau])) {
                    classesData[niveau][sousNiveau].forEach(classe => {
                        options += `<option value="${classe}">${classe}</option>`;
                    });
                }
            }
            
            options += `</optgroup>`;
        }
        
        return options;
    }
    
    // Fonction pour générer les options de matières
    function getMatieresOptions() {
        const matieresData = <?= json_encode($etablissementData['matieres'] ?? []) ?>;
        let options = '';
        
        matieresData.forEach(matiere => {
            options += `<option value="${matiere.nom}">${matiere.nom} (${matiere.code})</option>`;
        });
        
        return options;
    }
</script>
</body>
</html>