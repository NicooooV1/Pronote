<?php
/**
 * Journal d'audit — navigation, filtres, export
 */
require_once __DIR__ . '/../../API/core.php';
require_once __DIR__ . '/../includes/admin_functions.php';
require_once __DIR__ . '/../../login/src/auth.php';

requireAuth();
requireRole('administrateur');

$pdo = getPDO();

// Filtres
$filterAction = trim($_GET['action_type'] ?? '');
$filterModel = trim($_GET['model'] ?? '');
$filterUser = trim($_GET['user'] ?? '');
$filterDateFrom = $_GET['from'] ?? '';
$filterDateTo = $_GET['to'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 50;
$offset = ($page - 1) * $perPage;

$where = []; $params = [];
if (!empty($filterAction)) { $where[] = "a.action LIKE ?"; $params[] = "%$filterAction%"; }
if (!empty($filterModel)) { $where[] = "a.model = ?"; $params[] = $filterModel; }
if (!empty($filterUser)) { $where[] = "(a.user_id = ? OR a.user_type LIKE ?)"; $params[] = intval($filterUser); $params[] = "%$filterUser%"; }
if (!empty($filterDateFrom)) { $where[] = "a.created_at >= ?"; $params[] = $filterDateFrom . ' 00:00:00'; }
if (!empty($filterDateTo)) { $where[] = "a.created_at <= ?"; $params[] = $filterDateTo . ' 23:59:59'; }
$whereSQL = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

$total = $pdo->prepare("SELECT COUNT(*) FROM audit_log a $whereSQL"); $total->execute($params);
$totalLogs = $total->fetchColumn();
$totalPages = max(1, ceil($totalLogs / $perPage));

$sql = "SELECT a.* FROM audit_log a $whereSQL ORDER BY a.created_at DESC LIMIT $perPage OFFSET $offset";
$stmt = $pdo->prepare($sql); $stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Listes pour filtres
$actionTypes = $pdo->query("SELECT DISTINCT action FROM audit_log ORDER BY action")->fetchAll(PDO::FETCH_COLUMN);
$models = $pdo->query("SELECT DISTINCT model FROM audit_log WHERE model IS NOT NULL ORDER BY model")->fetchAll(PDO::FETCH_COLUMN);

// Stats rapides
$todayCount = $pdo->query("SELECT COUNT(*) FROM audit_log WHERE DATE(created_at) = CURDATE()")->fetchColumn();
$weekCount = $pdo->query("SELECT COUNT(*) FROM audit_log WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();

$pageTitle = 'Journal d\'audit';
$currentPage = 'audit';

ob_start();
?>
<style>
    .audit-container { max-width: 1200px; margin: 0 auto; }
    .stats-bar { display: flex; gap: 12px; margin-bottom: 20px; }
    .stat-card { background: white; border-radius: 8px; padding: 12px 18px; box-shadow: 0 1px 4px rgba(0,0,0,0.06); flex: 1; text-align: center; }
    .stat-card .val { font-size: 22px; font-weight: 700; } .stat-card .lbl { font-size: 12px; color: #888; }
    .filters { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 15px; background: white; padding: 12px 15px; border-radius: 10px; box-shadow: 0 1px 4px rgba(0,0,0,0.05); align-items: flex-end; }
    .filters .fg { display: flex; flex-direction: column; gap: 3px; }
    .filters label { font-size: 11px; font-weight: 600; color: #4a5568; }
    .filters select, .filters input { padding: 6px 8px; border: 1px solid #d2d6dc; border-radius: 5px; font-size: 12px; }
    .log-table { width: 100%; border-collapse: collapse; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
    .log-table th, .log-table td { padding: 8px 12px; text-align: left; border-bottom: 1px solid #f0f0f0; font-size: 12px; }
    .log-table th { background: #f7fafc; font-weight: 600; color: #4a5568; }
    .badge-action { display: inline-block; padding: 2px 6px; border-radius: 6px; font-size: 10px; font-weight: 600; font-family: monospace; }
    .ba-create { background: #d1fae5; color: #065f46; } .ba-delete { background: #fee2e2; color: #991b1b; }
    .ba-update { background: #dbeafe; color: #1e40af; } .ba-default { background: #e2e8f0; color: #4a5568; }
    .json-cell { max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-family: monospace; font-size: 11px; color: #666; cursor: pointer; }
    .json-cell:hover { white-space: normal; word-break: break-all; }
    .pagination { display: flex; gap: 4px; justify-content: center; margin-top: 20px; }
    .pagination a, .pagination span { padding: 6px 12px; border-radius: 6px; font-size: 13px; text-decoration: none; }
    .pagination a { background: white; color: #333; border: 1px solid #ddd; } .pagination span.current { background: #0f4c81; color: white; }
</style>
<?php
$extraHeadHtml = ob_get_clean();
include __DIR__ . '/../includes/sub_header.php';
?>

<div class="audit-container">
    <div class="stats-bar">
        <div class="stat-card"><div class="val"><?= $totalLogs ?></div><div class="lbl">Total entrées</div></div>
        <div class="stat-card"><div class="val"><?= $todayCount ?></div><div class="lbl">Aujourd'hui</div></div>
        <div class="stat-card"><div class="val"><?= $weekCount ?></div><div class="lbl">7 derniers jours</div></div>
    </div>

    <form method="get" class="filters">
        <div class="fg"><label>Action</label>
            <select name="action_type"><option value="">Toutes</option>
                <?php foreach ($actionTypes as $at): ?><option value="<?= htmlspecialchars($at) ?>" <?= $filterAction === $at ? 'selected' : '' ?>><?= htmlspecialchars($at) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="fg"><label>Modèle</label>
            <select name="model"><option value="">Tous</option>
                <?php foreach ($models as $m): ?><option value="<?= htmlspecialchars($m) ?>" <?= $filterModel === $m ? 'selected' : '' ?>><?= htmlspecialchars($m) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="fg"><label>User ID</label><input type="text" name="user" value="<?= htmlspecialchars($filterUser) ?>" placeholder="ID ou type" style="width:80px"></div>
        <div class="fg"><label>Du</label><input type="date" name="from" value="<?= $filterDateFrom ?>"></div>
        <div class="fg"><label>Au</label><input type="date" name="to" value="<?= $filterDateTo ?>"></div>
        <button class="btn btn-primary" style="height:30px;font-size:12px"><i class="fas fa-filter"></i></button>
        <a href="audit.php" style="font-size:12px;text-decoration:none;padding:6px">Reset</a>
    </form>

    <?php if (empty($logs)): ?>
        <div style="text-align:center;padding:40px;color:#999"><p>Aucune entrée dans le journal.</p></div>
    <?php else: ?>
    <table class="log-table">
        <thead><tr><th>#</th><th>Date</th><th>Action</th><th>Modèle</th><th>ID</th><th>User</th><th>IP</th><th>Anciennes valeurs</th><th>Nouvelles valeurs</th></tr></thead>
        <tbody>
            <?php foreach ($logs as $l):
                $actionClass = 'ba-default';
                if (str_contains($l['action'], 'create') || str_contains($l['action'], 'add')) $actionClass = 'ba-create';
                elseif (str_contains($l['action'], 'delete') || str_contains($l['action'], 'remove')) $actionClass = 'ba-delete';
                elseif (str_contains($l['action'], 'edit') || str_contains($l['action'], 'update')) $actionClass = 'ba-update';
            ?>
            <tr>
                <td style="color:#888"><?= $l['id'] ?></td>
                <td style="font-size:11px;white-space:nowrap"><?= date('d/m/Y H:i:s', strtotime($l['created_at'])) ?></td>
                <td><span class="badge-action <?= $actionClass ?>"><?= htmlspecialchars($l['action']) ?></span></td>
                <td style="font-family:monospace;font-size:11px"><?= htmlspecialchars($l['model'] ?? '-') ?></td>
                <td><?= $l['model_id'] ?? '-' ?></td>
                <td style="font-size:11px"><?= $l['user_type'] ?? '' ?>#<?= $l['user_id'] ?? '' ?></td>
                <td class="json-cell"><?= htmlspecialchars($l['ip_address'] ?? '-') ?></td>
                <td class="json-cell" title="<?= htmlspecialchars($l['old_values'] ?? '') ?>"><?= htmlspecialchars(mb_substr($l['old_values'] ?? '-', 0, 60)) ?></td>
                <td class="json-cell" title="<?= htmlspecialchars($l['new_values'] ?? '') ?>"><?= htmlspecialchars(mb_substr($l['new_values'] ?? '-', 0, 60)) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <?php if ($totalPages > 1): ?>
    <div class="pagination">
        <?php $qs = http_build_query(array_filter(['action_type' => $filterAction, 'model' => $filterModel, 'user' => $filterUser, 'from' => $filterDateFrom, 'to' => $filterDateTo]));
        $start = max(1, $page - 5); $end = min($totalPages, $page + 5);
        if ($page > 1): ?><a href="?<?= $qs ?>&page=<?= $page-1 ?>">&laquo;</a><?php endif;
        for ($i = $start; $i <= $end; $i++):
            if ($i === $page): ?><span class="current"><?= $i ?></span>
            <?php else: ?><a href="?<?= $qs ?>&page=<?= $i ?>"><?= $i ?></a>
        <?php endif; endfor;
        if ($page < $totalPages): ?><a href="?<?= $qs ?>&page=<?= $page+1 ?>">&raquo;</a><?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/sub_footer.php'; ?>
