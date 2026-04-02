<?php
/**
 * Administration des notes — filtres, vue par élève/classe/matière, CRUD, stats
 */
require_once __DIR__ . '/../../API/core.php';
require_once __DIR__ . '/../includes/admin_functions.php';

requireAuth();
requireRole('administrateur');

$pdo = getPDO();
$admin = getCurrentUser();
$noteService = app('notes');
$message = '';
$error = '';

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Charger listes de référence
$classes = $pdo->query("SELECT id, nom FROM classes WHERE actif = 1 ORDER BY nom")->fetchAll(PDO::FETCH_ASSOC);
$matieres = $pdo->query("SELECT id, nom, code FROM matieres WHERE actif = 1 ORDER BY nom")->fetchAll(PDO::FETCH_ASSOC);
$professeurs = $pdo->query("SELECT id, nom, prenom FROM professeurs ORDER BY nom")->fetchAll(PDO::FETCH_ASSOC);
$periodes = [];
try { $periodes = app('periodes')->getAll(); } catch (Exception $e) {}

// POST Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['csrf_token'] ?? '') === $csrf_token) {
    $action = $_POST['action'] ?? '';

    if ($action === 'edit_note') {
        $nid = intval($_POST['note_id'] ?? 0);
        if ($nid > 0) {
            $oldData = $noteService->getById($nid);
            $data = [
                'note'        => floatval($_POST['note'] ?? 0),
                'note_sur'    => floatval($_POST['note_sur'] ?? 20),
                'coefficient' => floatval($_POST['coefficient'] ?? 1),
                'commentaire' => trim($_POST['commentaire'] ?? ''),
            ];
            $noteService->update($nid, $data);
            logAudit('note_edited', 'notes', $nid, $oldData, $data);
            $message = "Note modifiée.";
        }
    }

    if ($action === 'delete_note') {
        $nid = intval($_POST['note_id'] ?? 0);
        if ($nid > 0) {
            $oldData = $noteService->getById($nid);
            $noteService->delete($nid);
            logAudit('note_deleted', 'notes', $nid, $oldData);
            $message = "Note supprimée.";
        }
    }

    if ($action === 'add_note') {
        $idEleve = intval($_POST['id_eleve'] ?? 0);
        $idMatiere = intval($_POST['id_matiere'] ?? 0);
        $idProf = intval($_POST['id_professeur'] ?? 0);

        if ($idEleve > 0 && $idMatiere > 0 && $idProf > 0) {
            $data = [
                'id_eleve'        => $idEleve,
                'id_matiere'      => $idMatiere,
                'id_professeur'   => $idProf,
                'note'            => floatval($_POST['note'] ?? 0),
                'note_sur'        => floatval($_POST['note_sur'] ?? 20),
                'coefficient'     => floatval($_POST['coefficient'] ?? 1),
                'type_evaluation' => trim($_POST['type_evaluation'] ?? 'Contrôle'),
                'date_note'       => $_POST['date_note'] ?? date('Y-m-d'),
                'trimestre'       => intval($_POST['trimestre'] ?? 1),
                'commentaire'     => trim($_POST['commentaire'] ?? ''),
            ];
            $newId = $noteService->create($data);
            logAudit('note_added', 'notes', $newId);
            $message = "Note ajoutée.";
        }
    }
}

