<?php
/**
 * Ajouter un utilisateur — Formulaire dynamique selon le type
 */
require_once __DIR__ . '/../../API/core.php';
require_once __DIR__ . '/../includes/admin_functions.php';

requireAuth();
requireRole('administrateur');

$pdo = getPDO();
$userObj = new User($pdo);

$message = '';
$error = '';
$generatedPassword = '';
$generatedIdentifier = '';

// CSRF
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Charger classes et matières
$classesList = [];
$matieresList = [];
try {
    $classesList = $pdo->query("SELECT nom FROM classes WHERE actif = 1 ORDER BY niveau, nom")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {}
try {
    $matieresList = $pdo->query("SELECT nom FROM matieres WHERE actif = 1 ORDER BY nom")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token']) && $_POST['csrf_token'] === $csrf_token) {
    $profil = $_POST['profil'] ?? '';
    
    $data = [
        'nom' => trim($_POST['nom'] ?? ''),
        'prenom' => trim($_POST['prenom'] ?? ''),
        'mail' => trim($_POST['mail'] ?? ''),
    ];

    // Champs optionnels communs
    if (isset($_POST['adresse']) && $_POST['adresse'] !== '') $data['adresse'] = trim($_POST['adresse']);
    if (isset($_POST['telephone']) && $_POST['telephone'] !== '') $data['telephone'] = trim($_POST['telephone']);

    // Champs spécifiques
    if ($profil === 'eleve') {
        $data['date_naissance'] = $_POST['date_naissance'] ?? '';
        $data['lieu_naissance'] = trim($_POST['lieu_naissance'] ?? '');
        $data['classe'] = $_POST['classe'] ?? '';
        if (empty($data['adresse'])) $data['adresse'] = '';
    }
    if ($profil === 'professeur') {
        $data['matiere'] = $_POST['matiere'] ?? '';
        $data['professeur_principal'] = $_POST['professeur_principal'] ?? 'non';
        if (empty($data['adresse'])) $data['adresse'] = '';
    }
    if ($profil === 'parent') {
        $data['metier'] = trim($_POST['metier'] ?? '');
        $data['est_parent_eleve'] = $_POST['est_parent_eleve'] ?? 'non';
        if (empty($data['adresse'])) $data['adresse'] = '';
    }
    if ($profil === 'vie_scolaire') {
        $data['est_CPE'] = $_POST['est_CPE'] ?? 'non';
        $data['est_infirmerie'] = $_POST['est_infirmerie'] ?? 'non';
    }

    if (empty($data['nom']) || empty($data['prenom']) || empty($data['mail'])) {
        $error = "Le nom, le prénom et l'email sont obligatoires.";
    } elseif (empty($profil)) {
        $error = "Veuillez sélectionner un type de profil.";
    } else {
        $result = $userObj->createUser($profil, $data);
        if ($result['success']) {
            $generatedPassword = $result['password'] ?? '';
            $generatedIdentifier = $result['identifiant'] ?? '';
            logAudit('user_created', $userObj->getTableName($profil), null, null, ['identifiant' => $generatedIdentifier, 'profil' => $profil]);
            $message = "Utilisateur créé avec succès.";
        } else {
            $error = $result['message'] ?? 'Erreur lors de la création.';
        }
    }
}

$pageTitle = 'Ajouter un utilisateur';
$currentPage = 'users_create';
$extraCss = ['../../assets/css/admin.css'];

ob_start();
?>
<style>
    .create-container { max-width: 800px; margin: 0 auto; }
    .form-card { background: white; border-radius: 10px; padding: 25px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); margin-bottom: 20px; }
    .form-card h3 { margin: 0 0 20px; font-size: 16px; color: #2d3748; }
    .form-group input:focus, .form-group select:focus { border-color: #0f4c81; outline: none; box-shadow: 0 0 0 3px rgba(15,76,129,0.1); }
    .profil-selector { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 20px; }
    .profil-btn { padding: 10px 18px; border: 2px solid #e2e8f0; border-radius: 8px; background: white; cursor: pointer; font-size: 14px; transition: all 0.15s; display: flex; align-items: center; gap: 8px; }
    .profil-btn:hover { border-color: #0f4c81; }
    .profil-btn.selected { border-color: #0f4c81; background: #eff6ff; color: #0f4c81; font-weight: 600; }
    .dynamic-fields { display: none; }
    .dynamic-fields.visible { display: block; }
    .credentials-box { background: #f0fdf4; border: 2px dashed #059669; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
    .credentials-box h4 { margin: 0 0 10px; color: #059669; }
    .credentials-box code { background: #e2e8f0; padding: 2px 8px; border-radius: 4px; font-size: 15px; }
</style>
<?php
$extraHeadHtml = ob_get_clean();
include __DIR__ . '/../includes/sub_header.php';
?>

<div class="create-container">
    <?php if (!empty($message) && !empty($generatedPassword)): ?>
        <div class="credentials-box">
            <h4><i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?></h4>
            <p><strong>Identifiant :</strong> <code><?= htmlspecialchars($generatedIdentifier) ?></code></p>
            <p><strong>Mot de passe :</strong> <code><?= htmlspecialchars($generatedPassword) ?></code></p>
            <p style="font-size:13px;color:#666;margin-top:10px;"><i class="fas fa-exclamation-triangle"></i> Communiquez ces informations à l'utilisateur. Le mot de passe ne sera plus affiché.</p>
        </div>
    <?php elseif (!empty($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post" class="form-card" id="createForm">
        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
        
        <h3><i class="fas fa-user-tag"></i> Type de profil</h3>
        <div class="profil-selector">
            <button type="button" class="profil-btn" data-profil="eleve" onclick="selectProfil('eleve')"><i class="fas fa-user-graduate"></i> Élève</button>
            <button type="button" class="profil-btn" data-profil="professeur" onclick="selectProfil('professeur')"><i class="fas fa-chalkboard-teacher"></i> Professeur</button>
            <button type="button" class="profil-btn" data-profil="parent" onclick="selectProfil('parent')"><i class="fas fa-users"></i> Parent</button>
            <button type="button" class="profil-btn" data-profil="vie_scolaire" onclick="selectProfil('vie_scolaire')"><i class="fas fa-user-tie"></i> Vie scolaire</button>
            <button type="button" class="profil-btn" data-profil="administrateur" onclick="selectProfil('administrateur')"><i class="fas fa-user-shield"></i> Administrateur</button>
        </div>
        <input type="hidden" name="profil" id="profilInput" value="">

        <!-- Champs communs -->
        <h3><i class="fas fa-id-card"></i> Informations générales</h3>
        <div class="form-row">
            <div class="form-group"><label>Nom *</label><input type="text" name="nom" required></div>
            <div class="form-group"><label>Prénom *</label><input type="text" name="prenom" required></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label>Email *</label><input type="email" name="mail" required></div>
            <div class="form-group"><label>Téléphone</label><input type="text" name="telephone" placeholder="06 12 34 56 78"></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label>Adresse</label><input type="text" name="adresse"></div>
        </div>

        <!-- Champs élève -->
        <div class="dynamic-fields" id="fields-eleve">
            <h3><i class="fas fa-user-graduate"></i> Informations élève</h3>
            <div class="form-row">
                <div class="form-group"><label>Classe *</label>
                    <select name="classe">
                        <option value="">Sélectionner…</option>
                        <?php foreach ($classesList as $c): ?>
                        <option value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group"><label>Date de naissance *</label><input type="date" name="date_naissance"></div>
                <div class="form-group"><label>Lieu de naissance</label><input type="text" name="lieu_naissance"></div>
            </div>
        </div>

        <!-- Champs professeur -->
        <div class="dynamic-fields" id="fields-professeur">
            <h3><i class="fas fa-chalkboard-teacher"></i> Informations professeur</h3>
            <div class="form-row">
                <div class="form-group"><label>Matière *</label>
                    <select name="matiere">
                        <option value="">Sélectionner…</option>
                        <?php foreach ($matieresList as $m): ?>
                        <option value="<?= htmlspecialchars($m) ?>"><?= htmlspecialchars($m) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group"><label>Professeur principal</label>
                    <select name="professeur_principal">
                        <option value="non">Non</option>
                        <option value="oui">Oui</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Champs parent -->
        <div class="dynamic-fields" id="fields-parent">
            <h3><i class="fas fa-users"></i> Informations parent</h3>
            <div class="form-row">
                <div class="form-group"><label>Métier</label><input type="text" name="metier"></div>
                <div class="form-group"><label>Parent d'élève</label>
                    <select name="est_parent_eleve"><option value="non">Non</option><option value="oui">Oui</option></select>
                </div>
            </div>
        </div>

        <!-- Champs vie scolaire -->
        <div class="dynamic-fields" id="fields-vie_scolaire">
            <h3><i class="fas fa-user-tie"></i> Informations vie scolaire</h3>
            <div class="form-row">
                <div class="form-group"><label>CPE</label><select name="est_CPE"><option value="non">Non</option><option value="oui">Oui</option></select></div>
                <div class="form-group"><label>Infirmerie</label><select name="est_infirmerie"><option value="non">Non</option><option value="oui">Oui</option></select></div>
            </div>
        </div>

        <div style="margin-top: 20px;">
            <button type="submit" class="btn btn-primary"><i class="fas fa-user-plus"></i> Créer l'utilisateur</button>
        </div>
    </form>
</div>

<script>
function selectProfil(profil) {
    document.getElementById('profilInput').value = profil;
    document.querySelectorAll('.profil-btn').forEach(b => b.classList.remove('selected'));
    document.querySelector(`.profil-btn[data-profil="${profil}"]`).classList.add('selected');
    document.querySelectorAll('.dynamic-fields').forEach(f => f.classList.remove('visible'));
    const fields = document.getElementById('fields-' + profil);
    if (fields) fields.classList.add('visible');
}
</script>

<?php include __DIR__ . '/../includes/sub_footer.php'; ?>
