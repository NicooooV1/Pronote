<?php
/**
 * Gestion des justificatifs — approuver / rejeter avec commentaire admin
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
    $jid = intval($_POST['justificatif_id'] ?? 0);

    if ($action === 'approve' && $jid > 0) {
        $commentAdmin = trim($_POST['commentaire_admin'] ?? '');
        $stmt = $pdo->prepare("UPDATE justificatifs SET traite = 1, approuve = 1, commentaire_admin = ?, date_traitement = NOW(), traite_par = ? WHERE id = ?");
        $stmt->execute([$commentAdmin, $admin['id'], $jid]);
        // Justifier l'absence correspondante
        $j = $pdo->prepare("SELECT id_eleve, date_debut_absence, date_fin_absence FROM justificatifs WHERE id = ?"); $j->execute([$jid]); $jd = $j->fetch(PDO::FETCH_ASSOC);
        if ($jd) {
            // Justifier toutes les absences qui chevauchent la période du justificatif
            $pdo->prepare("UPDATE absences SET justifie = 1 WHERE id_eleve = ? AND DATE(date_debut) <= ? AND DATE(date_fin) >= ?")
                 ->execute([$jd['id_eleve'], $jd['date_fin_absence'], $jd['date_debut_absence']]);
        }
        logAudit('justificatif_approved', 'justificatifs', $jid);
        $message = "Justificatif approuvé et absences justifiées.";
    }

    if ($action === 'reject' && $jid > 0) {
        $commentAdmin = trim($_POST['commentaire_admin'] ?? '');
        $stmt = $pdo->prepare("UPDATE justificatifs SET traite = 1, approuve = 0, commentaire_admin = ?, date_traitement = NOW(), traite_par = ? WHERE id = ?");
        $stmt->execute([$commentAdmin, $admin['id'], $jid]);
        logAudit('justificatif_rejected', 'justificatifs', $jid);
        $message = "Justificatif rejeté.";
    }
}

// Filtres
$filterStatus = $_GET['status'] ?? 'pending';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 30;
$offset = ($page - 1) * $perPage;

$where = [];
$params = [];
if ($filterStatus === 'pending') { $where[] = "j.traite = 0"; }
elseif ($filterStatus === 'approved') { $where[] = "j.traite = 1 AND j.approuve = 1"; }
elseif ($filterStatus === 'rejected') { $where[] = "j.traite = 1 AND j.approuve = 0"; }
$whereSQL = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

$totalStmt = $pdo->prepare("SELECT COUNT(*) FROM justificatifs j $whereSQL");
$totalStmt->execute($params);
$total = $totalStmt->fetchColumn();
$totalPages = max(1, ceil($total / $perPage));

$sql = "SELECT j.*, e.nom AS eleve_nom, e.prenom AS eleve_prenom, e.classe
        FROM justificatifs j
        JOIN eleves e ON j.id_eleve = e.id
        $whereSQL
        ORDER BY j.date_soumission DESC
        LIMIT $perPage OFFSET $offset";
$stmt = $pdo->prepare($sql); $stmt->execute($params);
$justificatifs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Compteurs pour badges
$pendingCount = $pdo->query("SELECT COUNT(*) FROM justificatifs WHERE traite = 0")->fetchColumn();
$approvedCount = $pdo->query("SELECT COUNT(*) FROM justificatifs WHERE traite = 1 AND approuve = 1")->fetchColumn();
$rejectedCount = $pdo->query("SELECT COUNT(*) FROM justificatifs WHERE traite = 1 AND approuve = 0")->fetchColumn();

$pageTitle = 'Justificatifs';
$currentPage = 'justificatifs';
$extraCss = ['../../assets/css/admin.css'];

ob_start();
?>
<style>
    .just-container { max-width: 1100px; margin: 0 auto; }
    .tabs { display: flex; gap: 5px; margin-bottom: 20px; border-bottom: 2px solid #eee; }
    .tab-link { padding: 10px 18px; text-decoration: none; font-size: 14px; font-weight: 500; color: #666; border-bottom: 2px solid transparent; margin-bottom: -2px; display: flex; align-items: center; gap: 6px; }
    .tab-link.active { color: #0f4c81; border-bottom-color: #0f4c81; }
    .tab-badge { padding: 1px 7px; border-radius: 50%; font-size: 11px; font-weight: 600; color: white; }
    .tb-pending { background: #f59e0b; } .tb-approved { background: #059669; } .tb-rejected { background: #dc2626; }
    .just-card { background: white; border-radius: 10px; box-shadow: 0 2px 6px rgba(0,0,0,0.06); padding: 18px; margin-bottom: 15px; display: flex; gap: 15px; align-items: flex-start; }
    .just-card .left { flex: 1; }
    .just-card .right { display: flex; flex-direction: column; gap: 8px; min-width: 200px; }
    .just-card h4 { margin: 0 0 4px; font-size: 15px; }
    .just-meta { font-size: 13px; color: #666; margin-bottom: 6px; }
    .just-meta span { margin-right: 12px; }
    .badge-type { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 600; background: #e2e8f0; color: #4a5568; }
    .badge-status { display: inline-block; padding: 3px 10px; border-radius: 10px; font-size: 12px; font-weight: 600; }
    .status-pending { background: #fef3cd; color: #92400e; } .status-approved { background: #d1fae5; color: #065f46; } .status-rejected { background: #fee2e2; color: #991b1b; }
    .admin-comment { font-size: 12px; padding: 6px 10px; background: #f8f9fa; border-radius: 6px; margin-top: 6px; color: #555; }
    .action-form textarea { width: 100%; padding: 6px 8px; border: 1px solid #d2d6dc; border-radius: 6px; font-size: 12px; resize: vertical; box-sizing: border-box; }
    .file-link { color: #0f4c81; font-size: 13px; text-decoration: none; }
    .file-link:hover { text-decoration: underline; }
    .empty-state { text-align: center; padding: 40px; color: #999; }
</style>
<?php
$extraHeadHtml = ob_get_clean();
include __DIR__ . '/../includes/sub_header.php';
?>

<div class="just-container">
    <?php if (!empty($message)): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>

    <div class="tabs">
        <a href="?status=pending" class="tab-link <?= $filterStatus === 'pending' ? 'active' : '' ?>"><i class="fas fa-hourglass-half"></i> En attente <span class="tab-badge tb-pending"><?= $pendingCount ?></span></a>
        <a href="?status=approved" class="tab-link <?= $filterStatus === 'approved' ? 'active' : '' ?>"><i class="fas fa-check"></i> Approuvés <span class="tab-badge tb-approved"><?= $approvedCount ?></span></a>
        <a href="?status=rejected" class="tab-link <?= $filterStatus === 'rejected' ? 'active' : '' ?>"><i class="fas fa-times"></i> Rejetés <span class="tab-badge tb-rejected"><?= $rejectedCount ?></span></a>
    </div>

    <?php if (empty($justificatifs)): ?>
        <div class="empty-state"><i class="fas fa-file-alt" style="font-size:36px;opacity:0.3;display:block;margin-bottom:10px"></i><p>Aucun justificatif dans cette catégorie.</p></div>
    <?php else: ?>
        <?php foreach ($justificatifs as $j): ?>
        <div class="just-card">
            <div class="left">
                <h4><?= htmlspecialchars($j['eleve_prenom'] . ' ' . $j['eleve_nom']) ?> <small style="color:#888">(<?= htmlspecialchars($j['classe']) ?>)</small></h4>
                <div class="just-meta">
                    <span><i class="fas fa-calendar"></i> <?= date('d/m/Y', strtotime($j['date_debut_absence'])) ?> → <?= date('d/m/Y', strtotime($j['date_fin_absence'])) ?></span>
                    <span class="badge-type"><?= htmlspecialchars($j['type']) ?></span>
                    <span><i class="fas fa-clock"></i> Soumis le <?= date('d/m/Y', strtotime($j['date_soumission'])) ?></span>
                </div>
                <?php if (!empty($j['motif'])): ?><div style="font-size:13px;color:#444;margin-top:4px"><strong>Motif :</strong> <?= htmlspecialchars($j['motif']) ?></div><?php endif; ?>
                <?php if (!empty($j['fichier'])): ?><div style="margin-top:6px"><a href="../../<?= htmlspecialchars($j['fichier']) ?>" class="file-link" target="_blank"><i class="fas fa-paperclip"></i> Voir le fichier joint</a></div><?php endif; ?>
                <?php if (!empty($j['commentaire_admin'])): ?><div class="admin-comment"><i class="fas fa-comment-dots"></i> <?= htmlspecialchars($j['commentaire_admin']) ?></div><?php endif; ?>
            </div>
            <div class="right">
                <?php if (!$j['traite']): ?>
                <form method="post" class="action-form">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    <input type="hidden" name="justificatif_id" value="<?= $j['id'] ?>">
                    <textarea name="commentaire_admin" placeholder="Commentaire (optionnel)…" rows="2"></textarea>
                    <div style="display:flex;gap:6px;margin-top:6px">
                        <button type="submit" name="action" value="approve" class="btn-xs success"><i class="fas fa-check"></i> Approuver</button>
                        <button type="submit" name="action" value="reject" class="btn-xs danger"><i class="fas fa-times"></i> Rejeter</button>
                    </div>
                </form>
                <?php else: ?>
                <span class="badge-status <?= $j['approuve'] ? 'status-approved' : 'status-rejected' ?>"><?= $j['approuve'] ? 'Approuvé' : 'Rejeté' ?></span>
                <?php if (!empty($j['date_traitement'])): ?><div style="font-size:12px;color:#888;margin-top:4px">Le <?= date('d/m/Y H:i', strtotime($j['date_traitement'])) ?></div><?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/sub_footer.php'; ?>
