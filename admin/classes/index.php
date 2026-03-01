<?php
/**
 * Gestion des classes — CRUD, effectifs, prof principal, affectation rapide
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

$professeurs = $pdo->query("SELECT id, nom, prenom FROM professeurs ORDER BY nom, prenom")->fetchAll(PDO::FETCH_ASSOC);

// POST Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['csrf_token'] ?? '') === $csrf_token) {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_class') {
        $nom = trim($_POST['nom'] ?? '');
        $niveau = trim($_POST['niveau'] ?? '');
        $annee = trim($_POST['annee_scolaire'] ?? date('Y') . '-' . (date('Y') + 1));
        $ppId = intval($_POST['professeur_principal_id'] ?? 0) ?: null;
        if (!empty($nom) && !empty($niveau)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO classes (nom, niveau, annee_scolaire, professeur_principal_id) VALUES (?,?,?,?)");
                $stmt->execute([$nom, $niveau, $annee, $ppId]);
                logAudit('class_created', 'classes', $pdo->lastInsertId());
                $message = "Classe « $nom » créée.";
            } catch (PDOException $e) {
                $error = str_contains($e->getMessage(), 'Duplicate') ? "Cette classe existe déjà pour cette année." : "Erreur : " . $e->getMessage();
            }
        }
    }

    if ($action === 'edit_class') {
        $cid = intval($_POST['class_id'] ?? 0);
        $nom = trim($_POST['nom'] ?? '');
        $niveau = trim($_POST['niveau'] ?? '');
        $ppId = intval($_POST['professeur_principal_id'] ?? 0) ?: null;
        $actif = isset($_POST['actif']) ? 1 : 0;
        if ($cid > 0 && !empty($nom)) {
            $pdo->prepare("UPDATE classes SET nom = ?, niveau = ?, professeur_principal_id = ?, actif = ? WHERE id = ?")
                ->execute([$nom, $niveau, $ppId, $actif, $cid]);
            logAudit('class_edited', 'classes', $cid);
            $message = "Classe modifiée.";
        }
    }

    if ($action === 'delete_class') {
        $cid = intval($_POST['class_id'] ?? 0);
        if ($cid > 0) {
            // Vérifier les élèves
            $count = $pdo->prepare("SELECT COUNT(*) FROM eleves WHERE classe = (SELECT nom FROM classes WHERE id = ?)"); $count->execute([$cid]);
            if ($count->fetchColumn() > 0) {
                $error = "Impossible de supprimer : des élèves sont encore affectés à cette classe.";
            } else {
                $pdo->prepare("DELETE FROM classes WHERE id = ?")->execute([$cid]);
                logAudit('class_deleted', 'classes', $cid);
                $message = "Classe supprimée.";
            }
        }
    }

    if ($action === 'assign_students') {
        $cid = intval($_POST['class_id'] ?? 0);
        $className = trim($_POST['class_name'] ?? '');
        $studentIds = $_POST['student_ids'] ?? [];
        if ($cid > 0 && !empty($className)) {
            // Retirer tous les élèves de cette classe
            $pdo->prepare("UPDATE eleves SET classe = '' WHERE classe = ?")->execute([$className]);
            // Affecter les sélectionnés
            if (!empty($studentIds)) {
                $in = implode(',', array_map('intval', $studentIds));
                $pdo->exec("UPDATE eleves SET classe = " . $pdo->quote($className) . " WHERE id IN ($in)");
            }
            logAudit('students_assigned', 'classes', $cid, [], ['count' => count($studentIds)]);
            $message = count($studentIds) . " élève(s) affecté(s) à " . htmlspecialchars($className) . ".";
        }
    }
}

// Charger classes avec effectifs
$classes = $pdo->query("
    SELECT c.*,
        (SELECT COUNT(*) FROM eleves e WHERE e.classe = c.nom AND e.actif = 1) AS effectif,
        (SELECT CONCAT(p.prenom, ' ', p.nom) FROM professeurs p WHERE p.id = c.professeur_principal_id) AS pp_nom
    FROM classes c
    ORDER BY c.actif DESC, c.niveau, c.nom
")->fetchAll(PDO::FETCH_ASSOC);

// Stats
$totalClasses = count($classes);
$totalEleves = $pdo->query("SELECT COUNT(*) FROM eleves WHERE actif = 1")->fetchColumn();
$avgEffectif = $totalClasses > 0 ? round($totalEleves / $totalClasses, 1) : 0;

$pageTitle = 'Gestion des classes';
$currentPage = 'classes';
$extraCss = ['../../assets/css/admin.css'];

ob_start();
?>
<style>
    .classes-container { max-width: 1100px; margin: 0 auto; }
    .classes-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 15px; }
    .class-card { background: white; border-radius: 10px; padding: 18px; box-shadow: 0 2px 6px rgba(0,0,0,0.06); border-left: 4px solid #0f4c81; transition: transform 0.2s; }
    .class-card:hover { transform: translateY(-2px); }
    .class-card.inactive { opacity: 0.6; border-left-color: #ccc; }
    .class-card h3 { margin: 0 0 8px; font-size: 18px; display: flex; justify-content: space-between; align-items: center; }
    .class-card .effectif { font-size: 24px; font-weight: 700; color: #0f4c81; }
    .class-meta { font-size: 13px; color: #666; margin-bottom: 8px; }
    .class-actions { display: flex; gap: 6px; margin-top: 10px; }
    .badge-niveau { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 600; background: #e2e8f0; color: #4a5568; }
    .badge-inactive { background: #fee2e2; color: #991b1b; }
    .student-list { max-height: 300px; overflow-y: auto; border: 1px solid #e2e8f0; border-radius: 6px; padding: 8px; }
    .student-list label { display: block; padding: 4px 6px; font-size: 13px; cursor: pointer; border-radius: 4px; }
    .student-list label:hover { background: #f0f4f8; }
</style>
<?php
$extraHeadHtml = ob_get_clean();
include __DIR__ . '/../includes/sub_header.php';
?>

<div class="classes-container">
    <?php if (!empty($message)): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if (!empty($error)): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <div class="stats-bar">
        <div class="stat-card"><div class="val"><?= $totalClasses ?></div><div class="lbl">Classes</div></div>
        <div class="stat-card"><div class="val"><?= $totalEleves ?></div><div class="lbl">Élèves total</div></div>
        <div class="stat-card"><div class="val"><?= $avgEffectif ?></div><div class="lbl">Moyenne/classe</div></div>
    </div>

    <div class="top-bar">
        <button class="btn btn-primary" onclick="document.getElementById('createModal').classList.add('active')"><i class="fas fa-plus"></i> Nouvelle classe</button>
    </div>

    <div class="classes-grid">
        <?php foreach ($classes as $c): ?>
        <div class="class-card <?= $c['actif'] ? '' : 'inactive' ?>">
            <h3>
                <?= htmlspecialchars($c['nom']) ?>
                <span class="effectif"><?= $c['effectif'] ?></span>
            </h3>
            <div class="class-meta">
                <span class="badge-niveau"><?= htmlspecialchars($c['niveau']) ?></span>
                <?php if (!$c['actif']): ?><span class="badge-niveau badge-inactive">Inactive</span><?php endif; ?>
                <div style="margin-top:4px"><?= htmlspecialchars($c['annee_scolaire']) ?></div>
                <?php if (!empty($c['pp_nom'])): ?><div><i class="fas fa-user-tie"></i> <?= htmlspecialchars($c['pp_nom']) ?></div><?php endif; ?>
            </div>
            <div class="class-actions">
                <button class="btn-xs primary" onclick='openEdit(<?= json_encode($c) ?>)' title="Modifier"><i class="fas fa-pen"></i></button>
                <button class="btn-xs success" onclick='openStudents(<?= $c["id"] ?>, <?= json_encode($c["nom"]) ?>)' title="Gérer élèves"><i class="fas fa-users"></i></button>
                <form method="post" style="display:inline" onsubmit="return confirm('Supprimer cette classe ?')"><input type="hidden" name="csrf_token" value="<?= $csrf_token ?>"><input type="hidden" name="action" value="delete_class"><input type="hidden" name="class_id" value="<?= $c['id'] ?>"><button class="btn-xs danger" title="Supprimer"><i class="fas fa-trash"></i></button></form>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Modal Créer -->
<div class="modal-overlay" id="createModal">
    <div class="modal-box">
        <h3><i class="fas fa-plus"></i> Nouvelle classe</h3>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
            <input type="hidden" name="action" value="create_class">
            <div class="form-row">
                <div class="form-group"><label>Nom</label><input type="text" name="nom" placeholder="6èmeA" required></div>
                <div class="form-group"><label>Niveau</label><select name="niveau" required><option value="6ème">6ème</option><option value="5ème">5ème</option><option value="4ème">4ème</option><option value="3ème">3ème</option><option value="2nde">2nde</option><option value="1ère">1ère</option><option value="Terminale">Terminale</option></select></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Année scolaire</label><input type="text" name="annee_scolaire" value="<?= date('Y') . '-' . (date('Y') + 1) ?>"></div>
                <div class="form-group"><label>Prof. principal</label><select name="professeur_principal_id"><option value="">Aucun</option>
                    <?php foreach ($professeurs as $p): ?><option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['prenom'] . ' ' . $p['nom']) ?></option><?php endforeach; ?>
                </select></div>
            </div>
            <div style="display:flex;gap:8px;justify-content:flex-end"><button type="button" class="btn btn-secondary" onclick="document.getElementById('createModal').classList.remove('active')">Annuler</button><button type="submit" class="btn btn-primary">Créer</button></div>
        </form>
    </div>
</div>

<!-- Modal Modifier -->
<div class="modal-overlay" id="editModal">
    <div class="modal-box">
        <h3><i class="fas fa-pen"></i> Modifier la classe</h3>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
            <input type="hidden" name="action" value="edit_class">
            <input type="hidden" name="class_id" id="edit_cid">
            <div class="form-row">
                <div class="form-group"><label>Nom</label><input type="text" name="nom" id="edit_nom" required></div>
                <div class="form-group"><label>Niveau</label><select name="niveau" id="edit_niveau"><option value="6ème">6ème</option><option value="5ème">5ème</option><option value="4ème">4ème</option><option value="3ème">3ème</option><option value="2nde">2nde</option><option value="1ère">1ère</option><option value="Terminale">Terminale</option></select></div>
            </div>
            <div class="form-group"><label>Prof. principal</label><select name="professeur_principal_id" id="edit_pp"><option value="">Aucun</option>
                <?php foreach ($professeurs as $p): ?><option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['prenom'] . ' ' . $p['nom']) ?></option><?php endforeach; ?>
            </select></div>
            <div class="form-group"><label><input type="checkbox" name="actif" id="edit_actif" checked> Active</label></div>
            <div style="display:flex;gap:8px;justify-content:flex-end"><button type="button" class="btn btn-secondary" onclick="document.getElementById('editModal').classList.remove('active')">Annuler</button><button type="submit" class="btn btn-primary">Enregistrer</button></div>
        </form>
    </div>
</div>

<!-- Modal Élèves -->
<div class="modal-overlay" id="studentsModal">
    <div class="modal-box">
        <h3><i class="fas fa-users"></i> Élèves de <span id="sm_class_name"></span></h3>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
            <input type="hidden" name="action" value="assign_students">
            <input type="hidden" name="class_id" id="sm_cid">
            <input type="hidden" name="class_name" id="sm_cname">
            <input type="text" id="sm_search" placeholder="Rechercher…" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:6px;margin-bottom:10px;box-sizing:border-box;font-size:13px" oninput="filterStudents()">
            <div class="student-list" id="sm_list">Chargement…</div>
            <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:12px"><button type="button" class="btn btn-secondary" onclick="document.getElementById('studentsModal').classList.remove('active')">Annuler</button><button type="submit" class="btn btn-primary">Enregistrer</button></div>
        </form>
    </div>
</div>

<script>
const allStudents = <?= json_encode($pdo->query("SELECT id, nom, prenom, classe FROM eleves WHERE actif = 1 ORDER BY nom, prenom")->fetchAll(PDO::FETCH_ASSOC)) ?>;

function openEdit(c) {
    document.getElementById('edit_cid').value = c.id;
    document.getElementById('edit_nom').value = c.nom;
    document.getElementById('edit_niveau').value = c.niveau;
    document.getElementById('edit_pp').value = c.professeur_principal_id || '';
    document.getElementById('edit_actif').checked = !!c.actif;
    document.getElementById('editModal').classList.add('active');
}

function openStudents(cid, className) {
    document.getElementById('sm_cid').value = cid;
    document.getElementById('sm_cname').value = className;
    document.getElementById('sm_class_name').textContent = className;
    let html = '';
    allStudents.forEach(s => {
        const checked = s.classe === className ? 'checked' : '';
        html += `<label><input type="checkbox" name="student_ids[]" value="${s.id}" ${checked}> ${s.prenom} ${s.nom} <small style="color:#888">(${s.classe || 'Sans classe'})</small></label>`;
    });
    document.getElementById('sm_list').innerHTML = html;
    document.getElementById('studentsModal').classList.add('active');
}

function filterStudents() {
    const q = document.getElementById('sm_search').value.toLowerCase();
    document.querySelectorAll('#sm_list label').forEach(l => {
        l.style.display = l.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
}

document.querySelectorAll('.modal-overlay').forEach(m => m.addEventListener('click', e => { if (e.target === m) m.classList.remove('active'); }));
</script>

<?php include __DIR__ . '/../includes/sub_footer.php'; ?>
