<?php
/**
 * Gestion de tous les utilisateurs — Recherche avancée, consultation, édition, actions
 */
require_once __DIR__ . '/../../API/core.php';
require_once __DIR__ . '/../includes/admin_functions.php';

requireAuth();
requireRole('administrateur');

$pdo = getPDO();
$userObj = app('user');

$message = '';
$error = '';

// CSRF
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// ---------- Actions POST ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token']) && $_POST['csrf_token'] === $csrf_token) {
    $action = $_POST['action'] ?? '';

    // Activer / Désactiver
    if ($action === 'toggle_active') {
        $uid = intval($_POST['user_id'] ?? 0);
        $profil = $_POST['user_type'] ?? '';
        $newActive = intval($_POST['new_active'] ?? 1);
        $table = $userObj->getTableName($profil);
        if ($uid > 0 && $table) {
            $old = $pdo->prepare("SELECT actif FROM `$table` WHERE id = ?"); $old->execute([$uid]); $oldVal = $old->fetchColumn();
            $stmt = $pdo->prepare("UPDATE `$table` SET actif = ? WHERE id = ?");
            if ($stmt->execute([$newActive, $uid])) {
                logAudit($newActive ? 'user_activated' : 'user_deactivated', $table, $uid, ['actif' => $oldVal], ['actif' => $newActive]);
                $message = $newActive ? "Compte activé avec succès." : "Compte désactivé avec succès.";
            } else { $error = "Erreur lors de la modification du statut."; }
        }
    }

    // Déverrouiller
    if ($action === 'unlock') {
        $uid = intval($_POST['user_id'] ?? 0);
        $profil = $_POST['user_type'] ?? '';
        $table = $userObj->getTableName($profil);
        if ($uid > 0 && $table) {
            $stmt = $pdo->prepare("UPDATE `$table` SET locked_until = NULL, failed_login_attempts = 0 WHERE id = ?");
            if ($stmt->execute([$uid])) {
                logAudit('user_unlocked', $table, $uid);
                $message = "Compte déverrouillé.";
            }
        }
    }

    // Réinitialiser le mot de passe
    if ($action === 'reset_password') {
        $uid = intval($_POST['user_id'] ?? 0);
        $profil = $_POST['user_type'] ?? '';
        if ($uid > 0 && !empty($profil)) {
            $newPwd = generateSecurePassword(12);
            if ($userObj->changePassword($profil, $uid, $newPwd)) {
                logAudit('password_reset', $userObj->getTableName($profil), $uid, null, ['password' => '***']);
                $message = "Mot de passe réinitialisé. Nouveau mot de passe : <code>" . htmlspecialchars($newPwd) . "</code>";
            } else {
                $error = "Erreur : " . $userObj->getErrorMessage();
            }
        }
    }

    // Supprimer
    if ($action === 'delete') {
        $uid = intval($_POST['user_id'] ?? 0);
        $profil = $_POST['user_type'] ?? '';
        if ($uid > 0 && !empty($profil)) {
            if ($userObj->delete($profil, $uid)) {
                logAudit('user_deleted', $userObj->getTableName($profil), $uid);
                $message = "Utilisateur supprimé.";
            } else { $error = "Erreur : " . $userObj->getErrorMessage(); }
        }
    }

    // Modifier infos
    if ($action === 'edit_user') {
        $uid = intval($_POST['user_id'] ?? 0);
        $profil = $_POST['user_type'] ?? '';
        $table = $userObj->getTableName($profil);
        if ($uid > 0 && $table) {
            $fields = ['nom','prenom','mail','adresse','telephone'];
            if ($profil === 'eleve') $fields = array_merge($fields, ['classe','date_naissance','lieu_naissance']);
            if ($profil === 'professeur') $fields = array_merge($fields, ['matiere','professeur_principal']);
            if ($profil === 'parent') $fields = array_merge($fields, ['metier','est_parent_eleve']);
            if ($profil === 'vie_scolaire') $fields = array_merge($fields, ['est_CPE','est_infirmerie']);

            $sets = []; $params = [];
            foreach ($fields as $f) {
                if (isset($_POST[$f])) {
                    $sets[] = "`$f` = ?";
                    $params[] = trim($_POST[$f]);
                }
            }
            if (!empty($sets)) {
                $params[] = $uid;
                $sql = "UPDATE `$table` SET " . implode(', ', $sets) . " WHERE id = ?";
                try {
                    $stmt = $pdo->prepare($sql);
                    if ($stmt->execute($params)) {
                        logAudit('user_updated', $table, $uid);
                        $message = "Utilisateur mis à jour.";
                    } else { $error = "Erreur lors de la mise à jour."; }
                } catch (PDOException $e) { $error = "Erreur : " . $e->getMessage(); }
            }
        }
    }
}

