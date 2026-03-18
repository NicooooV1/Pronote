<?php
/**
 * Template partagé : Top bar (zone entre sidebar et contenu)
 * Ouvre le main-content et affiche le top-header.
 * Doit être inclus APRÈS shared_sidebar.php.
 * 
 * Variables attendues (déjà définies par shared_header.php) :
 *   $pageTitle, $user_initials
 * 
 * Variables optionnelles :
 *   $pageSubtitle, $headerExtraActions, $user_fullname
 */

$pageTitle = $pageTitle ?? 'FRONOTE';
$user_initials = $user_initials ?? '';
$pageSubtitle = $pageSubtitle ?? '';
$headerExtraActions = $headerExtraActions ?? '';
$user_fullname = $user_fullname ?? '';
$rootPrefix = $rootPrefix ?? '../';

// ─── Sélecteur enfant global pour les parents ────────────────────────────────
$_topbar_children = [];
$_topbar_selected_child = null;
$_topbar_is_parent = (($_SESSION['role'] ?? '') === 'parent');

if ($_topbar_is_parent && !empty($_SESSION['user_id'])) {
    try {
        $pdo_tb = getPDO();

        // Changement d'enfant via POST/GET
        if (!empty($_REQUEST['switch_child'])) {
            $switchId = (int)$_REQUEST['switch_child'];
            // Vérifier que cet enfant appartient bien au parent
            $stmtCheck = $pdo_tb->prepare("SELECT COUNT(*) FROM parent_eleve WHERE id_parent = ? AND id_eleve = ?");
            $stmtCheck->execute([$_SESSION['user_id'], $switchId]);
            if ((int)$stmtCheck->fetchColumn() > 0) {
                $_SESSION['selected_child_id'] = $switchId;
            }
        }

        // Charger la liste des enfants
        $stmtChildren = $pdo_tb->prepare("
            SELECT e.id, e.nom, e.prenom, c.nom AS classe_nom
            FROM parent_eleve pe
            JOIN eleves e ON e.id = pe.id_eleve
            LEFT JOIN classes c ON e.classe_id = c.id
            WHERE pe.id_parent = ? AND e.actif = 1
            ORDER BY e.nom, e.prenom
        ");
        $stmtChildren->execute([$_SESSION['user_id']]);
        $_topbar_children = $stmtChildren->fetchAll(PDO::FETCH_ASSOC);

        // Sélectionner l'enfant actif (ou le premier par défaut)
        if (!empty($_topbar_children)) {
            $selectedId = $_SESSION['selected_child_id'] ?? null;
            $found = false;
            foreach ($_topbar_children as $child) {
                if ((int)$child['id'] === (int)$selectedId) {
                    $_topbar_selected_child = $child;
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $_topbar_selected_child = $_topbar_children[0];
                $_SESSION['selected_child_id'] = (int)$_topbar_children[0]['id'];
            }
        }
    } catch (Exception $e) {
        // Table pas encore créée ou erreur — on continue sans
    }
}
?>

    <!-- Main Content -->
    <div class="main-content">
        <div class="top-header">
            <div class="page-title">
                <h1><?= htmlspecialchars($pageTitle) ?></h1>
                <?php if (!empty($pageSubtitle)): ?>
                <p class="subtitle"><?= htmlspecialchars($pageSubtitle) ?></p>
                <?php endif; ?>
            </div>

            <div class="header-actions">
                <?php if ($_topbar_is_parent && count($_topbar_children) > 1): ?>
                <div class="child-selector">
                    <label class="child-selector-label" title="Sélecteur d'enfant">
                        <i class="fas fa-child"></i>
                    </label>
                    <select class="child-selector-select" onchange="switchChild(this.value)">
                        <?php foreach ($_topbar_children as $_ch): ?>
                        <option value="<?= (int)$_ch['id'] ?>" <?= ($_topbar_selected_child && (int)$_ch['id'] === (int)$_topbar_selected_child['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($_ch['prenom'] . ' ' . $_ch['nom']) ?><?= !empty($_ch['classe_nom']) ? ' — ' . htmlspecialchars($_ch['classe_nom']) : '' ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php elseif ($_topbar_is_parent && $_topbar_selected_child): ?>
                <div class="child-indicator" title="Enfant consulté">
                    <i class="fas fa-child"></i>
                    <span><?= htmlspecialchars($_topbar_selected_child['prenom'] . ' ' . $_topbar_selected_child['nom']) ?></span>
                </div>
                <?php endif; ?>
                <?= $headerExtraActions ?>
                <?php if (isAdmin()): ?>
                <a href="<?= $rootPrefix ?>admin/dashboard.php" class="admin-topbar-link" title="Administration">
                    <i class="fas fa-cogs"></i>
                </a>
                <?php endif; ?>
                <a href="<?= $rootPrefix ?>login/logout.php" class="logout-button" title="Déconnexion">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
                <div class="user-avatar" title="<?= htmlspecialchars($user_fullname) ?>"><?= htmlspecialchars($user_initials) ?></div>
            </div>
        </div>

<?php if ($_topbar_is_parent && !empty($_topbar_children)): ?>
<style>
.child-selector{display:flex;align-items:center;gap:6px;background:#f0f4ff;border-radius:8px;padding:4px 10px}
.child-selector-label{color:#667eea;font-size:1em}
.child-selector-select{border:none;background:transparent;font-size:.88em;font-weight:600;color:#2d3748;padding:4px 6px;cursor:pointer;outline:none;max-width:220px}
.child-selector-select:focus{box-shadow:0 0 0 2px rgba(102,126,234,.3);border-radius:4px}
.child-indicator{display:flex;align-items:center;gap:6px;background:#f0f4ff;border-radius:8px;padding:6px 12px;font-size:.88em;font-weight:600;color:#4a5568}
.child-indicator i{color:#667eea}
</style>
<script>
function switchChild(childId) {
    if (!childId) return;
    var url = new URL(window.location.href);
    url.searchParams.set('switch_child', childId);
    window.location.href = url.toString();
}
</script>
<?php endif; ?>
