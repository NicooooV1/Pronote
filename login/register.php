<?php
/**
 * Page d'inscription — Version refactorisée.
 * -etablissement.json, -inline JS lourds, +CSRF, +text inputs.
 */
require_once __DIR__ . '/../API/core.php';

// Authentification et droits
requireAuth();
requireRole('administrateur');

$user = getCurrentUser();
$admin_initials = strtoupper(substr($user['prenom'], 0, 1) . substr($user['nom'], 0, 1));

$error = '';
$success = '';
$generatedPassword = '';
$identifiant = '';

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    // Vérification CSRF
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Jeton de sécurité invalide. Veuillez recharger la page.';
    } else {
        $profil = $_POST['profil'] ?? '';

        if ($profil === 'administrateur') {
            $error = "La création de comptes administrateurs n'est pas autorisée.";
        } else {
            try {
                $userData = [
                    'nom'     => $_POST['nom'] ?? '',
                    'prenom'  => $_POST['prenom'] ?? '',
                    'mail'    => $_POST['mail'] ?? '',
                    'adresse' => $_POST['adresse'] ?? ''
                ];

                switch ($profil) {
                    case 'eleve':
                        $userData['date_naissance'] = $_POST['date_naissance'] ?? '';
                        $userData['lieu_naissance'] = $_POST['lieu_naissance'] ?? '';
                        $userData['classe']         = $_POST['classe'] ?? '';
                        break;
                    case 'professeur':
                        $userData['matiere'] = $_POST['matiere'] ?? '';
                        $userData['est_pp']  = $_POST['est_pp'] ?? 'non';
                        break;
                    case 'vie_scolaire':
                        $userData['est_CPE']        = $_POST['est_CPE'] ?? 'non';
                        $userData['est_infirmerie'] = $_POST['est_infirmerie'] ?? 'non';
                        break;
                }

                $result = createUser($profil, $userData);

                if ($result && !empty($result['success'])) {
                    $success = 'Inscription réussie !';
                    $generatedPassword = $result['password'];
                    $identifiant       = $result['identifiant'];
                } else {
                    $error = $result['message'] ?? 'Erreur inconnue lors de la création.';
                }
            } catch (Exception $e) {
                $error = "Erreur lors de la création de l'utilisateur.";
            }
        }
    }
}

$csrfToken = generateCSRFToken();

// Configuration pour les templates partagés
$pageTitle  = 'Ajouter un utilisateur';
$activePage = 'admin';
$isAdmin    = true;
$rootPrefix = '../';
$currentPage = 'register';

// Sidebar admin
ob_start();
?>
        <div class="sidebar-section">
            <div class="sidebar-section-header">ADMINISTRATION</div>
            <div class="sidebar-nav">
                <a href="register.php" class="sidebar-nav-item active">
                    <span class="sidebar-nav-icon"><i class="fas fa-user-plus"></i></span>
                    <span>Ajouter un utilisateur</span>
                </a>
                <a href="../admin/users/index.php" class="sidebar-nav-item">
                    <span class="sidebar-nav-icon"><i class="fas fa-users-cog"></i></span>
                    <span>Gestion des utilisateurs</span>
                </a>
                <a href="../admin/users/passwords.php" class="sidebar-nav-item">
                    <span class="sidebar-nav-icon"><i class="fas fa-key"></i></span>
                    <span>Mots de passe</span>
                </a>
                <a href="../admin/dashboard.php" class="sidebar-nav-item">
                    <span class="sidebar-nav-icon"><i class="fas fa-tachometer-alt"></i></span>
                    <span>Tableau de bord admin</span>
                </a>
            </div>
        </div>
<?php
$sidebarExtraContent = ob_get_clean();

