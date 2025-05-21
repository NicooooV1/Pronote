<?php
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../src/auth.php';
require __DIR__ . '/../src/user.php';

// Vérifier si la session n'est pas déjà démarrée
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Vérification de sécurité: seuls les administrateurs connectés peuvent accéder à cette page
if (!isset($_SESSION['user']) || 
    !isset($_SESSION['user']['profil']) || 
    $_SESSION['user']['profil'] !== 'administrateur') {
    
    // Journaliser la tentative d'accès non autorisé
    error_log("Tentative d'accès non autorisé à la page d'inscription - IP: " . $_SERVER['REMOTE_ADDR']);
    
    // Rediriger vers la page de connexion
    header("Location: index.php");
    exit;
}

// Récupérer les informations de l'utilisateur administrateur
$admin = $_SESSION['user'];
$admin_initials = strtoupper(mb_substr($admin['prenom'], 0, 1) . mb_substr($admin['nom'], 0, 1));

$auth = new Auth($pdo);
$user = new User($pdo);
$error = '';
$success = '';
$generatedPassword = '';
$identifiant = '';

// Vérifier si la création de comptes administrateurs est autorisée
$adminCreationAllowed = !file_exists(__DIR__ . '/../../admin.lock');

// Chargement des données d'établissement (classes et matières)
$etablissementData = $user->getEtablissementData();