// Filtres
$filterClasse = $_GET['classe'] ?? '';
$filterMatiere = intval($_GET['matiere'] ?? 0);
$filterTrimestre = $_GET['trimestre'] ?? '';
$filterProf = intval($_GET['prof'] ?? 0);
$filterEleve = trim($_GET['eleve'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 50;

// Build filter array for service
$filters = [];
if (!empty($filterClasse))    $filters['classe'] = $filterClasse;
if ($filterMatiere > 0)       $filters['matiere_id'] = $filterMatiere;
if ($filterTrimestre !== '')   $filters['trimestre'] = intval($filterTrimestre);
if ($filterProf > 0)          $filters['professeur_id'] = $filterProf;
if (!empty($filterEleve))     $filters['eleve_id'] = $filterEleve; // Note: service filters by eleve_id, not name

// Use service for filtered results
$result = $noteService->getFiltered($filters, $page, $perPage);
$notes = $result['data'];
$totalNotes = $result['total'];
$totalPages = $result['pages'];

// Stats globales — computed from all matching notes (not just current page)
$allResult = $noteService->getFiltered($filters, 1, PHP_INT_MAX);
$allNotes = $allResult['data'];
$stats = ['total' => $allResult['total'], 'moyenne' => null, 'min_note' => null, 'max_note' => null];
if (!empty($allNotes)) {
    $normalized = array_map(fn($n) => $n['note_sur'] > 0 ? round($n['note'] / $n['note_sur'] * 20, 2) : 0, $allNotes);
    $stats['moyenne'] = round(array_sum($normalized) / count($normalized), 2);
    $stats['min_note'] = min($normalized);
    $stats['max_note'] = max($normalized);
}

// Charger élèves pour le modal d'ajout
$eleves = $pdo->query("SELECT id, nom, prenom, classe FROM eleves WHERE actif = 1 ORDER BY nom, prenom")->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Gestion des notes';
$currentPage = 'notes';
$extraCss = ['../../assets/css/admin.css'];

ob_start();
?>
<style>
    .notes-container { max-width: 1200px; margin: 0 auto; }
    .filters { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 20px; background: white; padding: 15px; border-radius: 10px; box-shadow: 0 2px 6px rgba(0,0,0,0.05); align-items: flex-end; }
    .filters .fg { display: flex; flex-direction: column; gap: 4px; }
    .filters label { font-size: 12px; font-weight: 600; color: #4a5568; }
    .filters select, .filters input { padding: 7px 10px; border: 1px solid #d2d6dc; border-radius: 6px; font-size: 13px; }
    .notes-table { width: 100%; border-collapse: collapse; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
    .notes-table th, .notes-table td { padding: 8px 12px; text-align: left; border-bottom: 1px solid #f0f0f0; font-size: 13px; }
    .notes-table th { background: #f7fafc; font-weight: 600; color: #4a5568; font-size: 12px; }
    .note-val { font-weight: 700; font-size: 14px; }
    .note-high { color: #059669; } .note-mid { color: #f59e0b; } .note-low { color: #dc2626; }
</style>
<?php
$extraHeadHtml = ob_get_clean();
include __DIR__ . '/../includes/sub_header.php';
?>

<div class="notes-container">
    <?php if (!empty($message)): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if (!empty($error)): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <!-- Stats -->
    <div class="stats-bar">
        <div class="stat-card"><div class="val"><?= $stats['total'] ?? 0 ?></div><div class="lbl">Notes</div></div>
        <div class="stat-card"><div class="val"><?= $stats['moyenne'] ?? '-' ?>/20</div><div class="lbl">Moyenne</div></div>
        <div class="stat-card"><div class="val"><?= $stats['min_note'] ?? '-' ?></div><div class="lbl">Min</div></div>
        <div class="stat-card"><div class="val"><?= $stats['max_note'] ?? '-' ?></div><div class="lbl">Max</div></div>
    </div>

    <!-- Filtres -->
    <form method="get" class="filters">
        <div class="fg">
            <label>Classe</label>
            <select name="classe"><option value="">Toutes</option>
                <?php foreach ($classes as $c): ?><option value="<?= htmlspecialchars($c['nom']) ?>" <?= $filterClasse === $c['nom'] ? 'selected' : '' ?>><?= htmlspecialchars($c['nom']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="fg">
            <label>Matière</label>
            <select name="matiere"><option value="">Toutes</option>
                <?php foreach ($matieres as $m): ?><option value="<?= $m['id'] ?>" <?= $filterMatiere == $m['id'] ? 'selected' : '' ?>><?= htmlspecialchars($m['nom']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="fg">
            <label>Trimestre</label>
            <select name="trimestre"><option value="">Tous</option>
                <option value="1" <?= $filterTrimestre === '1' ? 'selected' : '' ?>>T1</option>
                <option value="2" <?= $filterTrimestre === '2' ? 'selected' : '' ?>>T2</option>
                <option value="3" <?= $filterTrimestre === '3' ? 'selected' : '' ?>>T3</option>
            </select>
        </div>
        <div class="fg">
            <label>Professeur</label>
            <select name="prof"><option value="">Tous</option>
                <?php foreach ($professeurs as $p): ?><option value="<?= $p['id'] ?>" <?= $filterProf == $p['id'] ? 'selected' : '' ?>><?= htmlspecialchars($p['prenom'] . ' ' . $p['nom']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="fg">
            <label>Élève</label>
            <input type="text" name="eleve" value="<?= htmlspecialchars($filterEleve) ?>" placeholder="Nom…">
        </div>
        <button type="submit" class="btn btn-primary" style="height:35px"><i class="fas fa-filter"></i> Filtrer</button>
        <a href="notes.php" class="btn btn-secondary" style="height:35px;line-height:35px;text-decoration:none">Réinitialiser</a>
        <button type="button" class="btn btn-success" style="height:35px;margin-left:auto" onclick="openAdd()"><i class="fas fa-plus"></i> Ajouter</button>
    </form>

    <!-- Table -->
    <?php if (empty($notes)): ?>
        <div style="text-align:center;padding:40px;color:#999"><i class="fas fa-clipboard" style="font-size:36px;opacity:0.3"></i><p>Aucune note trouvée.</p></div>
    <?php else: ?>
    <table class="notes-table">
        <thead><tr><th>Élève</th><th>Classe</th><th>Matière</th><th>Note</th><th>Coef</th><th>Type</th><th>Date</th><th>Prof</th><th>T</th><th>Actions</th></tr></thead>
        <tbody>
            <?php foreach ($notes as $n):
                $pct = ($n['note_sur'] > 0) ? ($n['note'] / $n['note_sur'] * 100) : 0;
                $colorClass = $pct >= 60 ? 'note-high' : ($pct >= 40 ? 'note-mid' : 'note-low');
            ?>
            <tr>
                <td><strong><?= htmlspecialchars($n['eleve_prenom'] . ' ' . $n['eleve_nom']) ?></strong></td>
                <td><?= htmlspecialchars($n['classe']) ?></td>
                <td><span title="<?= htmlspecialchars($n['matiere_nom']) ?>"><?= htmlspecialchars($n['matiere_code']) ?></span></td>
                <td><span class="note-val <?= $colorClass ?>"><?= $n['note'] ?>/<?= $n['note_sur'] ?></span></td>
                <td><?= $n['coefficient'] ?></td>
                <td style="font-size:12px"><?= htmlspecialchars($n['type_evaluation']) ?></td>
                <td style="font-size:12px"><?= date('d/m/Y', strtotime($n['date_note'])) ?></td>
                <td style="font-size:12px"><?= htmlspecialchars($n['prof_prenom'][0] . '. ' . $n['prof_nom']) ?></td>
                <td>T<?= $n['trimestre'] ?></td>
                <td>
                    <button class="btn-xs primary" onclick='openEdit(<?= json_encode($n) ?>)'><i class="fas fa-pen"></i></button>
                    <form method="post" style="display:inline" onsubmit="return confirm('Supprimer cette note ?')"><input type="hidden" name="csrf_token" value="<?= $csrf_token ?>"><input type="hidden" name="action" value="delete_note"><input type="hidden" name="note_id" value="<?= $n['id'] ?>"><button class="btn-xs danger"><i class="fas fa-trash"></i></button></form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <?php if ($totalPages > 1): ?>
    <div class="pagination">
        <?php
        $qs = http_build_query(array_filter(['classe' => $filterClasse, 'matiere' => $filterMatiere, 'trimestre' => $filterTrimestre, 'prof' => $filterProf, 'eleve' => $filterEleve]));
        for ($i = 1; $i <= $totalPages; $i++):
            $link = "notes.php?" . $qs . "&page=$i";
            if ($i === $page): ?><span class="current"><?= $i ?></span>
            <?php else: ?><a href="<?= $link ?>"><?= $i ?></a>
            <?php endif; endfor; ?>
    </div>
    <?php endif; endif; ?>
</div>

<!-- Modal Ajouter -->
<div class="modal-overlay" id="addModal">
    <div class="modal-box">
        <h3><i class="fas fa-plus"></i> Ajouter une note</h3>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
            <input type="hidden" name="action" value="add_note">
            <div class="form-group">
                <label>Élève</label>
                <select name="id_eleve" required>
                    <option value="">Sélectionner…</option>
                    <?php foreach ($eleves as $el): ?><option value="<?= $el['id'] ?>"><?= htmlspecialchars($el['prenom'] . ' ' . $el['nom'] . ' (' . $el['classe'] . ')') ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Matière</label><select name="id_matiere" required>
                    <?php foreach ($matieres as $m): ?><option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['nom']) ?></option><?php endforeach; ?>
                </select></div>
                <div class="form-group"><label>Professeur</label><select name="id_professeur" required>
                    <?php foreach ($professeurs as $p): ?><option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['prenom'] . ' ' . $p['nom']) ?></option><?php endforeach; ?>
                </select></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Note</label><input type="number" name="note" step="0.01" min="0" max="20" required></div>
                <div class="form-group"><label>Sur</label><input type="number" name="note_sur" value="20" step="0.01" min="1"></div>
                <div class="form-group"><label>Coef</label><input type="number" name="coefficient" value="1" step="0.01" min="0.01"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Type</label><select name="type_evaluation"><option>Contrôle</option><option>Devoir maison</option><option>Interrogation</option><option>Examen</option><option>TP</option></select></div>
                <div class="form-group"><label>Date</label><input type="date" name="date_note" value="<?= date('Y-m-d') ?>" required></div>
                <div class="form-group"><label>Trimestre</label><select name="trimestre"><option value="1">T1</option><option value="2">T2</option><option value="3">T3</option></select></div>
            </div>
            <div class="form-group"><label>Commentaire</label><textarea name="commentaire" rows="2"></textarea></div>
            <div style="display:flex;gap:8px;justify-content:flex-end"><button type="button" class="btn btn-secondary" onclick="closeModal('addModal')">Annuler</button><button type="submit" class="btn btn-primary">Ajouter</button></div>
        </form>
    </div>
</div>

<!-- Modal Modifier -->
<div class="modal-overlay" id="editModal">
    <div class="modal-box">
        <h3><i class="fas fa-pen"></i> Modifier la note</h3>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
            <input type="hidden" name="action" value="edit_note">
            <input type="hidden" name="note_id" id="edit_note_id">
            <div class="form-row">
                <div class="form-group"><label>Note</label><input type="number" name="note" id="edit_note" step="0.01" min="0" required></div>
                <div class="form-group"><label>Sur</label><input type="number" name="note_sur" id="edit_note_sur" step="0.01" min="1"></div>
                <div class="form-group"><label>Coef</label><input type="number" name="coefficient" id="edit_coef" step="0.01"></div>
            </div>
            <div class="form-group"><label>Commentaire</label><textarea name="commentaire" id="edit_comment" rows="2"></textarea></div>
            <div style="display:flex;gap:8px;justify-content:flex-end"><button type="button" class="btn btn-secondary" onclick="closeModal('editModal')">Annuler</button><button type="submit" class="btn btn-primary">Enregistrer</button></div>
        </form>
    </div>
</div>

<script>
function openAdd() { document.getElementById('addModal').classList.add('active'); }
function openEdit(n) {
    document.getElementById('edit_note_id').value = n.id;
    document.getElementById('edit_note').value = n.note;
    document.getElementById('edit_note_sur').value = n.note_sur;
    document.getElementById('edit_coef').value = n.coefficient;
    document.getElementById('edit_comment').value = n.commentaire || '';
    document.getElementById('editModal').classList.add('active');
}
function closeModal(id) { document.getElementById(id).classList.remove('active'); }
document.querySelectorAll('.modal-overlay').forEach(m => m.addEventListener('click', e => { if (e.target === m) m.classList.remove('active'); }));
</script>

<?php include __DIR__ . '/../includes/sub_footer.php'; ?>
