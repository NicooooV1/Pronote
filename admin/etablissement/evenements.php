<?php
/**
 * Gestion des événements — vue admin, CRUD
 */
require_once __DIR__ . '/../../API/core.php';
require_once __DIR__ . '/../includes/admin_functions.php';
require_once __DIR__ . '/../../login/src/auth.php';

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

    if ($action === 'delete') {
        $eid = intval($_POST['event_id'] ?? 0);
        if ($eid > 0) {
            $pdo->prepare("DELETE FROM evenements WHERE id = ?")->execute([$eid]);
            logAudit('event_deleted', 'evenements', $eid);
            $message = "Événement supprimé.";
        }
    }

    if ($action === 'toggle_status') {
        $eid = intval($_POST['event_id'] ?? 0);
        if ($eid > 0) {
            $cur = $pdo->prepare("SELECT statut FROM evenements WHERE id = ?"); $cur->execute([$eid]);
            $st = $cur->fetchColumn();
            $newSt = ($st === 'actif') ? 'annule' : 'actif';
            $pdo->prepare("UPDATE evenements SET statut = ? WHERE id = ?")->execute([$newSt, $eid]);
            logAudit('event_status_toggled', 'evenements', $eid);
            $message = "Statut mis à jour.";
        }
    }

    if ($action === 'edit') {
        $eid = intval($_POST['event_id'] ?? 0);
        $titre = trim($_POST['titre'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $dateDebut = $_POST['date_debut'] ?? '';
        $dateFin = $_POST['date_fin'] ?? '';
        $typeEv = $_POST['type_evenement'] ?? '';
        $lieu = trim($_POST['lieu'] ?? '');
        if ($eid > 0 && !empty($titre)) {
            $pdo->prepare("UPDATE evenements SET titre = ?, description = ?, date_debut = ?, date_fin = ?, type_evenement = ?, lieu = ? WHERE id = ?")
                ->execute([$titre, $desc, $dateDebut, $dateFin, $typeEv, $lieu, $eid]);
            logAudit('event_edited', 'evenements', $eid);
            $message = "Événement modifié.";
        }
    }
}

// Filtres
$filterType = $_GET['type'] ?? '';
$filterStatus = $_GET['status'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 30;
$offset = ($page - 1) * $perPage;

$where = []; $params = [];
if (!empty($filterType)) { $where[] = "type_evenement = ?"; $params[] = $filterType; }
if (!empty($filterStatus)) { $where[] = "statut = ?"; $params[] = $filterStatus; }
$whereSQL = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

$total = $pdo->prepare("SELECT COUNT(*) FROM evenements $whereSQL"); $total->execute($params);
$totalEvents = $total->fetchColumn();
$totalPages = max(1, ceil($totalEvents / $perPage));

$events = $pdo->prepare("SELECT * FROM evenements $whereSQL ORDER BY date_debut DESC LIMIT $perPage OFFSET $offset");
$events->execute($params);
$evenements = $events->fetchAll(PDO::FETCH_ASSOC);

$types = $pdo->query("SELECT DISTINCT type_evenement FROM evenements ORDER BY type_evenement")->fetchAll(PDO::FETCH_COLUMN);

$pageTitle = 'Événements';
$currentPage = 'etab_evenements';

ob_start();
?>
<style>
    .ev-container { max-width: 1100px; margin: 0 auto; }
    .filters { display: flex; gap: 10px; margin-bottom: 15px; align-items: flex-end; }
    .filters select { padding: 7px 10px; border: 1px solid #d2d6dc; border-radius: 6px; font-size: 13px; }
    .ev-table { width: 100%; border-collapse: collapse; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
    .ev-table th, .ev-table td { padding: 10px 14px; text-align: left; border-bottom: 1px solid #f0f0f0; font-size: 13px; }
    .ev-table th { background: #f7fafc; font-weight: 600; color: #4a5568; font-size: 12px; }
    .badge-ev { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 600; }
    .badge-actif { background: #d1fae5; color: #065f46; } .badge-annule { background: #fee2e2; color: #991b1b; }
    .btn-xs { padding: 3px 7px; font-size: 11px; border: none; border-radius: 4px; cursor: pointer; color: white; }
    .btn-xs.primary { background: #0f4c81; } .btn-xs.danger { background: #dc2626; } .btn-xs.warning { background: #f59e0b; }
    .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; }
    .modal-overlay.active { display: flex; }
    .modal-box { background: white; border-radius: 12px; padding: 25px; width: 500px; max-height: 80vh; overflow-y: auto; }
    .form-group { margin-bottom: 12px; }
    .form-group label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 4px; color: #4a5568; }
    .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 8px; border: 1px solid #d2d6dc; border-radius: 6px; font-size: 13px; box-sizing: border-box; }
    .form-row { display: flex; gap: 10px; } .form-row .form-group { flex: 1; }
</style>
<?php
$extraHeadHtml = ob_get_clean();
include __DIR__ . '/../includes/sub_header.php';
?>

<div class="ev-container">
    <?php if (!empty($message)): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>

    <form method="get" class="filters">
        <select name="type"><option value="">Tous types</option>
            <?php foreach ($types as $t): ?><option value="<?= htmlspecialchars($t) ?>" <?= $filterType === $t ? 'selected' : '' ?>><?= htmlspecialchars($t) ?></option><?php endforeach; ?>
        </select>
        <select name="status"><option value="">Tout statut</option><option value="actif" <?= $filterStatus === 'actif' ? 'selected' : '' ?>>Actif</option><option value="annule" <?= $filterStatus === 'annule' ? 'selected' : '' ?>>Annulé</option></select>
        <button class="btn btn-primary" style="height:35px"><i class="fas fa-filter"></i></button>
    </form>

    <?php if (empty($evenements)): ?>
        <div style="text-align:center;padding:40px;color:#999"><p>Aucun événement trouvé.</p></div>
    <?php else: ?>
    <table class="ev-table">
        <thead><tr><th>Titre</th><th>Type</th><th>Date début</th><th>Date fin</th><th>Lieu</th><th>Statut</th><th>Créateur</th><th>Actions</th></tr></thead>
        <tbody>
            <?php foreach ($evenements as $ev): ?>
            <tr>
                <td><strong><?= htmlspecialchars($ev['titre']) ?></strong></td>
                <td style="font-size:12px"><?= htmlspecialchars($ev['type_evenement']) ?></td>
                <td style="font-size:12px"><?= date('d/m/Y H:i', strtotime($ev['date_debut'])) ?></td>
                <td style="font-size:12px"><?= date('d/m/Y H:i', strtotime($ev['date_fin'])) ?></td>
                <td style="font-size:12px"><?= htmlspecialchars($ev['lieu'] ?? '-') ?></td>
                <td><span class="badge-ev badge-<?= $ev['statut'] ?>"><?= $ev['statut'] ?></span></td>
                <td style="font-size:12px"><?= htmlspecialchars($ev['createur']) ?></td>
                <td>
                    <button class="btn-xs primary" onclick='openEdit(<?= json_encode($ev) ?>)'><i class="fas fa-pen"></i></button>
                    <form method="post" style="display:inline"><input type="hidden" name="csrf_token" value="<?= $csrf_token ?>"><input type="hidden" name="action" value="toggle_status"><input type="hidden" name="event_id" value="<?= $ev['id'] ?>"><button class="btn-xs warning" title="Changer statut"><i class="fas fa-<?= $ev['statut'] === 'actif' ? 'ban' : 'check' ?>"></i></button></form>
                    <form method="post" style="display:inline" onsubmit="return confirm('Supprimer ?')"><input type="hidden" name="csrf_token" value="<?= $csrf_token ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="event_id" value="<?= $ev['id'] ?>"><button class="btn-xs danger"><i class="fas fa-trash"></i></button></form>
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
        <h3><i class="fas fa-pen"></i> Modifier l'événement</h3>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>"><input type="hidden" name="action" value="edit"><input type="hidden" name="event_id" id="e_eid">
            <div class="form-group"><label>Titre</label><input type="text" name="titre" id="e_titre" required></div>
            <div class="form-group"><label>Description</label><textarea name="description" id="e_desc" rows="3"></textarea></div>
            <div class="form-row">
                <div class="form-group"><label>Début</label><input type="datetime-local" name="date_debut" id="e_dd"></div>
                <div class="form-group"><label>Fin</label><input type="datetime-local" name="date_fin" id="e_df"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Type</label><input type="text" name="type_evenement" id="e_type"></div>
                <div class="form-group"><label>Lieu</label><input type="text" name="lieu" id="e_lieu"></div>
            </div>
            <div style="display:flex;gap:8px;justify-content:flex-end"><button type="button" class="btn btn-secondary" onclick="document.getElementById('editModal').classList.remove('active')">Annuler</button><button type="submit" class="btn btn-primary">Enregistrer</button></div>
        </form>
    </div>
</div>

<script>
function openEdit(ev) {
    document.getElementById('e_eid').value = ev.id;
    document.getElementById('e_titre').value = ev.titre;
    document.getElementById('e_desc').value = ev.description || '';
    document.getElementById('e_dd').value = ev.date_debut ? ev.date_debut.replace(' ', 'T').substring(0,16) : '';
    document.getElementById('e_df').value = ev.date_fin ? ev.date_fin.replace(' ', 'T').substring(0,16) : '';
    document.getElementById('e_type').value = ev.type_evenement;
    document.getElementById('e_lieu').value = ev.lieu || '';
    document.getElementById('editModal').classList.add('active');
}
document.querySelectorAll('.modal-overlay').forEach(m => m.addEventListener('click', e => { if (e.target === m) m.classList.remove('active'); }));
</script>

<?php include __DIR__ . '/../includes/sub_footer.php'; ?>
