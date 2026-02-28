<?php
/**
 * Gestion des périodes scolaires (trimestres/semestres)
 */
require_once __DIR__ . '/../../API/core.php';
require_once __DIR__ . '/../includes/admin_functions.php';
require_once __DIR__ . '/../../login/src/auth.php';

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
        $numero = intval($_POST['numero'] ?? 1);
        $type = $_POST['type'] ?? 'trimestre';
        $dateDebut = $_POST['date_debut'] ?? '';
        $dateFin = $_POST['date_fin'] ?? '';
        if (!empty($nom) && $dateDebut && $dateFin) {
            $pdo->prepare("INSERT INTO periodes (nom, numero, type, date_debut, date_fin) VALUES (?,?,?,?,?)")
                ->execute([$nom, $numero, $type, $dateDebut, $dateFin]);
            logAudit('periode_created', 'periodes', $pdo->lastInsertId());
            $message = "Période créée.";
        }
    }

    if ($action === 'edit') {
        $pid = intval($_POST['periode_id'] ?? 0);
        $nom = trim($_POST['nom'] ?? '');
        $numero = intval($_POST['numero'] ?? 1);
        $type = $_POST['type'] ?? 'trimestre';
        $dateDebut = $_POST['date_debut'] ?? '';
        $dateFin = $_POST['date_fin'] ?? '';
        if ($pid > 0 && !empty($nom)) {
            $pdo->prepare("UPDATE periodes SET nom = ?, numero = ?, type = ?, date_debut = ?, date_fin = ? WHERE id = ?")
                ->execute([$nom, $numero, $type, $dateDebut, $dateFin, $pid]);
            logAudit('periode_edited', 'periodes', $pid);
            $message = "Période modifiée.";
        }
    }

    if ($action === 'delete') {
        $pid = intval($_POST['periode_id'] ?? 0);
        if ($pid > 0) {
            $pdo->prepare("DELETE FROM periodes WHERE id = ?")->execute([$pid]);
            logAudit('periode_deleted', 'periodes', $pid);
            $message = "Période supprimée.";
        }
    }
}

$periodes = $pdo->query("SELECT * FROM periodes ORDER BY numero")->fetchAll(PDO::FETCH_ASSOC);

// Vérifier chevauchements
$overlaps = [];
for ($i = 0; $i < count($periodes); $i++) {
    for ($j = $i + 1; $j < count($periodes); $j++) {
        if ($periodes[$i]['date_debut'] <= $periodes[$j]['date_fin'] && $periodes[$j]['date_debut'] <= $periodes[$i]['date_fin']) {
            $overlaps[] = $periodes[$i]['nom'] . ' / ' . $periodes[$j]['nom'];
        }
    }
}

$pageTitle = 'Périodes scolaires';
$currentPage = 'etab_periodes';

