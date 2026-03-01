<?php
/**
 * Administration des devoirs — liste, filtres, modifier, supprimer
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

// POST Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['csrf_token'] ?? '') === $csrf_token) {
    $action = $_POST['action'] ?? '';

    if ($action === 'delete_devoir') {
        $did = intval($_POST['devoir_id'] ?? 0);
        if ($did > 0) {
            $pdo->prepare("DELETE FROM devoirs WHERE id = ?")->execute([$did]);
            logAudit('devoir_deleted', 'devoirs', $did);
            $message = "Devoir supprimé.";
        }
    }

    if ($action === 'edit_devoir') {
        $did = intval($_POST['devoir_id'] ?? 0);
        $titre = trim($_POST['titre'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $dateRendu = $_POST['date_rendu'] ?? '';
        if ($did > 0 && !empty($titre)) {
            $pdo->prepare("UPDATE devoirs SET titre = ?, description = ?, date_rendu = ? WHERE id = ?")->execute([$titre, $description, $dateRendu, $did]);
            logAudit('devoir_edited', 'devoirs', $did);
            $message = "Devoir modifié.";
        }
    }
}

// Filtres
$filterClasse = $_GET['classe'] ?? '';
$filterMatiere = $_GET['matiere'] ?? '';
$filterProf = $_GET['prof'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 40;
$offset = ($page - 1) * $perPage;

$where = []; $params = [];
if (!empty($filterClasse)) { $where[] = "d.classe = ?"; $params[] = $filterClasse; }
if (!empty($filterMatiere)) { $where[] = "d.nom_matiere = ?"; $params[] = $filterMatiere; }
if (!empty($filterProf)) { $where[] = "d.nom_professeur LIKE ?"; $params[] = "%$filterProf%"; }
$whereSQL = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

$total = $pdo->prepare("SELECT COUNT(*) FROM devoirs d $whereSQL"); $total->execute($params);
$totalDevoirs = $total->fetchColumn();
$totalPages = max(1, ceil($totalDevoirs / $perPage));

$sql = "SELECT d.* FROM devoirs d $whereSQL ORDER BY d.date_rendu DESC LIMIT $perPage OFFSET $offset";
$stmt = $pdo->prepare($sql); $stmt->execute($params);
$devoirs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Listes pour filtres
$classesList = $pdo->query("SELECT DISTINCT classe FROM devoirs ORDER BY classe")->fetchAll(PDO::FETCH_COLUMN);
$matieresList = $pdo->query("SELECT DISTINCT nom_matiere FROM devoirs ORDER BY nom_matiere")->fetchAll(PDO::FETCH_COLUMN);

$pageTitle = 'Devoirs';
$currentPage = 'devoirs';
$extraCss = ['../../assets/css/admin.css'];

ob_start();
?>
<style>
    .devoirs-container { max-width: 1100px; margin: 0 auto; }
    .filters { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 15px; align-items: flex-end; }
    .filters select, .filters input { padding: 7px 10px; border: 1px solid #d2d6dc; border-radius: 6px; font-size: 13px; }
    .devoirs-table { width: 100%; border-collapse: collapse; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
    .devoirs-table th, .devoirs-table td { padding: 10px 14px; text-align: left; border-bottom: 1px solid #f0f0f0; font-size: 13px; }
    .devoirs-table th { background: #f7fafc; font-weight: 600; color: #4a5568; font-size: 12px; }
    .badge-date { font-size: 12px; padding: 2px 8px; border-radius: 10px; }
    .date-past { background: #fee2e2; color: #991b1b; } .date-future { background: #dbeafe; color: #1e40af; } .date-today { background: #d1fae5; color: #065f46; }
    .stat-pill { display: inline-flex; align-items: center; gap: 6px; background: white; border-radius: 8px; padding: 8px 14px; box-shadow: 0 1px 4px rgba(0,0,0,0.06); margin-bottom: 15px; margin-right: 10px; font-size: 14px; }
    .stat-pill strong { font-size: 18px; font-weight: 700; }
</style>
<?php
$extraHeadHtml = ob_get_clean();
include __DIR__ . '/../includes/sub_header.php';
?>

<div class="devoirs-container">
    <?php if (!empty($message)): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>

    <div>
        <span class="stat-pill"><i class="fas fa-book"></i> <strong><?= $totalDevoirs ?></strong> devoirs</span>
        <?php
        $upcoming = $pdo->query("SELECT COUNT(*) FROM devoirs WHERE date_rendu >= CURDATE()")->fetchColumn();
        $overdue = $pdo->query("SELECT COUNT(*) FROM devoirs WHERE date_rendu < CURDATE()")->fetchColumn();
        ?>
        <span class="stat-pill"><i class="fas fa-calendar-check" style="color:#059669"></i> <strong><?= $upcoming ?></strong> à venir</span>
        <span class="stat-pill"><i class="fas fa-calendar-times" style="color:#dc2626"></i> <strong><?= $overdue ?></strong> passés</span>
    </div>

    <form method="get" class="filters">
        <select name="classe"><option value="">Toutes classes</option>
            <?php foreach ($classesList as $c): ?><option value="<?= htmlspecialchars($c) ?>" <?= $filterClasse === $c ? 'selected' : '' ?>><?= htmlspecialchars($c) ?></option><?php endforeach; ?>
        </select>
        <select name="matiere"><option value="">Toutes matières</option>
            <?php foreach ($matieresList as $m): ?><option value="<?= htmlspecialchars($m) ?>" <?= $filterMatiere === $m ? 'selected' : '' ?>><?= htmlspecialchars($m) ?></option><?php endforeach; ?>
        </select>
        <input type="text" name="prof" value="<?= htmlspecialchars($filterProf) ?>" placeholder="Professeur…">
        <button type="submit" class="btn btn-primary" style="height:35px"><i class="fas fa-filter"></i></button>
        <a href="devoirs.php" class="btn btn-secondary" style="height:35px;line-height:35px;text-decoration:none">Reset</a>
    </form>

    <?php if (empty($devoirs)): ?>
        <div style="text-align:center;padding:40px;color:#999"><p>Aucun devoir trouvé.</p></div>
    <?php else: ?>
    <table class="devoirs-table">
        <thead><tr><th>Titre</th><th>Classe</th><th>Matière</th><th>Professeur</th><th>Date ajout</th><th>Date rendu</th><th>Actions</th></tr></thead>
        <tbody>
            <?php foreach ($devoirs as $d):
                $today = date('Y-m-d');
                $dateClass = $d['date_rendu'] < $today ? 'date-past' : ($d['date_rendu'] === $today ? 'date-today' : 'date-future');
            ?>
            <tr>
                <td><strong><?= htmlspecialchars($d['titre']) ?></strong>
                    <?php if (!empty($d['description'])): ?><br><small style="color:#888"><?= htmlspecialchars(mb_substr($d['description'], 0, 60)) ?>…</small><?php endif; ?>
                </td>
                <td><?= htmlspecialchars($d['classe']) ?></td>
                <td><?= htmlspecialchars($d['nom_matiere']) ?></td>
                <td style="font-size:12px"><?= htmlspecialchars($d['nom_professeur']) ?></td>
                <td style="font-size:12px"><?= date('d/m/Y', strtotime($d['date_ajout'])) ?></td>
                <td><span class="badge-date <?= $dateClass ?>"><?= date('d/m/Y', strtotime($d['date_rendu'])) ?></span></td>
                <td>
                    <button class="btn-xs primary" onclick='openEdit(<?= json_encode($d) ?>)'><i class="fas fa-pen"></i></button>
                    <form method="post" style="display:inline" onsubmit="return confirm('Supprimer ce devoir ?')"><input type="hidden" name="csrf_token" value="<?= $csrf_token ?>"><input type="hidden" name="action" value="delete_devoir"><input type="hidden" name="devoir_id" value="<?= $d['id'] ?>"><button class="btn-xs danger"><i class="fas fa-trash"></i></button></form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<!-- Modal Modifier -->
<div class="modal-overlay" id="editModal">
    <div class="modal-box">
        <h3><i class="fas fa-pen"></i> Modifier le devoir</h3>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
            <input type="hidden" name="action" value="edit_devoir">
            <input type="hidden" name="devoir_id" id="edit_did">
            <div class="form-group"><label>Titre</label><input type="text" name="titre" id="edit_titre" required></div>
            <div class="form-group"><label>Description</label><textarea name="description" id="edit_desc" rows="3"></textarea></div>
            <div class="form-group"><label>Date de rendu</label><input type="date" name="date_rendu" id="edit_date"></div>
            <div style="display:flex;gap:8px;justify-content:flex-end"><button type="button" class="btn btn-secondary" onclick="document.getElementById('editModal').classList.remove('active')">Annuler</button><button type="submit" class="btn btn-primary">Enregistrer</button></div>
        </form>
    </div>
</div>

<script>
function openEdit(d) {
    document.getElementById('edit_did').value = d.id;
    document.getElementById('edit_titre').value = d.titre;
    document.getElementById('edit_desc').value = d.description || '';
    document.getElementById('edit_date').value = d.date_rendu;
    document.getElementById('editModal').classList.add('active');
}
document.querySelectorAll('.modal-overlay').forEach(m => m.addEventListener('click', e => { if (e.target === m) m.classList.remove('active'); }));
</script>

<?php include __DIR__ . '/../includes/sub_footer.php'; ?>
