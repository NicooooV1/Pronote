<?php
/**
 * Gestion des administrateurs — Édition info, MDP, 2FA, déverrouillage
 */
require_once __DIR__ . '/../../API/core.php';
require_once __DIR__ . '/../includes/admin_functions.php';
require_once __DIR__ . '/../../login/src/auth.php';
require_once __DIR__ . '/../../login/src/user.php';

requireAuth();
requireRole('administrateur');

$pdo = getPDO();
$currentUser = getCurrentUser();
$userObj = new User($pdo);

$message = '';
$error = '';

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['csrf_token'] ?? '') === $csrf_token) {
    $action = $_POST['action'] ?? '';
    $adminId = intval($_POST['admin_id'] ?? 0);

    if ($action === 'change_password' && $adminId > 0) {
        $newPwd = trim($_POST['new_password'] ?? '');
        $confirmPwd = trim($_POST['confirm_password'] ?? '');
        if (empty($newPwd) || strlen($newPwd) < 8) {
            $error = "Le mot de passe doit contenir au moins 8 caractères.";
        } elseif ($newPwd !== $confirmPwd) {
            $error = "Les mots de passe ne correspondent pas.";
        } else {
            if ($userObj->changePassword('administrateur', $adminId, $newPwd)) {
                logAudit('admin_password_changed', 'administrateurs', $adminId);
                $message = "Mot de passe modifié avec succès.";
            } else { $error = "Erreur : " . $userObj->getErrorMessage(); }
        }
    }

    if ($action === 'toggle_active' && $adminId > 0) {
        $newActive = intval($_POST['new_active'] ?? 1);
        // Ne pas désactiver son propre compte
        if ($adminId == ($currentUser['id'] ?? 0) && $newActive == 0) {
            $error = "Vous ne pouvez pas désactiver votre propre compte.";
        } else {
            $stmt = $pdo->prepare("UPDATE administrateurs SET actif = ? WHERE id = ?");
            if ($stmt->execute([$newActive, $adminId])) {
                logAudit($newActive ? 'admin_activated' : 'admin_deactivated', 'administrateurs', $adminId);
                $message = $newActive ? "Compte activé." : "Compte désactivé.";
            } else { $error = "Erreur lors de la modification du statut."; }
        }
    }

    if ($action === 'unlock' && $adminId > 0) {
        $stmt = $pdo->prepare("UPDATE administrateurs SET locked_until = NULL, failed_login_attempts = 0 WHERE id = ?");
        if ($stmt->execute([$adminId])) {
            logAudit('admin_unlocked', 'administrateurs', $adminId);
            $message = "Compte déverrouillé.";
        }
    }

    if ($action === 'edit_info' && $adminId > 0) {
        $fields = ['nom','prenom','mail','adresse'];
        $sets = []; $params = [];
        foreach ($fields as $f) {
            if (isset($_POST[$f])) { $sets[] = "`$f` = ?"; $params[] = trim($_POST[$f]); }
        }
        if (!empty($sets)) {
            $params[] = $adminId;
            try {
                $stmt = $pdo->prepare("UPDATE administrateurs SET " . implode(', ', $sets) . " WHERE id = ?");
                if ($stmt->execute($params)) {
                    logAudit('admin_info_updated', 'administrateurs', $adminId);
                    $message = "Informations mises à jour.";
                }
            } catch (PDOException $e) { $error = "Erreur : " . $e->getMessage(); }
        }
    }

    if ($action === 'toggle_2fa' && $adminId > 0) {
        $current = $pdo->prepare("SELECT two_factor_enabled FROM administrateurs WHERE id = ?");
        $current->execute([$adminId]); $was = (int)$current->fetchColumn();
        $new2fa = $was ? 0 : 1;
        $stmt = $pdo->prepare("UPDATE administrateurs SET two_factor_enabled = ?" . ($new2fa ? '' : ", two_factor_secret = NULL") . " WHERE id = ?");
        $stmt->execute([$new2fa, $adminId]);
        logAudit($new2fa ? '2fa_enabled' : '2fa_disabled', 'administrateurs', $adminId);
        $message = $new2fa ? "2FA activé." : "2FA désactivé et secret réinitialisé.";
    }
}

// Récupérer les admins
$admins = [];
try {
    $stmt = $pdo->query("SELECT * FROM administrateurs ORDER BY nom, prenom");
    $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $error = "Erreur : " . $e->getMessage(); }

