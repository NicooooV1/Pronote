<?php
/**
 * Administration des absences et retards — deux onglets, CRUD, justifier, stats
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

$classes = $pdo->query("SELECT id, nom FROM classes WHERE actif = 1 ORDER BY nom")->fetchAll(PDO::FETCH_ASSOC);

// POST Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['csrf_token'] ?? '') === $csrf_token) {
    $action = $_POST['action'] ?? '';

    if ($action === 'toggle_justify_absence') {
        $aid = intval($_POST['absence_id'] ?? 0);
        if ($aid > 0) {
            $cur = $pdo->prepare("SELECT justifie FROM absences WHERE id = ?"); $cur->execute([$aid]); $val = $cur->fetchColumn();
            $newVal = $val ? 0 : 1;
            $pdo->prepare("UPDATE absences SET justifie = ? WHERE id = ?")->execute([$newVal, $aid]);
            logAudit($newVal ? 'absence_justified' : 'absence_unjustified', 'absences', $aid);
            $message = $newVal ? "Absence marquée comme justifiée." : "Absence marquée comme non justifiée.";
        }
    }

    if ($action === 'delete_absence') {
        $aid = intval($_POST['absence_id'] ?? 0);
        if ($aid > 0) {
            $pdo->prepare("DELETE FROM absences WHERE id = ?")->execute([$aid]);
            logAudit('absence_deleted', 'absences', $aid);
            $message = "Absence supprimée.";
        }
    }

    if ($action === 'toggle_justify_retard') {
        $rid = intval($_POST['retard_id'] ?? 0);
        if ($rid > 0) {
            $cur = $pdo->prepare("SELECT justifie FROM retards WHERE id = ?"); $cur->execute([$rid]); $val = $cur->fetchColumn();
            $newVal = $val ? 0 : 1;
            $pdo->prepare("UPDATE retards SET justifie = ? WHERE id = ?")->execute([$newVal, $rid]);
            logAudit($newVal ? 'retard_justified' : 'retard_unjustified', 'retards', $rid);
            $message = $newVal ? "Retard justifié." : "Retard non justifié.";
        }
    }

    if ($action === 'delete_retard') {
        $rid = intval($_POST['retard_id'] ?? 0);
        if ($rid > 0) {
            $pdo->prepare("DELETE FROM retards WHERE id = ?")->execute([$rid]);
            logAudit('retard_deleted', 'retards', $rid);
            $message = "Retard supprimé.";
        }
    }

    if ($action === 'add_absence') {
        $idEleve = intval($_POST['id_eleve'] ?? 0);
        $dateDebut = $_POST['date_debut'] ?? '';
        $dateFin = $_POST['date_fin'] ?? '';
        $typeAbs = trim($_POST['type_absence'] ?? 'absence');
        $motif = trim($_POST['motif'] ?? '');
        $commentaire = trim($_POST['commentaire'] ?? '');
        if ($idEleve > 0 && $dateDebut && $dateFin) {
            $stmt = $pdo->prepare("INSERT INTO absences (id_eleve, date_debut, date_fin, type_absence, motif, commentaire, signale_par) VALUES (?,?,?,?,?,?,?)");
            $stmt->execute([$idEleve, $dateDebut, $dateFin, $typeAbs, $motif, $commentaire, 'Administrateur']);
            logAudit('absence_added', 'absences', $pdo->lastInsertId());
            $message = "Absence ajoutée.";
        }
    }

    if ($action === 'add_retard') {
        $idEleve = intval($_POST['id_eleve'] ?? 0);
        $dateRetard = $_POST['date_retard'] ?? '';
        $duree = intval($_POST['duree_minutes'] ?? 0);
        $motif = trim($_POST['motif'] ?? '');
        if ($idEleve > 0 && $dateRetard && $duree > 0) {
            $stmt = $pdo->prepare("INSERT INTO retards (id_eleve, date_retard, duree_minutes, motif, signale_par) VALUES (?,?,?,?,?)");
            $stmt->execute([$idEleve, $dateRetard, $duree, $motif, 'Administrateur']);
            logAudit('retard_added', 'retards', $pdo->lastInsertId());
            $message = "Retard ajouté.";
        }
    }
}

// Filtres
$tab = $_GET['tab'] ?? 'absences';
$filterClasse = $_GET['classe'] ?? '';
$filterEleve = trim($_GET['eleve'] ?? '');
$filterJustifie = $_GET['justifie'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 50;
$offset = ($page - 1) * $perPage;

// Absences
$absWhere = []; $absParams = [];
if (!empty($filterClasse)) { $absWhere[] = "e.classe = ?"; $absParams[] = $filterClasse; }
if (!empty($filterEleve)) { $absWhere[] = "(e.nom LIKE ? OR e.prenom LIKE ?)"; $absParams[] = "%$filterEleve%"; $absParams[] = "%$filterEleve%"; }
if ($filterJustifie !== '') { $absWhere[] = "a.justifie = ?"; $absParams[] = intval($filterJustifie); }
$absWhereSQL = !empty($absWhere) ? 'WHERE ' . implode(' AND ', $absWhere) : '';

$absTotal = $pdo->prepare("SELECT COUNT(*) FROM absences a JOIN eleves e ON a.id_eleve = e.id $absWhereSQL");
$absTotal->execute($absParams); $totalAbsences = $absTotal->fetchColumn();

$absSQL = "SELECT a.*, e.nom AS eleve_nom, e.prenom AS eleve_prenom, e.classe
           FROM absences a JOIN eleves e ON a.id_eleve = e.id $absWhereSQL
           ORDER BY a.date_debut DESC LIMIT $perPage OFFSET $offset";
$absStmt = $pdo->prepare($absSQL); $absStmt->execute($absParams);
$absences = $absStmt->fetchAll(PDO::FETCH_ASSOC);

// Retards
$retWhere = []; $retParams = [];
if (!empty($filterClasse)) { $retWhere[] = "e.classe = ?"; $retParams[] = $filterClasse; }
if (!empty($filterEleve)) { $retWhere[] = "(e.nom LIKE ? OR e.prenom LIKE ?)"; $retParams[] = "%$filterEleve%"; $retParams[] = "%$filterEleve%"; }
if ($filterJustifie !== '' && $tab === 'retards') { $retWhere[] = "r.justifie = ?"; $retParams[] = intval($filterJustifie); }
$retWhereSQL = !empty($retWhere) ? 'WHERE ' . implode(' AND ', $retWhere) : '';

$retTotal = $pdo->prepare("SELECT COUNT(*) FROM retards r JOIN eleves e ON r.id_eleve = e.id $retWhereSQL");
$retTotal->execute($retParams); $totalRetards = $retTotal->fetchColumn();

$retSQL = "SELECT r.*, e.nom AS eleve_nom, e.prenom AS eleve_prenom, e.classe
           FROM retards r JOIN eleves e ON r.id_eleve = e.id $retWhereSQL
           ORDER BY r.date_retard DESC LIMIT $perPage OFFSET $offset";
$retStmt = $pdo->prepare($retSQL); $retStmt->execute($retParams);
$retards = $retStmt->fetchAll(PDO::FETCH_ASSOC);

// Stats rapides
$todayAbsences = $pdo->query("SELECT COUNT(*) FROM absences WHERE DATE(date_debut) <= CURDATE() AND DATE(date_fin) >= CURDATE()")->fetchColumn();
$unjustifiedCount = $pdo->query("SELECT COUNT(*) FROM absences WHERE justifie = 0")->fetchColumn();
$totalRetardsAll = $pdo->query("SELECT COUNT(*) FROM retards")->fetchColumn();

$eleves = $pdo->query("SELECT id, nom, prenom, classe FROM eleves WHERE actif = 1 ORDER BY nom, prenom")->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Absences & Retards';
$currentPage = 'absences';
$extraCss = ['../../assets/css/admin.css'];

ob_start();
?>
<style>
    .abs-container { max-width: 1200px; margin: 0 auto; }
    .tabs { display: flex; gap: 5px; margin-bottom: 20px; border-bottom: 2px solid #eee; }
    .tab-link { padding: 10px 20px; text-decoration: none; font-size: 14px; font-weight: 500; color: #666; border-bottom: 2px solid transparent; margin-bottom: -2px; display: flex; align-items: center; gap: 6px; }
    .tab-link.active { color: #0f4c81; border-bottom-color: #0f4c81; }
    .tab-badge { background: #e74c3c; color: white; border-radius: 50%; padding: 1px 6px; font-size: 11px; }
    .filters { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 15px; align-items: flex-end; }
    .filters select, .filters input { padding: 7px 10px; border: 1px solid #d2d6dc; border-radius: 6px; font-size: 13px; }
    .data-table { width: 100%; border-collapse: collapse; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
    .data-table th, .data-table td { padding: 8px 12px; text-align: left; border-bottom: 1px solid #f0f0f0; font-size: 13px; }
    .data-table th { background: #f7fafc; font-weight: 600; color: #4a5568; font-size: 12px; }
    .badge-j { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 600; }
    .badge-oui { background: #d1fae5; color: #065f46; } .badge-non { background: #fee2e2; color: #991b1b; }
</style>
<?php
$extraHeadHtml = ob_get_clean();
include __DIR__ . '/../includes/sub_header.php';
?>

<div class="abs-container">
    <?php if (!empty($message)): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if (!empty($error)): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <div class="stats-bar">
        <div class="stat-card"><div class="val" style="color:#dc2626"><?= $todayAbsences ?></div><div class="lbl">Absences aujourd'hui</div></div>
        <div class="stat-card"><div class="val" style="color:#f59e0b"><?= $unjustifiedCount ?></div><div class="lbl">Non justifiées</div></div>
        <div class="stat-card"><div class="val"><?= $totalRetardsAll ?></div><div class="lbl">Retards total</div></div>
    </div>

    <div class="tabs">
        <a href="?tab=absences" class="tab-link <?= $tab === 'absences' ? 'active' : '' ?>"><i class="fas fa-user-times"></i> Absences <span class="tab-badge"><?= $totalAbsences ?></span></a>
        <a href="?tab=retards" class="tab-link <?= $tab === 'retards' ? 'active' : '' ?>"><i class="fas fa-clock"></i> Retards <span class="tab-badge"><?= $totalRetards ?></span></a>
    </div>

    <form method="get" class="filters">
        <input type="hidden" name="tab" value="<?= $tab ?>">
        <select name="classe"><option value="">Toutes classes</option>
            <?php foreach ($classes as $c): ?><option value="<?= htmlspecialchars($c['nom']) ?>" <?= $filterClasse === $c['nom'] ? 'selected' : '' ?>><?= htmlspecialchars($c['nom']) ?></option><?php endforeach; ?>
        </select>
        <input type="text" name="eleve" value="<?= htmlspecialchars($filterEleve) ?>" placeholder="Nom élève…">
        <select name="justifie"><option value="">Justification</option><option value="1" <?= $filterJustifie === '1' ? 'selected' : '' ?>>Justifié</option><option value="0" <?= $filterJustifie === '0' ? 'selected' : '' ?>>Non justifié</option></select>
        <button type="submit" class="btn btn-primary" style="height:35px"><i class="fas fa-filter"></i></button>
        <button type="button" class="btn btn-success" style="height:35px;margin-left:auto" onclick="document.getElementById('<?= $tab === 'retards' ? 'addRetardModal' : 'addAbsModal' ?>').classList.add('active')"><i class="fas fa-plus"></i> Ajouter</button>
    </form>

    <?php if ($tab === 'absences'): ?>
    <!-- Tableau Absences -->
    <?php if (empty($absences)): ?>
        <div style="text-align:center;padding:40px;color:#999"><p>Aucune absence trouvée.</p></div>
    <?php else: ?>
    <table class="data-table">
        <thead><tr><th>Élève</th><th>Classe</th><th>Début</th><th>Fin</th><th>Type</th><th>Motif</th><th>Justifié</th><th>Actions</th></tr></thead>
        <tbody>
            <?php foreach ($absences as $a): ?>
            <tr>
                <td><strong><?= htmlspecialchars($a['eleve_prenom'] . ' ' . $a['eleve_nom']) ?></strong></td>
                <td><?= htmlspecialchars($a['classe']) ?></td>
                <td style="font-size:12px"><?= date('d/m/Y H:i', strtotime($a['date_debut'])) ?></td>
                <td style="font-size:12px"><?= date('d/m/Y H:i', strtotime($a['date_fin'])) ?></td>
                <td style="font-size:12px"><?= htmlspecialchars($a['type_absence']) ?></td>
                <td style="font-size:12px;max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= htmlspecialchars($a['motif'] ?? '') ?>"><?= htmlspecialchars($a['motif'] ?? '-') ?></td>
                <td><span class="badge-j <?= $a['justifie'] ? 'badge-oui' : 'badge-non' ?>"><?= $a['justifie'] ? 'Oui' : 'Non' ?></span></td>
                <td>
                    <form method="post" style="display:inline"><input type="hidden" name="csrf_token" value="<?= $csrf_token ?>"><input type="hidden" name="action" value="toggle_justify_absence"><input type="hidden" name="absence_id" value="<?= $a['id'] ?>"><button class="btn-xs <?= $a['justifie'] ? 'warning' : 'success' ?>" title="<?= $a['justifie'] ? 'Retirer justification' : 'Justifier' ?>"><i class="fas fa-<?= $a['justifie'] ? 'times' : 'check' ?>"></i></button></form>
                    <form method="post" style="display:inline" onsubmit="return confirm('Supprimer ?')"><input type="hidden" name="csrf_token" value="<?= $csrf_token ?>"><input type="hidden" name="action" value="delete_absence"><input type="hidden" name="absence_id" value="<?= $a['id'] ?>"><button class="btn-xs danger"><i class="fas fa-trash"></i></button></form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <?php else: ?>
    <!-- Tableau Retards -->
    <?php if (empty($retards)): ?>
        <div style="text-align:center;padding:40px;color:#999"><p>Aucun retard trouvé.</p></div>
    <?php else: ?>
    <table class="data-table">
        <thead><tr><th>Élève</th><th>Classe</th><th>Date</th><th>Durée</th><th>Motif</th><th>Justifié</th><th>Actions</th></tr></thead>
        <tbody>
            <?php foreach ($retards as $r): ?>
            <tr>
                <td><strong><?= htmlspecialchars($r['eleve_prenom'] . ' ' . $r['eleve_nom']) ?></strong></td>
                <td><?= htmlspecialchars($r['classe']) ?></td>
                <td style="font-size:12px"><?= date('d/m/Y H:i', strtotime($r['date_retard'])) ?></td>
                <td><?= $r['duree_minutes'] ?> min</td>
                <td style="font-size:12px;max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= htmlspecialchars($r['motif'] ?? '-') ?></td>
                <td><span class="badge-j <?= $r['justifie'] ? 'badge-oui' : 'badge-non' ?>"><?= $r['justifie'] ? 'Oui' : 'Non' ?></span></td>
                <td>
                    <form method="post" style="display:inline"><input type="hidden" name="csrf_token" value="<?= $csrf_token ?>"><input type="hidden" name="action" value="toggle_justify_retard"><input type="hidden" name="retard_id" value="<?= $r['id'] ?>"><button class="btn-xs <?= $r['justifie'] ? 'warning' : 'success' ?>"><i class="fas fa-<?= $r['justifie'] ? 'times' : 'check' ?>"></i></button></form>
                    <form method="post" style="display:inline" onsubmit="return confirm('Supprimer ?')"><input type="hidden" name="csrf_token" value="<?= $csrf_token ?>"><input type="hidden" name="action" value="delete_retard"><input type="hidden" name="retard_id" value="<?= $r['id'] ?>"><button class="btn-xs danger"><i class="fas fa-trash"></i></button></form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; endif; ?>
</div>

<!-- Modal Ajouter Absence -->
<div class="modal-overlay" id="addAbsModal">
    <div class="modal-box">
        <h3><i class="fas fa-user-times"></i> Ajouter une absence</h3>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
            <input type="hidden" name="action" value="add_absence">
            <div class="form-group"><label>Élève</label><select name="id_eleve" required><option value="">Sélectionner…</option>
                <?php foreach ($eleves as $el): ?><option value="<?= $el['id'] ?>"><?= htmlspecialchars($el['prenom'] . ' ' . $el['nom'] . ' (' . $el['classe'] . ')') ?></option><?php endforeach; ?>
            </select></div>
            <div class="form-row">
                <div class="form-group"><label>Début</label><input type="datetime-local" name="date_debut" required></div>
                <div class="form-group"><label>Fin</label><input type="datetime-local" name="date_fin" required></div>
            </div>
            <div class="form-group"><label>Type</label><select name="type_absence"><option>absence</option><option>maladie</option><option>famille</option><option>autre</option></select></div>
            <div class="form-group"><label>Motif</label><input type="text" name="motif"></div>
            <div class="form-group"><label>Commentaire</label><textarea name="commentaire" rows="2"></textarea></div>
            <div style="display:flex;gap:8px;justify-content:flex-end"><button type="button" class="btn btn-secondary" onclick="document.getElementById('addAbsModal').classList.remove('active')">Annuler</button><button type="submit" class="btn btn-primary">Ajouter</button></div>
        </form>
    </div>
</div>

<!-- Modal Ajouter Retard -->
<div class="modal-overlay" id="addRetardModal">
    <div class="modal-box">
        <h3><i class="fas fa-clock"></i> Ajouter un retard</h3>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
            <input type="hidden" name="action" value="add_retard">
            <div class="form-group"><label>Élève</label><select name="id_eleve" required><option value="">Sélectionner…</option>
                <?php foreach ($eleves as $el): ?><option value="<?= $el['id'] ?>"><?= htmlspecialchars($el['prenom'] . ' ' . $el['nom'] . ' (' . $el['classe'] . ')') ?></option><?php endforeach; ?>
            </select></div>
            <div class="form-row">
                <div class="form-group"><label>Date/Heure</label><input type="datetime-local" name="date_retard" required></div>
                <div class="form-group"><label>Durée (min)</label><input type="number" name="duree_minutes" min="1" required></div>
            </div>
            <div class="form-group"><label>Motif</label><input type="text" name="motif"></div>
            <div style="display:flex;gap:8px;justify-content:flex-end"><button type="button" class="btn btn-secondary" onclick="document.getElementById('addRetardModal').classList.remove('active')">Annuler</button><button type="submit" class="btn btn-primary">Ajouter</button></div>
        </form>
    </div>
</div>

<script>
document.querySelectorAll('.modal-overlay').forEach(m => m.addEventListener('click', e => { if (e.target === m) m.classList.remove('active'); }));
</script>

<?php include __DIR__ . '/../includes/sub_footer.php'; ?>
