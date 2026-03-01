<?php
/**
 * Gestion des matières — CRUD, couleurs, coefficients
 */
require_once __DIR__ . '/../../API/core.php';
require_once __DIR__ . '/../includes/admin_functions.php';

requireAuth();
requireRole('administrateur');

$pdo = getPDO();
$admin = getCurrentUser();
$message = '';
$error = '';

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// POST Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['csrf_token'] ?? '') === $csrf_token) {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $nom = trim($_POST['nom'] ?? '');
        $code = trim(strtoupper($_POST['code'] ?? ''));
        $coef = floatval($_POST['coefficient'] ?? 1);
        $couleur = $_POST['couleur'] ?? '#3498db';
        if (!empty($nom) && !empty($code)) {
            try {
                $pdo->prepare("INSERT INTO matieres (nom, code, coefficient, couleur) VALUES (?,?,?,?)")->execute([$nom, $code, $coef, $couleur]);
                logAudit('matiere_created', 'matieres', $pdo->lastInsertId());
                $message = "Matière « $nom » créée.";
            } catch (PDOException $e) {
                $error = str_contains($e->getMessage(), 'Duplicate') ? "Ce code existe déjà." : $e->getMessage();
            }
        }
    }

    if ($action === 'edit') {
        $mid = intval($_POST['matiere_id'] ?? 0);
        $nom = trim($_POST['nom'] ?? '');
        $code = trim(strtoupper($_POST['code'] ?? ''));
        $coef = floatval($_POST['coefficient'] ?? 1);
        $couleur = $_POST['couleur'] ?? '#3498db';
        $actif = isset($_POST['actif']) ? 1 : 0;
        if ($mid > 0 && !empty($nom)) {
            $pdo->prepare("UPDATE matieres SET nom = ?, code = ?, coefficient = ?, couleur = ?, actif = ? WHERE id = ?")->execute([$nom, $code, $coef, $couleur, $actif, $mid]);
            logAudit('matiere_edited', 'matieres', $mid);
            $message = "Matière modifiée.";
        }
    }

    if ($action === 'delete') {
        $mid = intval($_POST['matiere_id'] ?? 0);
        if ($mid > 0) {
            $noteCount = $pdo->prepare("SELECT COUNT(*) FROM notes WHERE id_matiere = ?"); $noteCount->execute([$mid]);
            if ($noteCount->fetchColumn() > 0) {
                $error = "Impossible : des notes sont liées à cette matière. Désactivez-la plutôt.";
            } else {
                $pdo->prepare("DELETE FROM matieres WHERE id = ?")->execute([$mid]);
                logAudit('matiere_deleted', 'matieres', $mid);
                $message = "Matière supprimée.";
            }
        }
    }

    if ($action === 'toggle_active') {
        $mid = intval($_POST['matiere_id'] ?? 0);
        if ($mid > 0) {
            $pdo->prepare("UPDATE matieres SET actif = NOT actif WHERE id = ?")->execute([$mid]);
            logAudit('matiere_toggled', 'matieres', $mid);
            $message = "Statut modifié.";
        }
    }
}

$matieres = $pdo->query("SELECT m.*, (SELECT COUNT(*) FROM notes n WHERE n.id_matiere = m.id) AS note_count FROM matieres m ORDER BY m.actif DESC, m.nom")->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Matières';
$currentPage = 'etab_matieres';
$extraCss = ['../../assets/css/admin.css'];