include __DIR__ . '/../templates/shared_header.php';
include __DIR__ . '/../templates/shared_topbar.php';
?>

            <div class="content-wrapper">
            <div class="card">
                <div class="card-header">
                    <h2>Inscription d'un utilisateur</h2>
                </div>

                <div class="card-body">
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-error" role="alert">
                            <i class="fas fa-exclamation-circle" aria-hidden="true"></i>
                            <div><?= htmlspecialchars($error) ?></div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($success)): ?>
                        <div class="alert alert-success" role="status">
                            <h3><i class="fas fa-check-circle" aria-hidden="true"></i> Inscription réussie !</h3>
                            <p>Le compte utilisateur a été créé avec succès.</p>

                            <div class="credentials-info">
                                <p><strong>Identifiant :</strong> <?= htmlspecialchars($identifiant) ?></p>
                                <p><strong>Mot de passe :</strong> <?= htmlspecialchars($generatedPassword) ?></p>
                                <p class="warning">Veuillez communiquer ces informations à l'utilisateur de façon sécurisée.</p>
                            </div>

                            <div class="form-actions">
                                <a href="../accueil/accueil.php" class="btn btn-secondary">
                                    <i class="fas fa-home" aria-hidden="true"></i> Retour à l'accueil
                                </a>
                                <a href="register.php" class="btn btn-primary">
                                    <i class="fas fa-user-plus" aria-hidden="true"></i> Inscrire un autre utilisateur
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <form method="post" action="" class="form" id="registerForm" novalidate>
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

                            <div class="form-group">
                                <label for="profil" class="required-field">Type d'utilisateur</label>
                                <select id="profil" name="profil" class="form-control" required>
                                    <option value="" disabled selected>Choisir…</option>
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
                                <a href="../accueil/accueil.php" class="btn btn-secondary">
                                    <i class="fas fa-times" aria-hidden="true"></i> Annuler
                                </a>
                                <button type="submit" name="submit" class="btn btn-primary" id="submitBtn">
                                    <span class="btn-text"><i class="fas fa-user-plus" aria-hidden="true"></i> Inscrire l'utilisateur</span>
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
document.addEventListener('DOMContentLoaded', function() {
    var profilSelect = document.getElementById('profil');
    var dynContainer = document.getElementById('dynamicFields');
    var form = document.getElementById('registerForm');
    var btn  = document.getElementById('submitBtn');

    if (profilSelect) {
        profilSelect.addEventListener('change', renderFields);
    }

    // Loading state
    if (form && btn) {
        form.addEventListener('submit', function() {
            btn.classList.add('btn-loading');
            btn.disabled = true;
        });
    }

    function renderFields() {
        var profil = profilSelect.value;
        if (!profil) { dynContainer.innerHTML = ''; return; }

        var html = fieldAddress();

        switch (profil) {
            case 'eleve':    html += fieldsEleve();       break;
            case 'parent':   html += fieldsParent();      break;
            case 'professeur': html += fieldsProfesseur();break;
            case 'vie_scolaire': html += fieldsVieScolaire(); break;
        }

        dynContainer.innerHTML = html;
    }

    function fieldAddress() {
        return '<div class="form-group">'
            + '<label for="adresse" class="required-field">Adresse</label>'
            + '<div class="input-group">'
            + '<i class="input-group-icon fas fa-map-marker-alt" aria-hidden="true"></i>'
            + '<input type="text" id="adresse" name="adresse" class="form-control input-with-icon" required>'
            + '</div></div>';
    }

    function fieldsEleve() {
        return '<div class="form-group">'
            + '<label for="date_naissance" class="required-field">Date de naissance</label>'
            + '<div class="input-group">'
            + '<i class="input-group-icon fas fa-calendar-alt" aria-hidden="true"></i>'
            + '<input type="date" id="date_naissance" name="date_naissance" class="form-control input-with-icon" required>'
            + '</div></div>'
            + '<div class="form-group">'
            + '<label for="lieu_naissance" class="required-field">Lieu de naissance</label>'
            + '<div class="input-group">'
            + '<i class="input-group-icon fas fa-map-pin" aria-hidden="true"></i>'
            + '<input type="text" id="lieu_naissance" name="lieu_naissance" class="form-control input-with-icon" required>'
            + '</div></div>'
            + '<div class="form-group">'
            + '<label for="classe" class="required-field">Classe</label>'
            + '<div class="input-group">'
            + '<i class="input-group-icon fas fa-users" aria-hidden="true"></i>'
            + '<input type="text" id="classe" name="classe" class="form-control input-with-icon" required placeholder="Ex : 3A, 2nde B…">'
            + '</div></div>';
    }

    function fieldsParent() {
        return '<div class="form-group">'
            + '<label for="enfant">Nom de l\'enfant (facultatif)</label>'
            + '<div class="input-group">'
            + '<i class="input-group-icon fas fa-child" aria-hidden="true"></i>'
            + '<input type="text" id="enfant" name="enfant" class="form-control input-with-icon" placeholder="Vous pourrez associer l\'enfant plus tard">'
            + '</div></div>';
    }

    function fieldsProfesseur() {
        return '<div class="form-group">'
            + '<label for="matiere" class="required-field">Matière enseignée</label>'
            + '<div class="input-group">'
            + '<i class="input-group-icon fas fa-book" aria-hidden="true"></i>'
            + '<input type="text" id="matiere" name="matiere" class="form-control input-with-icon" required placeholder="Ex : Mathématiques, Français…">'
            + '</div></div>'
            + '<div class="form-group">'
            + '<label for="est_pp">Professeur principal</label>'
            + '<select id="est_pp" name="est_pp" class="form-select">'
            + '<option value="0" selected>Non</option>'
            + '<option value="1">Oui</option>'
            + '</select></div>';
    }

    function fieldsVieScolaire() {
        return '<div class="form-group">'
            + '<label for="est_CPE">CPE</label>'
            + '<select id="est_CPE" name="est_CPE" class="form-select">'
            + '<option value="0" selected>Non</option>'
            + '<option value="1">Oui</option>'
            + '</select></div>'
            + '<div class="form-group">'
            + '<label for="est_infirmerie">Infirmerie</label>'
            + '<select id="est_infirmerie" name="est_infirmerie" class="form-select">'
            + '<option value="0" selected>Non</option>'
            + '<option value="1">Oui</option>'
            + '</select></div>';
    }
});
</script>
            </div>

<?php include __DIR__ . '/../templates/shared_footer.php'; ?>