// ---------- Recherche / Filtres ----------
$searchTerm = $_GET['q'] ?? '';
$filterType = $_GET['type'] ?? '';
$filterStatus = $_GET['status'] ?? '';
$filterClasse = $_GET['classe'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 50;

$usersList = [];
$totalUsers = 0;

try {
    $tables = [
        'eleve' => 'eleves',
        'parent' => 'parents',
        'professeur' => 'professeurs',
        'vie_scolaire' => 'vie_scolaire',
    ];

    if (!empty($filterType) && isset($tables[$filterType])) {
        $tables = [$filterType => $tables[$filterType]];
    }

    $allResults = [];
    foreach ($tables as $profil => $table) {
        $where = []; $params = [];

        if (!empty($searchTerm)) {
            $where[] = "(nom LIKE ? OR prenom LIKE ? OR identifiant LIKE ? OR mail LIKE ?)";
            $s = '%' . $searchTerm . '%';
            $params = array_merge($params, [$s, $s, $s, $s]);
        }
        if ($filterStatus === 'active') { $where[] = "actif = 1"; }
        elseif ($filterStatus === 'inactive') { $where[] = "actif = 0"; }
        elseif ($filterStatus === 'locked') { $where[] = "locked_until > NOW()"; }

        if (!empty($filterClasse) && $profil === 'eleve') {
            $where[] = "classe = ?";
            $params[] = $filterClasse;
        }

        $sql = "SELECT id, identifiant, nom, prenom, mail, COALESCE(actif,1) as actif, last_login, locked_until, failed_login_attempts";
        if ($profil === 'eleve') $sql .= ", classe, date_naissance";
        if ($profil === 'professeur') $sql .= ", matiere, professeur_principal";
        $sql .= " FROM `$table`";
        if (!empty($where)) $sql .= " WHERE " . implode(' AND ', $where);

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$r) { $r['profil'] = $profil; }
        $allResults = array_merge($allResults, $rows);
    }

    usort($allResults, fn($a,$b) => strcasecmp($a['nom'], $b['nom']));
    $totalUsers = count($allResults);
    $usersList = array_slice($allResults, ($page - 1) * $perPage, $perPage);
} catch (Exception $e) {
    $error = "Erreur de chargement : " . $e->getMessage();
}

$totalPages = max(1, ceil($totalUsers / $perPage));

