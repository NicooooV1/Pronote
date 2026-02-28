<?php
/**
 * Modération des messages — recherche FULLTEXT, suppression, édition, pin
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
    $mid = intval($_POST['message_id'] ?? 0);

    if ($action === 'delete_message' && $mid > 0) {
        $pdo->prepare("UPDATE messages SET is_deleted = 1, deleted_at = NOW(), deleted_by_id = ?, deleted_by_type = 'administrateur' WHERE id = ?")
            ->execute([$admin['id'], $mid]);
        logAudit('message_deleted', 'messages', $mid);
        $message = "Message supprimé (soft delete).";
    }

    if ($action === 'restore_message' && $mid > 0) {
        $pdo->prepare("UPDATE messages SET is_deleted = 0, deleted_at = NULL, deleted_by_id = NULL, deleted_by_type = NULL WHERE id = ?")
            ->execute([$mid]);
        logAudit('message_restored', 'messages', $mid);
        $message = "Message restauré.";
    }

    if ($action === 'toggle_pin' && $mid > 0) {
        $cur = $pdo->prepare("SELECT is_pinned FROM messages WHERE id = ?"); $cur->execute([$mid]); $pinned = $cur->fetchColumn();
        if ($pinned) {
            $pdo->prepare("UPDATE messages SET is_pinned = 0, pinned_at = NULL, pinned_by_id = NULL, pinned_by_type = NULL WHERE id = ?")->execute([$mid]);
        } else {
            $pdo->prepare("UPDATE messages SET is_pinned = 1, pinned_at = NOW(), pinned_by_id = ?, pinned_by_type = 'administrateur' WHERE id = ?")->execute([$admin['id'], $mid]);
        }
        logAudit('message_pin_toggled', 'messages', $mid);
        $message = $pinned ? "Message désépinglé." : "Message épinglé.";
    }

    if ($action === 'edit_message' && $mid > 0) {
        $newBody = trim($_POST['new_body'] ?? '');
        if (!empty($newBody)) {
            // Sauvegarder l'original
            $pdo->prepare("UPDATE messages SET original_body = COALESCE(original_body, body), body = ?, edited_at = NOW() WHERE id = ?")->execute([$newBody, $mid]);
            logAudit('message_edited_by_admin', 'messages', $mid);
            $message = "Message modifié.";
        }
    }

    if ($action === 'report_action' && $mid > 0) {
        $reportId = intval($_POST['report_id'] ?? 0);
        $resolution = $_POST['resolution'] ?? '';
        if ($reportId > 0 && !empty($resolution)) {
            try {
                $pdo->prepare("UPDATE message_reports SET status = 'resolved', resolved_by = ?, resolved_at = NOW(), admin_note = ? WHERE id = ?")
                    ->execute([$admin['id'], $resolution, $reportId]);
                logAudit('report_resolved', 'message_reports', $reportId);
                $message = "Signalement traité.";
            } catch (Exception $e) {}
        }
    }
}

// Filtres
$search = trim($_GET['q'] ?? '');
$filterType = $_GET['type'] ?? '';
$filterDeleted = $_GET['deleted'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 30;
$offset = ($page - 1) * $perPage;

$where = []; $params = [];
if (!empty($search)) {
    $where[] = "MATCH(m.body) AGAINST(? IN BOOLEAN MODE)";
    $params[] = $search;
}
if (!empty($filterType)) { $where[] = "m.status = ?"; $params[] = $filterType; }
if ($filterDeleted === '1') { $where[] = "m.is_deleted = 1"; }
elseif ($filterDeleted !== 'all') { $where[] = "m.is_deleted = 0"; }
$whereSQL = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

$total = $pdo->prepare("SELECT COUNT(*) FROM messages m $whereSQL");
$total->execute($params); $totalMessages = $total->fetchColumn();
$totalPages = max(1, ceil($totalMessages / $perPage));

$sql = "SELECT m.*, c.subject AS conv_subject,
        CASE
            WHEN m.sender_type = 'eleve' THEN (SELECT CONCAT(prenom,' ',nom) FROM eleves WHERE id = m.sender_id)
            WHEN m.sender_type = 'professeur' THEN (SELECT CONCAT(prenom,' ',nom) FROM professeurs WHERE id = m.sender_id)
            WHEN m.sender_type = 'parent' THEN (SELECT CONCAT(prenom,' ',nom) FROM parents WHERE id = m.sender_id)
            WHEN m.sender_type = 'vie_scolaire' THEN (SELECT CONCAT(prenom,' ',nom) FROM vie_scolaire WHERE id = m.sender_id)
            WHEN m.sender_type = 'administrateur' THEN (SELECT CONCAT(prenom,' ',nom) FROM administrateurs WHERE id = m.sender_id)
            ELSE 'Inconnu'
        END AS sender_name
        FROM messages m
        LEFT JOIN conversations c ON m.conversation_id = c.id
        $whereSQL
        ORDER BY m.created_at DESC
        LIMIT $perPage OFFSET $offset";
$stmt = $pdo->prepare($sql); $stmt->execute($params);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Signalements en attente
$reports = [];
try {
    $rStmt = $pdo->query("SELECT mr.*, m.body AS msg_body,
        CASE
            WHEN mr.reporter_type = 'eleve' THEN (SELECT CONCAT(prenom,' ',nom) FROM eleves WHERE id = mr.reporter_id)
            WHEN mr.reporter_type = 'professeur' THEN (SELECT CONCAT(prenom,' ',nom) FROM professeurs WHERE id = mr.reporter_id)
            ELSE 'Inconnu'
        END AS reporter_name
        FROM message_reports mr
        JOIN messages m ON mr.message_id = m.id
        WHERE mr.status = 'pending'
        ORDER BY mr.created_at DESC LIMIT 20");
    $reports = $rStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$pageTitle = 'Modération messagerie';
$currentPage = 'msg_moderation';

ob_start();
?>
<style>
    .mod-container { max-width: 1100px; margin: 0 auto; }
    .search-bar { display: flex; gap: 10px; margin-bottom: 15px; flex-wrap: wrap; align-items: flex-end; }
    .search-bar input, .search-bar select { padding: 8px 10px; border: 1px solid #d2d6dc; border-radius: 6px; font-size: 13px; }
    .search-bar input[type=text] { flex: 1; min-width: 200px; }
    .msg-card { background: white; border-radius: 8px; padding: 14px 18px; margin-bottom: 10px; box-shadow: 0 1px 4px rgba(0,0,0,0.05); display: flex; gap: 12px; align-items: flex-start; }
    .msg-card.deleted { opacity: 0.5; background: #fef2f2; }
    .msg-card.pinned { border-left: 3px solid #f59e0b; }
    .msg-body { flex: 1; }
    .msg-meta { font-size: 12px; color: #888; margin-bottom: 4px; }
    .msg-meta strong { color: #333; }
    .msg-text { font-size: 13px; color: #444; line-height: 1.5; max-height: 60px; overflow: hidden; }
    .msg-actions { display: flex; gap: 4px; flex-wrap: wrap; }
    .btn-xs { padding: 3px 7px; font-size: 11px; border: none; border-radius: 4px; cursor: pointer; color: white; }
    .btn-xs.primary { background: #0f4c81; } .btn-xs.danger { background: #dc2626; } .btn-xs.warning { background: #f59e0b; } .btn-xs.success { background: #059669; }
    .badge-status { display: inline-block; padding: 2px 6px; border-radius: 8px; font-size: 10px; font-weight: 600; margin-left: 4px; }
    .bs-normal { background: #e2e8f0; color: #4a5568; } .bs-important { background: #fef3cd; color: #92400e; }
    .bs-urgent { background: #fee2e2; color: #991b1b; } .bs-annonce { background: #dbeafe; color: #1e40af; }
    .report-box { background: #fff7ed; border: 1px solid #fed7aa; border-radius: 8px; padding: 12px; margin-bottom: 10px; font-size: 13px; }
    .pagination { display: flex; gap: 4px; justify-content: center; margin-top: 20px; }
    .pagination a, .pagination span { padding: 6px 12px; border-radius: 6px; font-size: 13px; text-decoration: none; }
    .pagination a { background: white; color: #333; border: 1px solid #ddd; } .pagination span.current { background: #0f4c81; color: white; }
    .section-title { font-size: 16px; font-weight: 600; margin: 20px 0 10px; color: #1a202c; }
</style>
<?php
$extraHeadHtml = ob_get_clean();
include __DIR__ . '/../includes/sub_header.php';
?>

<div class="mod-container">
    <?php if (!empty($message)): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>

    <?php if (!empty($reports)): ?>
    <div class="section-title"><i class="fas fa-flag" style="color:#dc2626"></i> Signalements en attente (<?= count($reports) ?>)</div>
    <?php foreach ($reports as $r): ?>
    <div class="report-box">
        <strong><?= htmlspecialchars($r['reporter_name']) ?></strong> a signalé un message : <em>"<?= htmlspecialchars(mb_substr($r['msg_body'], 0, 100)) ?>"</em>
        <br><small>Raison : <?= htmlspecialchars($r['reason'] ?? '-') ?> — <?= date('d/m H:i', strtotime($r['created_at'])) ?></small>
        <form method="post" style="margin-top:6px;display:flex;gap:6px;align-items:center">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
            <input type="hidden" name="action" value="report_action">
            <input type="hidden" name="report_id" value="<?= $r['id'] ?>">
            <input type="hidden" name="message_id" value="<?= $r['message_id'] ?>">
            <input type="text" name="resolution" placeholder="Note admin…" style="flex:1;padding:4px 8px;border:1px solid #ddd;border-radius:4px;font-size:12px">
            <button class="btn-xs success"><i class="fas fa-check"></i> Traiter</button>
        </form>
    </div>
    <?php endforeach; endif; ?>

    <div class="section-title"><i class="fas fa-search"></i> Recherche de messages</div>
    <form method="get" class="search-bar">
        <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Recherche FULLTEXT…">
        <select name="type"><option value="">Tout type</option><option value="normal" <?= $filterType==='normal'?'selected':'' ?>>Normal</option><option value="important" <?= $filterType==='important'?'selected':'' ?>>Important</option><option value="urgent" <?= $filterType==='urgent'?'selected':'' ?>>Urgent</option><option value="annonce" <?= $filterType==='annonce'?'selected':'' ?>>Annonce</option></select>
        <select name="deleted"><option value="">Actifs</option><option value="1" <?= $filterDeleted==='1'?'selected':'' ?>>Supprimés</option><option value="all" <?= $filterDeleted==='all'?'selected':'' ?>>Tous</option></select>
        <button class="btn btn-primary" style="height:35px"><i class="fas fa-search"></i></button>
    </form>

    <?php foreach ($messages as $m): ?>
    <div class="msg-card <?= $m['is_deleted'] ? 'deleted' : '' ?> <?= $m['is_pinned'] ? 'pinned' : '' ?>">
        <div class="msg-body">
            <div class="msg-meta">
                <strong><?= htmlspecialchars($m['sender_name']) ?></strong>
                <span class="badge-profil <?= getProfilBadgeClass($m['sender_type']) ?>" style="padding:1px 6px;border-radius:8px;font-size:10px;"><?= getProfilLabel($m['sender_type']) ?></span>
                <span class="badge-status bs-<?= $m['status'] ?>"><?= $m['status'] ?></span>
                <?php if ($m['is_pinned']): ?><span style="color:#f59e0b" title="Épinglé"><i class="fas fa-thumbtack"></i></span><?php endif; ?>
                <?php if ($m['is_deleted']): ?><span style="color:#dc2626"><i class="fas fa-trash"></i> Supprimé</span><?php endif; ?>
                <br><small>Conv: <?= htmlspecialchars($m['conv_subject'] ?? '-') ?> — <?= date('d/m/Y H:i', strtotime($m['created_at'])) ?>
                <?php if ($m['edited_at']): ?> (modifié <?= date('d/m H:i', strtotime($m['edited_at'])) ?>)<?php endif; ?></small>
            </div>
            <div class="msg-text"><?= nl2br(htmlspecialchars(mb_substr($m['body'], 0, 200))) ?></div>
        </div>
        <div class="msg-actions" style="flex-direction:column">
            <form method="post" style="display:inline"><input type="hidden" name="csrf_token" value="<?= $csrf_token ?>"><input type="hidden" name="message_id" value="<?= $m['id'] ?>"><input type="hidden" name="action" value="toggle_pin"><button class="btn-xs warning" title="Épingler/Désépingler"><i class="fas fa-thumbtack"></i></button></form>
            <?php if ($m['is_deleted']): ?>
            <form method="post" style="display:inline"><input type="hidden" name="csrf_token" value="<?= $csrf_token ?>"><input type="hidden" name="message_id" value="<?= $m['id'] ?>"><input type="hidden" name="action" value="restore_message"><button class="btn-xs success" title="Restaurer"><i class="fas fa-undo"></i></button></form>
            <?php else: ?>
            <form method="post" style="display:inline"><input type="hidden" name="csrf_token" value="<?= $csrf_token ?>"><input type="hidden" name="message_id" value="<?= $m['id'] ?>"><input type="hidden" name="action" value="delete_message"><button class="btn-xs danger" title="Supprimer"><i class="fas fa-trash"></i></button></form>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>

    <?php if ($totalPages > 1): ?>
    <div class="pagination">
        <?php $qs = http_build_query(array_filter(['q' => $search, 'type' => $filterType, 'deleted' => $filterDeleted]));
        for ($i = 1; $i <= min($totalPages, 20); $i++):
            if ($i === $page): ?><span class="current"><?= $i ?></span>
            <?php else: ?><a href="?<?= $qs ?>&page=<?= $i ?>"><?= $i ?></a>
        <?php endif; endfor; ?>
    </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/sub_footer.php'; ?>
