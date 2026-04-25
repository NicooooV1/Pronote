<?php
/**
 * Fiche établissement — modifier les informations générales
 */
require_once __DIR__ . '/../../API/core.php';
require_once __DIR__ . '/../includes/admin_functions.php';

requireAuth();
requireRole('administrateur');

$pdo = getPDO();
$admin = getCurrentUser();
$message = '';

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Charger les infos
$etab = $pdo->query("SELECT * FROM etablissements WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
if (!$etab) {
    $pdo->exec("INSERT INTO etablissements (id, nom) VALUES (1, 'Établissement Scolaire')");
    $etab = $pdo->query("SELECT * FROM etablissements WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
}

// POST : mise à jour
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['csrf_token'] ?? '') === $csrf_token) {
    $fields = ['nom', 'adresse', 'code_postal', 'ville', 'telephone', 'email', 'academie', 'type'];
    // Champs optionnels ajoutés par migration
    $optFields = ['chef_etablissement', 'code_uai', 'fax', 'annee_scolaire'];
    $allFields = $fields;

    // Vérifier quels champs optionnels existent
    foreach ($optFields as $of) {
        if (array_key_exists($of, $etab)) {
            $allFields[] = $of;
        }
    }

    $sets = [];
    $params = [];
    foreach ($allFields as $f) {
        $sets[] = "$f = ?";
        $params[] = trim($_POST[$f] ?? ($etab[$f] ?? ''));
    }
    $params[] = 1;

    $pdo->prepare("UPDATE etablissements SET " . implode(', ', $sets) . " WHERE id = ?")->execute($params);
    logAudit('etablissement_updated', 'etablissements', 1);
    $message = "Informations mises à jour.";
    $etab = $pdo->query("SELECT * FROM etablissements WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
}

$pageTitle = 'Fiche établissement';
$currentPage = 'etab_info';
$extraCss = ['../../assets/css/admin.css'];

ob_start();
?>
<style>
    .etab-container { max-width: 800px; margin: 0 auto; }
    .form-card { background: white; border-radius: 10px; padding: 30px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
    .form-card h3 { margin: 0 0 20px; font-size: 18px; border-bottom: 1px solid #eee; padding-bottom: 10px; }
    .section-label { font-size: 14px; font-weight: 600; color: #0f4c81; margin: 20px 0 10px; padding-top: 15px; border-top: 1px solid #eee; }
</style>
<?php
$extraHeadHtml = ob_get_clean();
include __DIR__ . '/../includes/header.php';
?>

<div class="etab-container">
    <?php if (!empty($message)): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>

    <div class="form-card">
        <h3><i class="fas fa-school"></i> Informations de l'établissement</h3>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">

            <div class="form-group">
                <label>Nom de l'établissement</label>
                <input type="text" name="nom" value="<?= htmlspecialchars($etab['nom'] ?? '') ?>" required>
            </div>

            <div class="form-row">
                <div class="form-group"><label>Type</label>
                    <select name="type">
                        <option value="college" <?= ($etab['type'] ?? '') === 'college' ? 'selected' : '' ?>>Collège</option>
                        <option value="lycee" <?= ($etab['type'] ?? '') === 'lycee' ? 'selected' : '' ?>>Lycée</option>
                        <option value="lycee_pro" <?= ($etab['type'] ?? '') === 'lycee_pro' ? 'selected' : '' ?>>Lycée professionnel</option>
                        <option value="autre" <?= ($etab['type'] ?? '') === 'autre' ? 'selected' : '' ?>>Autre</option>
                    </select>
                </div>
                <div class="form-group"><label>Académie</label><input type="text" name="academie" value="<?= htmlspecialchars($etab['academie'] ?? '') ?>"></div>
            </div>

            <?php if (array_key_exists('chef_etablissement', $etab)): ?>
            <div class="form-row">
                <div class="form-group"><label>Chef d'établissement</label><input type="text" name="chef_etablissement" value="<?= htmlspecialchars($etab['chef_etablissement'] ?? '') ?>"></div>
                <div class="form-group"><label>Code UAI</label><input type="text" name="code_uai" value="<?= htmlspecialchars($etab['code_uai'] ?? '') ?>"></div>
            </div>
            <?php endif; ?>

            <div class="section-label"><i class="fas fa-map-marker-alt"></i> Coordonnées</div>

            <div class="form-group"><label>Adresse</label><input type="text" name="adresse" value="<?= htmlspecialchars($etab['adresse'] ?? '') ?>"></div>
            <div class="form-row">
                <div class="form-group"><label>Code postal</label><input type="text" name="code_postal" value="<?= htmlspecialchars($etab['code_postal'] ?? '') ?>"></div>
                <div class="form-group"><label>Ville</label><input type="text" name="ville" value="<?= htmlspecialchars($etab['ville'] ?? '') ?>"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Téléphone</label><input type="tel" name="telephone" value="<?= htmlspecialchars($etab['telephone'] ?? '') ?>"></div>
                <div class="form-group"><label>Email</label><input type="email" name="email" value="<?= htmlspecialchars($etab['email'] ?? '') ?>"></div>
            </div>
            <?php if (array_key_exists('fax', $etab)): ?>
            <div class="form-row">
                <div class="form-group"><label>Fax</label><input type="text" name="fax" value="<?= htmlspecialchars($etab['fax'] ?? '') ?>"></div>
                <div class="form-group"><label>Année scolaire</label><input type="text" name="annee_scolaire" value="<?= htmlspecialchars($etab['annee_scolaire'] ?? '') ?>" placeholder="2024-2025"></div>
            </div>
            <?php endif; ?>

            <div style="margin-top:20px;text-align:right">
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