$pageTitle = 'Gestion des administrateurs';
$currentPage = 'admins';

ob_start();
?>
<style>
    .admins-container { max-width: 1000px; margin: 0 auto; }
    .admin-table { width: 100%; border-collapse: collapse; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
    .admin-table th, .admin-table td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #f0f0f0; font-size: 14px; }
    .admin-table th { background: #f7fafc; color: #4a5568; font-weight: 600; font-size: 13px; }
    .admin-table tr:hover { background: #f9fafb; }
    .admin-table tr.current-user { background: #eff6ff; }
    .btn-xs { padding: 4px 8px; font-size: 12px; border: none; border-radius: 4px; cursor: pointer; color: white; }
    .btn-xs.primary { background: #0f4c81; }
    .btn-xs.success { background: #059669; }
    .btn-xs.warning { background: #d97706; }
    .btn-xs.danger { background: #dc2626; }
    .actions-cell { display: flex; gap: 4px; flex-wrap: wrap; }
    .status-active { color: #059669; font-weight: 600; }
    .status-inactive { color: #dc2626; }
    .status-locked { color: #d97706; }
    .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; }
    .modal-overlay.show { display: flex; }
    .modal-box { background: white; border-radius: 12px; width: 95%; max-width: 500px; padding: 25px; box-shadow: 0 20px 60px rgba(0,0,0,0.25); }
    .modal-box h2 { margin: 0 0 20px; font-size: 18px; }
    .modal-close { float: right; background: none; border: none; font-size: 22px; cursor: pointer; color: #666; }
    .form-group { margin-bottom: 12px; }
    .form-group label { display: block; font-size: 13px; font-weight: 500; margin-bottom: 4px; color: #4a5568; }
    .form-group input { width: 100%; padding: 8px 10px; border: 1px solid #d2d6dc; border-radius: 6px; font-size: 14px; box-sizing: border-box; }
    .two-fa { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 600; }
    .two-fa.on { background: #d1fae5; color: #065f46; }
    .two-fa.off { background: #fee2e2; color: #991b1b; }
</style>
<?php
$extraHeadHtml = ob_get_clean();
include __DIR__ . '/../includes/sub_header.php';
?>

<div class="admins-container">
    <?php if (!empty($message)): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if (!empty($error)): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <table class="admin-table">
        <thead>
            <tr><th>Nom</th><th>Identifiant</th><th>Email</th><th>2FA</th><th>Statut</th><th>Dernière connexion</th><th>Actions</th></tr>
        </thead>
        <tbody>
            <?php foreach ($admins as $a): ?>
            <tr class="<?= ($a['id'] == ($currentUser['id'] ?? 0)) ? 'current-user' : '' ?>">
                <td><strong><?= htmlspecialchars($a['prenom'] . ' ' . $a['nom']) ?></strong><?= ($a['id'] == ($currentUser['id'] ?? 0)) ? ' <em>(vous)</em>' : '' ?></td>
                <td><code><?= htmlspecialchars($a['identifiant']) ?></code></td>
                <td><?= htmlspecialchars($a['mail']) ?></td>
                <td><span class="two-fa <?= ($a['two_factor_enabled'] ?? 0) ? 'on' : 'off' ?>"><?= ($a['two_factor_enabled'] ?? 0) ? 'Activé' : 'Désactivé' ?></span></td>
                <td>
                    <?php if (!empty($a['locked_until']) && strtotime($a['locked_until']) > time()): ?>
                        <span class="status-locked"><i class="fas fa-lock"></i> Verrouillé</span>
                    <?php elseif ($a['actif'] ?? 1): ?>
                        <span class="status-active"><i class="fas fa-check-circle"></i> Actif</span>
                    <?php else: ?>
                        <span class="status-inactive"><i class="fas fa-times-circle"></i> Inactif</span>
                    <?php endif; ?>
                </td>
                <td style="font-size:13px;color:#718096;"><?= !empty($a['last_login']) ? date('d/m/Y H:i', strtotime($a['last_login'])) : '<em>Jamais</em>' ?></td>
                <td>
                    <div class="actions-cell">
                        <button class="btn-xs primary" onclick="openEdit(<?= htmlspecialchars(json_encode($a)) ?>)" title="Modifier"><i class="fas fa-edit"></i></button>
                        <button class="btn-xs primary" onclick="openPwd(<?= $a['id'] ?>)" title="Mot de passe"><i class="fas fa-key"></i></button>
                        <form method="post" style="display:inline"><input type="hidden" name="csrf_token" value="<?= $csrf_token ?>"><input type="hidden" name="action" value="toggle_2fa"><input type="hidden" name="admin_id" value="<?= $a['id'] ?>"><button class="btn-xs <?= ($a['two_factor_enabled'] ?? 0) ? 'warning' : 'success' ?>" title="Basculer 2FA"><i class="fas fa-shield-alt"></i></button></form>
                        <?php if (!empty($a['locked_until']) && strtotime($a['locked_until']) > time()): ?>
                            <form method="post" style="display:inline"><input type="hidden" name="csrf_token" value="<?= $csrf_token ?>"><input type="hidden" name="action" value="unlock"><input type="hidden" name="admin_id" value="<?= $a['id'] ?>"><button class="btn-xs warning" title="Déverrouiller"><i class="fas fa-unlock"></i></button></form>
                        <?php endif; ?>
                        <form method="post" style="display:inline"><input type="hidden" name="csrf_token" value="<?= $csrf_token ?>"><input type="hidden" name="action" value="toggle_active"><input type="hidden" name="admin_id" value="<?= $a['id'] ?>"><input type="hidden" name="new_active" value="<?= ($a['actif'] ?? 1) ? 0 : 1 ?>"><button class="btn-xs <?= ($a['actif'] ?? 1) ? 'danger' : 'success' ?>" title="<?= ($a['actif'] ?? 1) ? 'Désactiver' : 'Activer' ?>"><i class="fas fa-<?= ($a['actif'] ?? 1) ? 'ban' : 'check' ?>"></i></button></form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Modal modifier info -->
<div class="modal-overlay" id="editModal">
    <div class="modal-box">
        <button class="modal-close" onclick="document.getElementById('editModal').classList.remove('show')">&times;</button>
        <h2>Modifier les informations</h2>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
            <input type="hidden" name="action" value="edit_info">
            <input type="hidden" name="admin_id" id="edit_id" value="">
            <div class="form-group"><label>Nom</label><input type="text" name="nom" id="edit_nom" required></div>
            <div class="form-group"><label>Prénom</label><input type="text" name="prenom" id="edit_prenom" required></div>
            <div class="form-group"><label>Email</label><input type="email" name="mail" id="edit_mail" required></div>
            <div class="form-group"><label>Adresse</label><input type="text" name="adresse" id="edit_adresse"></div>
            <button type="submit" class="btn btn-primary" style="margin-top:10px;"><i class="fas fa-save"></i> Enregistrer</button>
        </form>
    </div>
</div>

<!-- Modal mot de passe -->
<div class="modal-overlay" id="pwdModal">
    <div class="modal-box">
        <button class="modal-close" onclick="document.getElementById('pwdModal').classList.remove('show')">&times;</button>
        <h2>Changer le mot de passe</h2>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
            <input type="hidden" name="action" value="change_password">
            <input type="hidden" name="admin_id" id="pwd_id" value="">
            <div class="form-group"><label>Nouveau mot de passe</label><input type="password" name="new_password" required minlength="8"></div>
            <div class="form-group"><label>Confirmer</label><input type="password" name="confirm_password" required minlength="8"></div>
            <button type="submit" class="btn btn-primary" style="margin-top:10px;"><i class="fas fa-key"></i> Modifier</button>
        </form>
    </div>
</div>

<script>
function openEdit(admin) {
    document.getElementById('edit_id').value = admin.id;
    document.getElementById('edit_nom').value = admin.nom;
    document.getElementById('edit_prenom').value = admin.prenom;
    document.getElementById('edit_mail').value = admin.mail;
    document.getElementById('edit_adresse').value = admin.adresse || '';
    document.getElementById('editModal').classList.add('show');
}
function openPwd(id) {
    document.getElementById('pwd_id').value = id;
    document.getElementById('pwdModal').classList.add('show');
}
document.querySelectorAll('.modal-overlay').forEach(m => m.addEventListener('click', function(e) { if (e.target === this) this.classList.remove('show'); }));
</script>

<?php include __DIR__ . '/../includes/sub_footer.php'; ?>