// Classes disponibles pour le filtre
$classesList = [];
try {
    $classesList = $pdo->query("SELECT DISTINCT nom FROM classes WHERE actif = 1 ORDER BY niveau, nom")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {}

$pageTitle = 'Gestion des utilisateurs';
$currentPage = 'users';
$extraCss = ['../../assets/css/admin.css'];

ob_start();
?>
<style>
    .users-container { max-width: 1100px; margin: 0 auto; }
</style>
<?php
$extraHeadHtml = ob_get_clean();
include __DIR__ . '/../includes/header.php';
?>

<div class="users-container">
    <?php if (!empty($message)): ?>
        <div class="alert alert-success"><?= $message ?></div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Filtres -->
    <form method="get" class="filters-bar">
        <input type="text" name="q" placeholder="Rechercher nom, prénom, identifiant, email…" value="<?= htmlspecialchars($searchTerm) ?>">
        <select name="type">
            <option value="">Tous les profils</option>
            <option value="eleve" <?= $filterType==='eleve'?'selected':'' ?>>Élèves</option>
            <option value="professeur" <?= $filterType==='professeur'?'selected':'' ?>>Professeurs</option>
            <option value="parent" <?= $filterType==='parent'?'selected':'' ?>>Parents</option>
            <option value="vie_scolaire" <?= $filterType==='vie_scolaire'?'selected':'' ?>>Vie scolaire</option>
        </select>
        <select name="status">
            <option value="">Tous les statuts</option>
            <option value="active" <?= $filterStatus==='active'?'selected':'' ?>>Actifs</option>
            <option value="inactive" <?= $filterStatus==='inactive'?'selected':'' ?>>Inactifs</option>
            <option value="locked" <?= $filterStatus==='locked'?'selected':'' ?>>Verrouillés</option>
        </select>
        <?php if (!empty($classesList)): ?>
        <select name="classe">
            <option value="">Toutes les classes</option>
            <?php foreach ($classesList as $c): ?>
                <option value="<?= htmlspecialchars($c) ?>" <?= $filterClasse===$c?'selected':'' ?>><?= htmlspecialchars($c) ?></option>
            <?php endforeach; ?>
        </select>
        <?php endif; ?>
        <button type="submit" class="btn btn-primary" style="padding: 8px 16px;"><i class="fas fa-search"></i> Rechercher</button>
        <a href="index.php" class="btn-xs primary" style="padding: 8px 12px;">Réinitialiser</a>
    </form>

    <div class="results-count"><?= $totalUsers ?> utilisateur(s) trouvé(s)</div>

    <!-- Tableau -->
    <table class="admin-table">
        <thead>
            <tr>
                <th>Nom</th>
                <th>Prénom</th>
                <th>Identifiant</th>
                <th>Profil</th>
                <th>Statut</th>
                <th>Dernière connexion</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($usersList)): ?>
                <tr><td colspan="7" style="text-align:center;color:#999;padding:30px;">Aucun utilisateur trouvé.</td></tr>
            <?php else: ?>
                <?php foreach ($usersList as $u): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($u['nom']) ?></strong></td>
                    <td><?= htmlspecialchars($u['prenom']) ?></td>
                    <td><code><?= htmlspecialchars($u['identifiant']) ?></code></td>
                    <td><span class="badge-profil <?= getProfilBadgeClass($u['profil']) ?>"><?= getProfilLabel($u['profil']) ?></span></td>
                    <td>
                        <?php if (!empty($u['locked_until']) && strtotime($u['locked_until']) > time()): ?>
                            <span class="status-locked"><i class="fas fa-lock"></i> Verrouillé</span>
                        <?php elseif ($u['actif']): ?>
                            <span class="status-active"><i class="fas fa-check-circle"></i> Actif</span>
                        <?php else: ?>
                            <span class="status-inactive"><i class="fas fa-times-circle"></i> Inactif</span>
                        <?php endif; ?>
                    </td>
                    <td style="font-size:13px;color:#718096;">
                        <?= !empty($u['last_login']) ? date('d/m/Y H:i', strtotime($u['last_login'])) : '<em>Jamais</em>' ?>
                    </td>
                    <td>
                        <div class="actions-cell">
                            <button class="btn-xs primary" onclick="openProfile(<?= $u['id'] ?>, '<?= $u['profil'] ?>')"><i class="fas fa-eye"></i></button>
                            <?php if (!empty($u['locked_until']) && strtotime($u['locked_until']) > time()): ?>
                                <form method="post" style="display:inline"><input type="hidden" name="csrf_token" value="<?= $csrf_token ?>"><input type="hidden" name="action" value="unlock"><input type="hidden" name="user_id" value="<?= $u['id'] ?>"><input type="hidden" name="user_type" value="<?= $u['profil'] ?>"><button class="btn-xs warning" title="Déverrouiller"><i class="fas fa-unlock"></i></button></form>
                            <?php endif; ?>
                            <form method="post" style="display:inline"><input type="hidden" name="csrf_token" value="<?= $csrf_token ?>"><input type="hidden" name="action" value="toggle_active"><input type="hidden" name="user_id" value="<?= $u['id'] ?>"><input type="hidden" name="user_type" value="<?= $u['profil'] ?>"><input type="hidden" name="new_active" value="<?= $u['actif'] ? 0 : 1 ?>"><button class="btn-xs <?= $u['actif'] ? 'warning' : 'success' ?>" title="<?= $u['actif'] ? 'Désactiver' : 'Activer' ?>"><i class="fas fa-<?= $u['actif'] ? 'ban' : 'check' ?>"></i></button></form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="pagination">
        <?php
        $qp = http_build_query(array_filter(['q'=>$searchTerm,'type'=>$filterType,'status'=>$filterStatus,'classe'=>$filterClasse]));
        for ($p = 1; $p <= $totalPages; $p++):
            $href = "index.php?" . $qp . "&page=$p";
        ?>
            <?php if ($p === $page): ?>
                <span class="current"><?= $p ?></span>
            <?php else: ?>
                <a href="<?= htmlspecialchars($href) ?>"><?= $p ?></a>
            <?php endif; ?>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Modal profil utilisateur -->
<div class="modal-overlay" id="profileModal">
    <div class="modal-box">
        <button class="modal-close" onclick="closeProfile()">&times;</button>
        <h2 id="profileTitle">Profil utilisateur</h2>
        <div id="profileContent"><p style="text-align:center;color:#999;"><i class="fas fa-spinner fa-spin"></i> Chargement…</p></div>
    </div>
</div>

<script>
function openProfile(userId, userType) {
    document.getElementById('profileModal').classList.add('show');
    const content = document.getElementById('profileContent');
    content.innerHTML = '<p style="text-align:center;color:#999;"><i class="fas fa-spinner fa-spin"></i> Chargement…</p>';

    // Fetch via a simple AJAX-like approach with a hidden form
    fetch('profile_ajax.php?id=' + userId + '&type=' + userType)
        .then(r => r.text())
        .then(html => { content.innerHTML = html; })
        .catch(() => { content.innerHTML = '<p class="alert alert-danger">Erreur de chargement.</p>'; });
}

function closeProfile() {
    document.getElementById('profileModal').classList.remove('show');
}

// Close on overlay click
document.getElementById('profileModal').addEventListener('click', function(e) {
    if (e.target === this) closeProfile();
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