ob_start();
?>
<style>
    .per-container { max-width: 900px; margin: 0 auto; }
    .top-bar { display: flex; justify-content: flex-end; margin-bottom: 15px; }
    .alert-warn { background: #fef3cd; border-left: 4px solid #f59e0b; padding: 10px 14px; border-radius: 6px; margin-bottom: 15px; font-size: 13px; }
    .timeline { position: relative; }
    .period-card { background: white; border-radius: 10px; padding: 18px; margin-bottom: 12px; box-shadow: 0 2px 6px rgba(0,0,0,0.06); display: flex; align-items: center; gap: 15px; border-left: 4px solid #0f4c81; }
    .period-number { width: 40px; height: 40px; border-radius: 50%; background: #0f4c81; color: white; display: flex; align-items: center; justify-content: center; font-size: 18px; font-weight: 700; }
    .period-info { flex: 1; }
    .period-info h4 { margin: 0 0 4px; font-size: 16px; }
    .period-meta { font-size: 13px; color: #666; }
    .period-dates { font-size: 13px; display: flex; gap: 10px; align-items: center; }
    .date-badge { background: #eff6ff; padding: 4px 10px; border-radius: 6px; color: #1e40af; font-weight: 500; }
    .badge-type { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 600; background: #e2e8f0; color: #4a5568; }
    .period-actions { display: flex; gap: 6px; }
    .btn-xs { padding: 4px 8px; font-size: 11px; border: none; border-radius: 4px; cursor: pointer; color: white; }
    .btn-xs.primary { background: #0f4c81; } .btn-xs.danger { background: #dc2626; }
    .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; }
    .modal-overlay.active { display: flex; }
    .modal-box { background: white; border-radius: 12px; padding: 25px; width: 450px; }
    .form-group { margin-bottom: 12px; }
    .form-group label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 4px; color: #4a5568; }
    .form-group input, .form-group select { width: 100%; padding: 8px; border: 1px solid #d2d6dc; border-radius: 6px; font-size: 13px; box-sizing: border-box; }
    .form-row { display: flex; gap: 10px; } .form-row .form-group { flex: 1; }
    .current-badge { background: #d1fae5; color: #065f46; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 600; margin-left: 6px; }
</style>
<?php
$extraHeadHtml = ob_get_clean();
include __DIR__ . '/../includes/sub_header.php';
?>

<div class="per-container">
    <?php if (!empty($message)): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if (!empty($error)): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <?php if (!empty($overlaps)): ?>
    <div class="alert-warn"><strong><i class="fas fa-exclamation-triangle"></i> Chevauchement détecté :</strong> <?= htmlspecialchars(implode(', ', $overlaps)) ?></div>
    <?php endif; ?>

    <div class="top-bar">
        <button class="btn btn-primary" onclick="document.getElementById('createModal').classList.add('active')"><i class="fas fa-plus"></i> Nouvelle période</button>
    </div>

    <div class="timeline">
        <?php if (empty($periodes)): ?>
            <div style="text-align:center;padding:40px;color:#999"><p>Aucune période définie.</p></div>
        <?php endif; ?>
        <?php foreach ($periodes as $p):
            $today = date('Y-m-d');
            $isCurrent = ($today >= $p['date_debut'] && $today <= $p['date_fin']);
        ?>
        <div class="period-card" <?= $isCurrent ? 'style="border-left-color:#059669"' : '' ?>>
            <div class="period-number" <?= $isCurrent ? 'style="background:#059669"' : '' ?>><?= $p['numero'] ?></div>
            <div class="period-info">
                <h4><?= htmlspecialchars($p['nom']) ?> <?php if ($isCurrent): ?><span class="current-badge">En cours</span><?php endif; ?></h4>
                <div class="period-meta"><span class="badge-type"><?= htmlspecialchars($p['type']) ?></span></div>
            </div>
            <div class="period-dates">
                <span class="date-badge"><?= date('d/m/Y', strtotime($p['date_debut'])) ?></span>
                <i class="fas fa-arrow-right" style="color:#ccc"></i>
                <span class="date-badge"><?= date('d/m/Y', strtotime($p['date_fin'])) ?></span>
            </div>
            <div class="period-actions">
                <button class="btn-xs primary" onclick='openEdit(<?= json_encode($p) ?>)'><i class="fas fa-pen"></i></button>
                <form method="post" style="display:inline" onsubmit="return confirm('Supprimer ?')"><input type="hidden" name="csrf_token" value="<?= $csrf_token ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="periode_id" value="<?= $p['id'] ?>"><button class="btn-xs danger"><i class="fas fa-trash"></i></button></form>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Modal Créer -->
<div class="modal-overlay" id="createModal">
    <div class="modal-box">
        <h3><i class="fas fa-plus"></i> Nouvelle période</h3>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>"><input type="hidden" name="action" value="create">
            <div class="form-row">
                <div class="form-group"><label>Nom</label><input type="text" name="nom" placeholder="Trimestre 1" required></div>
                <div class="form-group"><label>Numéro</label><input type="number" name="numero" value="1" min="1" max="6"></div>
            </div>
            <div class="form-group"><label>Type</label><select name="type"><option value="trimestre">Trimestre</option><option value="semestre">Semestre</option><option value="annuel">Annuel</option></select></div>
            <div class="form-row">
                <div class="form-group"><label>Date début</label><input type="date" name="date_debut" required></div>
                <div class="form-group"><label>Date fin</label><input type="date" name="date_fin" required></div>
            </div>
            <div style="display:flex;gap:8px;justify-content:flex-end"><button type="button" class="btn btn-secondary" onclick="document.getElementById('createModal').classList.remove('active')">Annuler</button><button type="submit" class="btn btn-primary">Créer</button></div>
        </form>
    </div>
</div>

<!-- Modal Modifier -->
<div class="modal-overlay" id="editModal">
    <div class="modal-box">
        <h3><i class="fas fa-pen"></i> Modifier la période</h3>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>"><input type="hidden" name="action" value="edit"><input type="hidden" name="periode_id" id="e_pid">
            <div class="form-row">
                <div class="form-group"><label>Nom</label><input type="text" name="nom" id="e_nom" required></div>
                <div class="form-group"><label>Numéro</label><input type="number" name="numero" id="e_numero" min="1"></div>
            </div>
            <div class="form-group"><label>Type</label><select name="type" id="e_type"><option value="trimestre">Trimestre</option><option value="semestre">Semestre</option><option value="annuel">Annuel</option></select></div>
            <div class="form-row">
                <div class="form-group"><label>Date début</label><input type="date" name="date_debut" id="e_dd"></div>
                <div class="form-group"><label>Date fin</label><input type="date" name="date_fin" id="e_df"></div>
            </div>
            <div style="display:flex;gap:8px;justify-content:flex-end"><button type="button" class="btn btn-secondary" onclick="document.getElementById('editModal').classList.remove('active')">Annuler</button><button type="submit" class="btn btn-primary">Enregistrer</button></div>
        </form>
    </div>
</div>

<script>
function openEdit(p) {
    document.getElementById('e_pid').value = p.id;
    document.getElementById('e_nom').value = p.nom;
    document.getElementById('e_numero').value = p.numero;
    document.getElementById('e_type').value = p.type;
    document.getElementById('e_dd').value = p.date_debut;
    document.getElementById('e_df').value = p.date_fin;
    document.getElementById('editModal').classList.add('active');
}
document.querySelectorAll('.modal-overlay').forEach(m => m.addEventListener('click', e => { if (e.target === m) m.classList.remove('active'); }));
</script>

<?php include __DIR__ . '/../includes/sub_footer.php'; ?>