// Traitement du formulaire d'inscription
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    $profil = isset($_POST['profil']) ? $_POST['profil'] : '';
    
    // Bloquer explicitement la création de comptes administrateurs si le fichier de verrouillage existe
    if ($profil === 'administrateur' && !$adminCreationAllowed) {
        $error = "La création de comptes administrateurs est désactivée.";
    } else {
        // Configuration des champs requis par profil
        $requiredFields = [
            'eleve' => ['nom', 'prenom', 'date_naissance', 'lieu_naissance', 'classe', 'adresse', 'mail'],
            'parent' => ['nom', 'prenom', 'mail', 'adresse'],
            'professeur' => ['nom', 'prenom', 'mail', 'adresse', 'matiere'],
            'vie_scolaire' => ['nom', 'prenom', 'mail', 'adresse'],
            'administrateur' => ['nom', 'prenom', 'mail', 'adresse'],
        ];
        
        // Vérifier le profil
        if (!in_array($profil, array_keys($requiredFields))) {
            $error = 'Profil invalide.';
        } else {
            $formData = [];
            $errors = [];
            
            // Vérifier les champs requis
            foreach ($requiredFields[$profil] as $field) {
                if (empty($_POST[$field])) {
                    $errors[] = "Le champ $field est obligatoire.";
                } else {
                    $formData[$field] = $_POST[$field];
                }
            }
            
            // Vérification spécifique pour l'email
            if (!empty($formData['mail']) && !filter_var($formData['mail'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = "Le format de l'adresse email n'est pas valide.";
            }
            
            // Si pas d'erreur, procéder à l'inscription
            if (empty($errors)) {
                // Vérifier si l'utilisateur existe déjà
                if ($user->checkUserExists($profil, $formData)) {
                    $error = 'Un utilisateur avec ces informations existe déjà.';
                } else {
                    // Générer les identifiants et créer l'utilisateur
                    $result = $user->createUser($profil, $formData);
                    
                    if ($result['success']) {
                        $success = 'Inscription réussie !';
                        $generatedPassword = $result['password'];
                        $identifiant = $result['identifiant'];
                        
                        // Journaliser la création d'un nouvel utilisateur
                        error_log("Nouvel utilisateur créé: {$identifiant} (type: {$profil}) par admin: {$admin['identifiant']}");
                        
                        // Effacer les données du formulaire pour éviter une soumission en double
                        unset($formData);
                    } else {
                        $error = $result['message'];
                    }
                }
            } else {
                $error = implode('<br>', $errors);
            }
        }
    }
}

// Titre de la page et informations pour le template
$pageTitle = "Inscription d'un nouvel utilisateur";
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription - PRONOTE</title>
    <link rel="stylesheet" href="../public/assets/css/pronote-login.css">
    <link rel="stylesheet" href="../../assets/css/pronote-theme.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            display: block;
            padding: 0;
            margin: 0;
            min-height: 100vh;
            background-color: var(--background-color);
        }
        
        .register-container {
            box-shadow: none;
            border-radius: 0;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        
        /* Add any additional styles needed for the registration form */
    </style>
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
                <div class="user-avatar" title="<?= htmlspecialchars($_SESSION['user']['prenom'] . ' ' . $_SESSION['user']['nom']) ?>">
                    <?= $admin_initials ?>
                </div>
            </div>
        </div>

        <!-- Original registration form content -->
        <div class="register-container">
            <div class="admin-header">
                <a href="../../accueil/accueil.php" class="backlink">
                    <i class="fas fa-arrow-left"></i> Retour à l'accueil
                </a>
                <div class="admin-info">
                    <span>Admin: <?= htmlspecialchars($admin['prenom'] . ' ' . $admin['nom']) ?></span>
                    <div class="admin-avatar"><?= $admin_initials ?></div>
                </div>
            </div>
            
            <div class="auth-header">
                <div class="app-logo">P</div>
                <h1 class="app-title">Inscription d'un utilisateur</h1>
            </div>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <div><?= $error ?></div>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
                <div class="success-message">
                    <h3><i class="fas fa-check-circle"></i> Inscription réussie !</h3>
                    <p>Le compte utilisateur a été créé avec succès.</p>
                    
                    <div class="credentials-info">
                        <p><strong>Identifiant :</strong> <?= htmlspecialchars($identifiant) ?></p>
                        <p><strong>Mot de passe :</strong> <?= htmlspecialchars($generatedPassword) ?></p>
                        <p class="warning">Veuillez communiquer ces informations à l'utilisateur de façon sécurisée.</p>
                    </div>
                </div>
                
                <div class="form-actions">
                    <a href="../../accueil/accueil.php" class="btn btn-secondary">
                        <i class="fas fa-home"></i> Retour à l'accueil
                    </a>
                    <a href="register.php" class="btn btn-primary">
                        <i class="fas fa-user-plus"></i> Inscrire un autre utilisateur
                    </a>
                </div>
            <?php else: ?>
                <form method="post" action="" class="auth-form">
                    <div class="form-group">
                        <label for="profil" class="required-field">Type d'utilisateur</label>
                        <select id="profil" name="profil" class="form-select" required onchange="showFields()">
                            <option value="" disabled selected>Choisir...</option>
                            <option value="eleve">Élève</option>
                            <option value="parent">Parent</option>
                            <option value="professeur">Professeur</option>
                            <option value="vie_scolaire">Vie Scolaire</option>
                            <?php if ($adminCreationAllowed): ?>
                            <option value="administrateur">Administrateur</option>
                            <?php endif; ?>
                        </select>
                        <?php if (!$adminCreationAllowed): ?>
                            <div class="alert alert-info" style="margin-top: 10px;">
                                <i class="fas fa-info-circle"></i>
                                <div>
                                    <p>La création de nouveaux comptes administrateurs est désactivée car un administrateur principal a déjà été créé.</p>
                                    <p>Les comptes administrateurs existants peuvent être gérés depuis le <a href="../../admin/admin_accounts.php">panneau d'administration</a>.</p>
                                </div>
                            </div>
                        <?php endif; ?>
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
                            <div class="input-group">
                                <i class="input-group-icon fas fa-envelope"></i>
                                <input type="email" id="mail" name="mail" class="form-control input-with-icon" required>
                            </div>
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
        } else if (profil === 'administrateur') {
            fields += `
                <div class="form-group">
                    <label for="role">Rôle administratif</label>
                    <div class="input-group">
                        <i class="input-group-icon fas fa-user-shield"></i>
                        <select id="role" name="role" class="form-select" required>
                            <option value="" disabled selected>Choisir...</option>
                            <option value="direction">Direction</option>
                            <option value="secretariat">Secrétariat</option>
                            <option value="technique">Support technique</option>
                        </select>
                    </div>
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
    
    // Initialiser l'affichage des champs au chargement de la page
    document.addEventListener('DOMContentLoaded', function() {
        const profilSelect = document.getElementById('profil');
        if (profilSelect && profilSelect.value) {
            showFields();
        }
    });
</script>
</body>
</html>