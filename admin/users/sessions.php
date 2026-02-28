<?php
/**
 * Gestion des sessions actives — vue admin des sessions utilisateurs
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

// POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['csrf_token'] ?? '') === $csrf_token) {
    $action = $_POST['action'] ?? '';

    if ($action === 'kill_session') {
        $sid = $_POST['session_id'] ?? '';
        if (!empty($sid)) {
            $stmt = $pdo->prepare("UPDATE session_security SET is_active = 0, expires_at = NOW() WHERE session_id = ?");
            $stmt->execute([$sid]);
            logAudit('session_killed', 'session_security', 0, ['session_id' => $sid]);
            $message = "Session déconnectée.";
        }
    }

    if ($action === 'kill_user_sessions') {
        $uid = intval($_POST['user_id'] ?? 0);
        $utype = $_POST['user_type'] ?? '';
        if ($uid > 0 && !empty($utype)) {
            $stmt = $pdo->prepare("UPDATE session_security SET is_active = 0, expires_at = NOW() WHERE user_id = ? AND user_type = ?");
            $stmt->execute([$uid, $utype]);
            logAudit('all_sessions_killed', 'session_security', $uid, ['user_type' => $utype]);
            $message = "Toutes les sessions de l'utilisateur ont été fermées.";
        }
    }

    if ($action === 'kill_all') {
        $stmt = $pdo->prepare("UPDATE session_security SET is_active = 0, expires_at = NOW() WHERE NOT (user_id = ? AND user_type = 'administrateur')");
        $stmt->execute([$admin['id']]);
        logAudit('all_sessions_killed_global', 'session_security', 0);
        $message = "Toutes les sessions (sauf la vôtre) ont été fermées.";
    }
}

// Récupérer sessions actives
$sessions = [];
try {
    $stmt = $pdo->query("
        SELECT s.*,
            CASE
                WHEN s.user_type = 'eleve' THEN (SELECT CONCAT(prenom,' ',nom) FROM eleves WHERE id = s.user_id)
                WHEN s.user_type = 'professeur' THEN (SELECT CONCAT(prenom,' ',nom) FROM professeurs WHERE id = s.user_id)
                WHEN s.user_type = 'parent' THEN (SELECT CONCAT(prenom,' ',nom) FROM parents WHERE id = s.user_id)
                WHEN s.user_type = 'vie_scolaire' THEN (SELECT CONCAT(prenom,' ',nom) FROM vie_scolaire WHERE id = s.user_id)
                WHEN s.user_type = 'administrateur' THEN (SELECT CONCAT(prenom,' ',nom) FROM administrateurs WHERE id = s.user_id)
                ELSE 'Inconnu'
            END AS nom_complet
        FROM session_security s
        WHERE s.is_active = 1 AND s.expires_at > NOW()
        ORDER BY s.last_activity DESC
    ");
    $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Détection multi-IP : utilisateurs avec session active depuis 2+ IPs distinctes
$suspicious = [];
try {
    $stmt = $pdo->query("
        SELECT user_id, user_type, COUNT(DISTINCT ip_address) AS ip_count, GROUP_CONCAT(DISTINCT ip_address) AS ips
        FROM session_security
        WHERE is_active = 1 AND expires_at > NOW()
        GROUP BY user_id, user_type
        HAVING ip_count > 1
    ");
    $suspicious = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Stats
$totalActive = count($sessions);
$uniqueUsers = count(array_unique(array_map(fn($s) => $s['user_type'].'_'.$s['user_id'], $sessions)));

$pageTitle = 'Sessions actives';
$currentPage = 'sessions';

ob_start();
?>
<style>
    .sessions-container { max-width: 1100px; margin: 0 auto; }
    .stats-bar { display: flex; gap: 15px; margin-bottom: 20px; }
    .stat-pill { background: white; border-radius: 8px; padding: 12px 18px; box-shadow: 0 1px 4px rgba(0,0,0,0.06); display: flex; align-items: center; gap: 10px; }
    .stat-pill i { font-size: 20px; color: #0f4c81; }
    .stat-pill .val { font-size: 20px; font-weight: 700; color: #1a202c; }
    .stat-pill .lbl { font-size: 12px; color: #888; }
    .alert-box { background: #fef3cd; border-left: 4px solid #f59e0b; padding: 12px 16px; border-radius: 6px; margin-bottom: 20px; font-size: 14px; }
    .alert-box strong { color: #92400e; }
    .sess-table { width: 100%; border-collapse: collapse; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
    .sess-table th, .sess-table td { padding: 10px 14px; text-align: left; border-bottom: 1px solid #f0f0f0; font-size: 13px; }
    .sess-table th { background: #f7fafc; font-weight: 600; color: #4a5568; }
    .badge-profil { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 600; }
    .badge-eleve { background: #dbeafe; color: #1e40af; }
    .badge-parent { background: #d1fae5; color: #065f46; }
    .badge-prof { background: #ffedd5; color: #c2410c; }
    .badge-vs { background: #ede9fe; color: #5b21b6; }
    .badge-admin { background: #fee2e2; color: #991b1b; }
    .btn-xs { padding: 4px 8px; font-size: 12px; border: none; border-radius: 4px; cursor: pointer; color: white; }
    .btn-xs.danger { background: #dc2626; }
    .btn-xs.warning { background: #f59e0b; }
    .top-actions { display: flex; justify-content: flex-end; margin-bottom: 15px; }
    .ip-mono { font-family: monospace; font-size: 12px; color: #555; }
</style>
<?php
$extraHeadHtml = ob_get_clean();
include __DIR__ . '/../includes/sub_header.php';
?>

<div class="sessions-container">
    <?php if (!empty($message)): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if (!empty($error)): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <div class="stats-bar">
        <div class="stat-pill"><i class="fas fa-desktop"></i><div><div class="val"><?= $totalActive ?></div><div class="lbl">Sessions actives</div></div></div>
        <div class="stat-pill"><i class="fas fa-users"></i><div><div class="val"><?= $uniqueUsers ?></div><div class="lbl">Utilisateurs connectés</div></div></div>
        <?php if (count($suspicious) > 0): ?>
        <div class="stat-pill"><i class="fas fa-exclamation-triangle" style="color:#f59e0b"></i><div><div class="val"><?= count($suspicious) ?></div><div class="lbl">Connexions suspectes</div></div></div>
        <?php endif; ?>
    </div>

    <?php if (!empty($suspicious)): ?>
    <div class="alert-box">
        <strong><i class="fas fa-exclamation-triangle"></i> Connexions multi-IP détectées :</strong>
        <?php foreach ($suspicious as $s): ?>
            <div style="margin-top: 5px;">
                <?= htmlspecialchars(resolveUserName($pdo, $s['user_id'], $s['user_type'])) ?>
                (<?= getProfilLabel($s['user_type']) ?>) — <?= $s['ip_count'] ?> IPs : <span class="ip-mono"><?= htmlspecialchars($s['ips']) ?></span>
                <form method="post" style="display:inline;margin-left:8px">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    <input type="hidden" name="action" value="kill_user_sessions">
                    <input type="hidden" name="user_id" value="<?= $s['user_id'] ?>">
                    <input type="hidden" name="user_type" value="<?= $s['user_type'] ?>">
                    <button class="btn-xs warning"><i class="fas fa-ban"></i> Tout déconnecter</button>
                </form>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="top-actions">
        <form method="post" onsubmit="return confirm('Fermer TOUTES les sessions (sauf la vôtre) ?')">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
            <input type="hidden" name="action" value="kill_all">
            <button class="btn btn-danger"><i class="fas fa-power-off"></i> Fermer toutes les sessions</button>
        </form>
    </div>

    <?php if (empty($sessions)): ?>
        <div style="text-align:center;padding:40px;color:#999"><i class="fas fa-check-circle" style="font-size:36px;opacity:0.3"></i><p>Aucune session active.</p></div>
    <?php else: ?>
    <table class="sess-table">
        <thead><tr><th>Utilisateur</th><th>Profil</th><th>IP</th><th>Navigateur</th><th>Dernière activité</th><th>Expire</th><th>Action</th></tr></thead>
        <tbody>
            <?php foreach ($sessions as $s): ?>
            <tr>
                <td><strong><?= htmlspecialchars($s['nom_complet']) ?></strong></td>
                <td><span class="badge-profil <?= getProfilBadgeClass($s['user_type']) ?>"><?= getProfilLabel($s['user_type']) ?></span></td>
                <td class="ip-mono"><?= htmlspecialchars($s['ip_address'] ?? '-') ?></td>
                <td style="font-size:12px;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= htmlspecialchars($s['user_agent'] ?? '') ?>"><?= htmlspecialchars(substr($s['user_agent'] ?? '-', 0, 50)) ?></td>
                <td style="font-size:12px"><?= !empty($s['last_activity']) ? date('d/m H:i', strtotime($s['last_activity'])) : '-' ?></td>
                <td style="font-size:12px"><?= date('d/m H:i', strtotime($s['expires_at'])) ?></td>
                <td>
                    <form method="post" style="display:inline"><input type="hidden" name="csrf_token" value="<?= $csrf_token ?>"><input type="hidden" name="action" value="kill_session"><input type="hidden" name="session_id" value="<?= htmlspecialchars($s['session_id']) ?>"><button class="btn-xs danger" title="Déconnecter"><i class="fas fa-times"></i></button></form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/sub_footer.php'; ?>
