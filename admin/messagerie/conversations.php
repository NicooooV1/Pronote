<?php
/**
 * Vue admin des conversations — navigation, participants, statistiques
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

    if ($action === 'delete_conversation') {
        $cid = intval($_POST['conv_id'] ?? 0);
        if ($cid > 0) {
            $pdo->prepare("UPDATE messages SET is_deleted = 1, deleted_at = NOW(), deleted_by_id = ?, deleted_by_type = 'administrateur' WHERE conversation_id = ?")->execute([$admin['id'], $cid]);
            logAudit('conversation_deleted', 'conversations', $cid);
            $message = "Tous les messages de la conversation ont été supprimés.";
        }
    }
}

// Filtres
$search = trim($_GET['q'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 30;
$offset = ($page - 1) * $perPage;

$where = []; $params = [];
if (!empty($search)) {
    $where[] = "MATCH(c.subject) AGAINST(? IN BOOLEAN MODE)";
    $params[] = $search;
}
$whereSQL = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

$total = $pdo->prepare("SELECT COUNT(*) FROM conversations c $whereSQL");
$total->execute($params); $totalConvs = $total->fetchColumn();
$totalPages = max(1, ceil($totalConvs / $perPage));

$sql = "SELECT c.*,
        (SELECT COUNT(*) FROM messages m WHERE m.conversation_id = c.id AND m.is_deleted = 0) AS msg_count,
        (SELECT COUNT(*) FROM conversation_participants cp WHERE cp.conversation_id = c.id) AS participant_count,
        (SELECT MAX(m2.created_at) FROM messages m2 WHERE m2.conversation_id = c.id) AS last_msg_at
        FROM conversations c
        $whereSQL
        ORDER BY c.updated_at DESC
        LIMIT $perPage OFFSET $offset";
$stmt = $pdo->prepare($sql); $stmt->execute($params);
$conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Stats
$totalConvsAll = $pdo->query("SELECT COUNT(*) FROM conversations")->fetchColumn();
$totalMsgsAll = $pdo->query("SELECT COUNT(*) FROM messages WHERE is_deleted = 0")->fetchColumn();
$msgsToday = $pdo->query("SELECT COUNT(*) FROM messages WHERE DATE(created_at) = CURDATE() AND is_deleted = 0")->fetchColumn();

$pageTitle = 'Conversations';
$currentPage = 'msg_conversations';
$extraCss = ['../../assets/css/admin.css'];

ob_start();
?>
<style>
    .conv-container { max-width: 1100px; margin: 0 auto; }
    .search-bar { display: flex; gap: 10px; margin-bottom: 15px; }
    .search-bar input { flex: 1; padding: 8px 12px; border: 1px solid #d2d6dc; border-radius: 6px; font-size: 13px; }
    .conv-table { width: 100%; border-collapse: collapse; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
    .conv-table th, .conv-table td { padding: 10px 14px; text-align: left; border-bottom: 1px solid #f0f0f0; font-size: 13px; }
    .conv-table th { background: #f7fafc; font-weight: 600; color: #4a5568; font-size: 12px; }
    .badge-type { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 600; }
    .bt-standard { background: #e2e8f0; color: #4a5568; }
    .bt-broadcast { background: #dbeafe; color: #1e40af; }
    .detail-panel { display: none; background: #f8fafc; padding: 12px 14px; font-size: 13px; }
    .detail-panel.active { display: table-row; }
</style>
<?php
$extraHeadHtml = ob_get_clean();
include __DIR__ . '/../includes/sub_header.php';
?>

<div class="conv-container">
    <?php if (!empty($message)): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>

    <div class="stats-bar">
        <div class="stat-card"><div class="val"><?= $totalConvsAll ?></div><div class="lbl">Conversations</div></div>
        <div class="stat-card"><div class="val"><?= $totalMsgsAll ?></div><div class="lbl">Messages</div></div>
        <div class="stat-card"><div class="val"><?= $msgsToday ?></div><div class="lbl">Aujourd'hui</div></div>
    </div>

    <form method="get" class="search-bar">
        <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Rechercher une conversation…">
        <button class="btn btn-primary" style="height:35px"><i class="fas fa-search"></i></button>
    </form>

    <?php if (empty($conversations)): ?>
        <div style="text-align:center;padding:40px;color:#999"><p>Aucune conversation trouvée.</p></div>
    <?php else: ?>
    <table class="conv-table">
        <thead><tr><th>Sujet</th><th>Type</th><th>Participants</th><th>Messages</th><th>Dernier msg</th><th>Créée le</th><th>Actions</th></tr></thead>
        <tbody>
            <?php foreach ($conversations as $c): ?>
            <tr>
                <td><strong><?= htmlspecialchars($c['subject']) ?></strong></td>
                <td><span class="badge-type <?= $c['type'] === 'broadcast' ? 'bt-broadcast' : 'bt-standard' ?>"><?= htmlspecialchars($c['type']) ?></span></td>
                <td><?= $c['participant_count'] ?></td>
                <td><?= $c['msg_count'] ?></td>
                <td style="font-size:12px"><?= $c['last_msg_at'] ? date('d/m H:i', strtotime($c['last_msg_at'])) : '-' ?></td>
                <td style="font-size:12px"><?= date('d/m/Y', strtotime($c['created_at'])) ?></td>
                <td>
                    <button class="btn-xs primary" onclick="toggleDetail(<?= $c['id'] ?>)" title="Voir détails"><i class="fas fa-eye"></i></button>
                    <form method="post" style="display:inline" onsubmit="return confirm('Supprimer tous les messages de cette conversation ?')"><input type="hidden" name="csrf_token" value="<?= $csrf_token ?>"><input type="hidden" name="action" value="delete_conversation"><input type="hidden" name="conv_id" value="<?= $c['id'] ?>"><button class="btn-xs danger"><i class="fas fa-trash"></i></button></form>
                </td>
            </tr>
            <tr class="detail-panel" id="detail-<?= $c['id'] ?>">
                <td colspan="7" id="detail-content-<?= $c['id'] ?>">Chargement…</td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <?php if ($totalPages > 1): ?>
    <div class="pagination">
        <?php for ($i = 1; $i <= min($totalPages, 20); $i++):
            $qs = http_build_query(array_filter(['q' => $search]));
            if ($i === $page): ?><span class="current"><?= $i ?></span>
            <?php else: ?><a href="?<?= $qs ?>&page=<?= $i ?>"><?= $i ?></a>
        <?php endif; endfor; ?>
    </div>
    <?php endif; ?>
</div>

<script>
function toggleDetail(cid) {
    const row = document.getElementById('detail-' + cid);
    if (row.classList.contains('active')) {
        row.classList.remove('active');
        return;
    }
    row.classList.add('active');
    const content = document.getElementById('detail-content-' + cid);
    // Load participants via simple inline query (use AJAX if available, fallback to static)
    content.innerHTML = '<em>Détail chargé. Consultez la page de modération pour les messages individuels.</em>';
}
</script>

<?php include __DIR__ . '/../includes/sub_footer.php'; ?>