ob_start();
?>
<style>
    .mat-container { max-width: 900px; margin: 0 auto; }
    .mat-table { width: 100%; border-collapse: collapse; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
    .mat-table th, .mat-table td { padding: 10px 14px; text-align: left; border-bottom: 1px solid #f0f0f0; font-size: 13px; }
    .mat-table th { background: #f7fafc; font-weight: 600; color: #4a5568; font-size: 12px; }
    .mat-table tr.inactive { opacity: 0.5; }
    .color-dot { display: inline-block; width: 14px; height: 14px; border-radius: 50%; vertical-align: middle; margin-right: 6px; }
</style>
<?php
$extraHeadHtml = ob_get_clean();
include __DIR__ . '/../includes/sub_header.php';
?>

<div class="mat-container">
    <?php if (!empty($message)): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if (!empty($error)): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <div class="top-bar">
        <button class="btn btn-primary" onclick="document.getElementById('createModal').classList.add('active')"><i class="fas fa-plus"></i> Nouvelle matière</button>
    </div>

    <table class="mat-table">
        <thead><tr><th>Matière</th><th>Code</th><th>Coef</th><th>Notes</th><th>Statut</th><th>Actions</th></tr></thead>
        <tbody>
            <?php foreach ($matieres as $m): ?>
            <tr class="<?= $m['actif'] ? '' : 'inactive' ?>">
                <td><span class="color-dot" style="background:<?= htmlspecialchars($m['couleur']) ?>"></span><strong><?= htmlspecialchars($m['nom']) ?></strong></td>
                <td><code><?= htmlspecialchars($m['code']) ?></code></td>
                <td><?= $m['coefficient'] ?></td>
                <td><?= $m['note_count'] ?></td>
                <td><?= $m['actif'] ? '<span style="color:#059669">Active</span>' : '<span style="color:#dc2626">Inactive</span>' ?></td>
                <td>
                    <button class="btn-xs primary" onclick='openEdit(<?= json_encode($m) ?>)'><i class="fas fa-pen"></i></button>
                    <form method="post" style="display:inline"><input type="hidden" name="csrf_token" value="<?= $csrf_token ?>"><input type="hidden" name="action" value="toggle_active"><input type="hidden" name="matiere_id" value="<?= $m['id'] ?>"><button class="btn-xs <?= $m['actif'] ? 'warning' : 'success' ?>" title="<?= $m['actif'] ? 'Désactiver' : 'Activer' ?>"><i class="fas fa-<?= $m['actif'] ? 'eye-slash' : 'eye' ?>"></i></button></form>
                    <form method="post" style="display:inline" onsubmit="return confirm('Supprimer ?')"><input type="hidden" name="csrf_token" value="<?= $csrf_token ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="matiere_id" value="<?= $m['id'] ?>"><button class="btn-xs danger"><i class="fas fa-trash"></i></button></form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Modal Créer -->
<div class="modal-overlay" id="createModal">
    <div class="modal-box">
        <h3><i class="fas fa-plus"></i> Nouvelle matière</h3>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>"><input type="hidden" name="action" value="create">
            <div class="form-group"><label>Nom</label><input type="text" name="nom" placeholder="Mathématiques" required></div>
            <div class="form-row">
                <div class="form-group"><label>Code</label><input type="text" name="code" placeholder="MATH" maxlength="10" required></div>
                <div class="form-group"><label>Coefficient</label><input type="number" name="coefficient" value="1" step="0.01" min="0.01"></div>
            </div>
            <div class="form-group"><label>Couleur</label><input type="color" name="couleur" value="#3498db"></div>
            <div style="display:flex;gap:8px;justify-content:flex-end"><button type="button" class="btn btn-secondary" onclick="document.getElementById('createModal').classList.remove('active')">Annuler</button><button type="submit" class="btn btn-primary">Créer</button></div>
        </form>
    </div>
</div>

<!-- Modal Modifier -->
<div class="modal-overlay" id="editModal">
    <div class="modal-box">
        <h3><i class="fas fa-pen"></i> Modifier la matière</h3>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>"><input type="hidden" name="action" value="edit"><input type="hidden" name="matiere_id" id="e_mid">
            <div class="form-group"><label>Nom</label><input type="text" name="nom" id="e_nom" required></div>
            <div class="form-row">
                <div class="form-group"><label>Code</label><input type="text" name="code" id="e_code" maxlength="10" required></div>
                <div class="form-group"><label>Coefficient</label><input type="number" name="coefficient" id="e_coef" step="0.01"></div>
            </div>
            <div class="form-group"><label>Couleur</label><input type="color" name="couleur" id="e_couleur"></div>
            <div class="form-group"><label><input type="checkbox" name="actif" id="e_actif"> Active</label></div>
            <div style="display:flex;gap:8px;justify-content:flex-end"><button type="button" class="btn btn-secondary" onclick="document.getElementById('editModal').classList.remove('active')">Annuler</button><button type="submit" class="btn btn-primary">Enregistrer</button></div>
        </form>
    </div>
</div>

<script>
function openEdit(m) {
    document.getElementById('e_mid').value = m.id;
    document.getElementById('e_nom').value = m.nom;
    document.getElementById('e_code').value = m.code;
    document.getElementById('e_coef').value = m.coefficient;
    document.getElementById('e_couleur').value = m.couleur;
    document.getElementById('e_actif').checked = !!parseInt(m.actif);
    document.getElementById('editModal').classList.add('active');
}
document.querySelectorAll('.modal-overlay').forEach(m => m.addEventListener('click', e => { if (e.target === m) m.classList.remove('active'); }));
</script>

<?php include __DIR__ . '/../includes/sub_footer.php'; ?>
