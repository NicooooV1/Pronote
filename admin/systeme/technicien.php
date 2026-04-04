<?php
/**
 * Administration — Gestion des acces technicien temporaires
 * Creation, suivi, revocation et audit des comptes technicien.
 */
require_once __DIR__ . '/../../API/core.php';
require_once __DIR__ . '/../includes/admin_functions.php';

requireAuth();
requireRole('administrateur');

$pdo = getPDO();
$currentUser = getCurrentUser();
$message = '';
$messageType = '';
$showPasswordModal = false;
$generatedPassword = '';
$generatedLogin = '';

// ─── CSRF ──────────────────────────────────────────────────────────────────
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

// ─── Traitement POST ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['csrf_token'] ?? '') === $csrfToken) {
    $action = $_POST['action'] ?? '';

    // --- Creer un acces technicien ---
    if ($action === 'create') {
        $nom = trim($_POST['nom'] ?? '');
        $prenom = trim($_POST['prenom'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $motif = trim($_POST['motif'] ?? '');
        $permLevel = $_POST['permission_level'] ?? 'lecture';
        $dureeHeures = max(1, min(168, intval($_POST['duree_heures'] ?? 24)));
        $ipWhitelist = trim($_POST['ip_whitelist'] ?? '');
        $modulesAutorises = $_POST['modules_autorises'] ?? [];

        if (empty($nom) || empty($prenom) || empty($motif)) {
            $message = 'Les champs Nom, Prenom et Motif sont obligatoires.';
            $messageType = 'error';
        } else {
            // Generer identifiant unique tech_XXXX
            $identifiant = 'tech_' . strtoupper(bin2hex(random_bytes(2)));
            // Verifier unicite
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM technicien_access WHERE identifiant = ?");
            $checkStmt->execute([$identifiant]);
            while ($checkStmt->fetchColumn() > 0) {
                $identifiant = 'tech_' . strtoupper(bin2hex(random_bytes(2)));
                $checkStmt->execute([$identifiant]);
            }

            // Generer mot de passe
            $plainPassword = bin2hex(random_bytes(8));
            $hashedPassword = password_hash($plainPassword, PASSWORD_DEFAULT);

            // Permissions selon le niveau
            $permissionsMap = [
                'lecture'  => ['admin.access', 'admin.systeme', 'read'],
                'standard' => ['admin.access', 'admin.systeme', 'read', 'write'],
                'complet'  => ['admin.access', 'admin.systeme', 'read', 'write', 'delete', 'config'],
            ];
            $permissions = json_encode($permissionsMap[$permLevel] ?? $permissionsMap['lecture']);

            // IP whitelist
            $ipWhitelistJson = null;
            if (!empty($ipWhitelist)) {
                $ips = array_map('trim', explode(',', $ipWhitelist));
                $ips = array_filter($ips, function ($ip) {
                    return filter_var($ip, FILTER_VALIDATE_IP) || preg_match('#^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}/\d{1,2}$#', $ip);
                });
                if (!empty($ips)) {
                    $ipWhitelistJson = json_encode(array_values($ips));
                }
            }

            // Modules autorises
            $modulesJson = null;
            if (!empty($modulesAutorises)) {
                $modulesJson = json_encode($modulesAutorises);
            }

            $dateExpiration = date('Y-m-d H:i:s', strtotime("+{$dureeHeures} hours"));

            try {
                $stmt = $pdo->prepare("
                    INSERT INTO technicien_access
                    (nom, prenom, email, identifiant, mot_de_passe, motif, permissions, modules_autorises, ip_whitelist, created_by, date_expiration)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $nom, $prenom, $email ?: null, $identifiant, $hashedPassword,
                    $motif, $permissions, $modulesJson, $ipWhitelistJson,
                    $currentUser['id'], $dateExpiration
                ]);

                $newId = $pdo->lastInsertId();

                // Audit log
                $auditStmt = $pdo->prepare("
                    INSERT INTO technicien_audit_log (technicien_id, action, details, ip_address, user_agent)
                    VALUES (?, 'access.created', ?, ?, ?)
                ");
                $auditStmt->execute([
                    $newId,
                    json_encode(['created_by' => $currentUser['id'], 'permission_level' => $permLevel, 'duree_heures' => $dureeHeures]),
                    $_SERVER['REMOTE_ADDR'] ?? null,
                    $_SERVER['HTTP_USER_AGENT'] ?? null
                ]);

                logAudit('technicien.created', 'technicien_access', $newId, null, [
                    'identifiant' => $identifiant, 'permission_level' => $permLevel, 'duree_heures' => $dureeHeures
                ]);

                $showPasswordModal = true;
                $generatedPassword = $plainPassword;
                $generatedLogin = $identifiant;
                $message = 'Acces technicien cree avec succes.';
                $messageType = 'success';
            } catch (Exception $e) {
                error_log("Technicien creation error: " . $e->getMessage());
                $message = 'Erreur lors de la creation de l\'acces technicien.';
                $messageType = 'error';
            }
        }
    }

    // --- Revoquer un acces ---
    if ($action === 'revoke') {
        $techId = intval($_POST['tech_id'] ?? 0);
        if ($techId > 0) {
            try {
                $stmt = $pdo->prepare("
                    UPDATE technicien_access
                    SET actif = 0, revoked_at = NOW(), revoked_by = ?
                    WHERE id = ? AND actif = 1
                ");
                $stmt->execute([$currentUser['id'], $techId]);

                if ($stmt->rowCount() > 0) {
                    $auditStmt = $pdo->prepare("
                        INSERT INTO technicien_audit_log (technicien_id, action, details, ip_address, user_agent)
                        VALUES (?, 'access.revoked', ?, ?, ?)
                    ");
                    $auditStmt->execute([
                        $techId,
                        json_encode(['revoked_by' => $currentUser['id']]),
                        $_SERVER['REMOTE_ADDR'] ?? null,
                        $_SERVER['HTTP_USER_AGENT'] ?? null
                    ]);

                    logAudit('technicien.revoked', 'technicien_access', $techId, null, ['revoked_by' => $currentUser['id']]);

                    $message = 'Acces technicien revoque immediatement.';
                    $messageType = 'success';
                } else {
                    $message = 'Acces introuvable ou deja revoque.';
                    $messageType = 'error';
                }
            } catch (Exception $e) {
                error_log("Technicien revoke error: " . $e->getMessage());
                $message = 'Erreur lors de la revocation.';
                $messageType = 'error';
            }
        }
    }
}

// ─── Donnees ───────────────────────────────────────────────────────────────

// Tous les acces technicien
$allAccess = $pdo->query("
    SELECT ta.*,
           CONCAT(a.prenom, ' ', a.nom) as created_by_name,
           CONCAT(a2.prenom, ' ', a2.nom) as revoked_by_name
    FROM technicien_access ta
    LEFT JOIN administrateurs a ON ta.created_by = a.id
    LEFT JOIN administrateurs a2 ON ta.revoked_by = a2.id
    ORDER BY ta.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Separer actifs / historique
$activeAccess = [];
$historyAccess = [];
foreach ($allAccess as $acc) {
    $isExpired = strtotime($acc['date_expiration']) < time();
    $isRevoked = !empty($acc['revoked_at']);
    $isActive = $acc['actif'] && !$isExpired && !$isRevoked;

    $acc['_status'] = $isRevoked ? 'revoked' : ($isExpired ? 'expired' : ($isActive ? 'active' : 'expired'));
    $acc['_remaining'] = max(0, strtotime($acc['date_expiration']) - time());

    if ($isActive) {
        $activeAccess[] = $acc;
    } else {
        $historyAccess[] = $acc;
    }
}

// Stats
$totalActive = count($activeAccess);
$totalExpired = count(array_filter($historyAccess, fn($a) => $a['_status'] === 'expired'));
$totalRevoked = count(array_filter($historyAccess, fn($a) => $a['_status'] === 'revoked'));

// Audit log
$auditPage = max(1, intval($_GET['audit_page'] ?? 1));
$auditPerPage = 30;
$auditOffset = ($auditPage - 1) * $auditPerPage;

$filterTechId = intval($_GET['filter_tech'] ?? 0);
$filterAuditAction = trim($_GET['filter_action'] ?? '');

$auditWhere = [];
$auditParams = [];
if ($filterTechId > 0) {
    $auditWhere[] = "tal.technicien_id = ?";
    $auditParams[] = $filterTechId;
}
if (!empty($filterAuditAction)) {
    $auditWhere[] = "tal.action LIKE ?";
    $auditParams[] = "%{$filterAuditAction}%";
}
$auditWhereSQL = !empty($auditWhere) ? 'WHERE ' . implode(' AND ', $auditWhere) : '';

$auditTotalStmt = $pdo->prepare("SELECT COUNT(*) FROM technicien_audit_log tal $auditWhereSQL");
$auditTotalStmt->execute($auditParams);
$auditTotal = $auditTotalStmt->fetchColumn();
$auditTotalPages = max(1, ceil($auditTotal / $auditPerPage));

$auditStmt = $pdo->prepare("
    SELECT tal.*, CONCAT(ta.prenom, ' ', ta.nom) as tech_name, ta.identifiant as tech_login
    FROM technicien_audit_log tal
    LEFT JOIN technicien_access ta ON tal.technicien_id = ta.id
    $auditWhereSQL
    ORDER BY tal.created_at DESC
    LIMIT $auditPerPage OFFSET $auditOffset
");
$auditStmt->execute($auditParams);
$auditLogs = $auditStmt->fetchAll(PDO::FETCH_ASSOC);

// Actions distinctes pour filtre audit
$auditActions = $pdo->query("SELECT DISTINCT action FROM technicien_audit_log ORDER BY action")->fetchAll(PDO::FETCH_COLUMN);

// ─── Page ──────────────────────────────────────────────────────────────────

$pageTitle = 'Acces technicien';
$currentPage = 'systeme';
$extraCss = ['../../assets/css/admin.css'];

ob_start();
?>
<style>
.tech-container{max-width:1100px;margin:0 auto}
.tech-stats{display:flex;gap:16px;margin-bottom:24px}
.tech-stat{background:#fff;border-radius:8px;padding:14px 20px;flex:1;text-align:center;box-shadow:0 1px 4px rgba(0,0,0,.05)}
.tech-stat-value{font-size:1.8em;font-weight:700;color:#333}
.tech-stat-label{font-size:.85em;color:#718096;margin-top:2px}
.tab-nav{display:flex;gap:0;border-bottom:2px solid #e2e8f0;margin-bottom:20px}
.tab-btn{padding:10px 20px;background:none;border:none;border-bottom:2px solid transparent;margin-bottom:-2px;cursor:pointer;font-size:14px;font-weight:500;color:#718096;transition:color .15s,border-color .15s}
.tab-btn.active{color:#0f4c81;border-bottom-color:#0f4c81}
.tab-pane{display:none}
.tab-pane.active{display:block}
.tech-card{background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:18px 20px;margin-bottom:14px;box-shadow:0 1px 4px rgba(0,0,0,.04);transition:.2s}
.tech-card:hover{box-shadow:0 2px 10px rgba(0,0,0,.08)}
.tech-card-header{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:10px}
.tech-card-name{font-weight:600;font-size:1.05em;color:#2d3748}
.tech-card-login{font-family:monospace;font-size:.9em;color:#667eea;background:#f0f4ff;padding:2px 8px;border-radius:4px}
.tech-card-meta{display:flex;flex-wrap:wrap;gap:10px;font-size:.85em;color:#718096;margin-bottom:8px}
.tech-card-meta span{display:flex;align-items:center;gap:4px}
.tech-card-motif{font-size:.88em;color:#4a5568;background:#f7fafc;padding:8px 12px;border-radius:6px;border-left:3px solid #667eea;margin-bottom:10px}
.tech-card-footer{display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap}
.badge-status{display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:20px;font-size:.78em;font-weight:600}
.badge-active{background:#d1fae5;color:#065f46}
.badge-expired{background:#fef3c7;color:#92400e}
.badge-revoked{background:#fee2e2;color:#991b1b}
.badge-perm{display:inline-block;padding:2px 8px;border-radius:10px;font-size:.72em;font-weight:600;background:#ebf4ff;color:#3182ce}
.badge-perm-standard{background:#d1fae5;color:#276749}
.badge-perm-complet{background:#fef3c7;color:#92400e}
.countdown{font-family:monospace;font-size:.9em;font-weight:600;color:#065f46;background:#d1fae5;padding:4px 10px;border-radius:6px}
.btn-revoke{background:#e53e3e;color:#fff;border:none;padding:6px 14px;border-radius:6px;font-size:.82em;font-weight:600;cursor:pointer;transition:.15s}
.btn-revoke:hover{background:#c53030}
.form-card{background:#fff;border-radius:10px;box-shadow:0 2px 8px rgba(0,0,0,.06);padding:24px 28px}
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.form-group{display:flex;flex-direction:column;gap:4px}
.form-group.full{grid-column:1/-1}
.form-group label{font-size:.85em;font-weight:600;color:#4a5568}
.form-group input,.form-group select,.form-group textarea{padding:8px 12px;border:1px solid #d2d6dc;border-radius:6px;font-size:.9em;font-family:inherit}
.form-group textarea{resize:vertical;min-height:60px}
.form-group .help{font-size:.78em;color:#a0aec0;margin-top:2px}
.btn-create{background:#0f4c81;color:#fff;border:none;padding:10px 24px;border-radius:8px;font-size:.95em;font-weight:600;cursor:pointer;transition:.15s;display:inline-flex;align-items:center;gap:6px}
.btn-create:hover{background:#0d3f6b}
.modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.5);display:flex;align-items:center;justify-content:center;z-index:9999}
.modal-box{background:#fff;border-radius:12px;padding:28px 32px;max-width:500px;width:90%;box-shadow:0 10px 40px rgba(0,0,0,.15)}
.modal-box h3{margin:0 0 12px;color:#2d3748;font-size:1.15em}
.modal-box .cred-block{background:#f7fafc;border:1px solid #e2e8f0;border-radius:8px;padding:14px 18px;margin:12px 0;font-family:monospace;font-size:.95em}
.modal-box .cred-label{font-size:.8em;color:#718096;font-weight:600;font-family:sans-serif;margin-bottom:2px}
.modal-box .cred-value{font-size:1.05em;color:#2d3748;user-select:all}
.modal-box .warning{background:#fef3c7;color:#92400e;padding:10px 14px;border-radius:6px;font-size:.85em;margin:14px 0;display:flex;align-items:center;gap:8px}
.btn-modal-close{background:#0f4c81;color:#fff;border:none;padding:8px 20px;border-radius:6px;font-size:.9em;font-weight:600;cursor:pointer;margin-top:8px}
.btn-copy{background:#667eea;color:#fff;border:none;padding:4px 10px;border-radius:4px;font-size:.78em;cursor:pointer;margin-left:8px}
.btn-copy:hover{background:#5a67d8}
.audit-table{width:100%;border-collapse:collapse;background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.06)}
.audit-table th,.audit-table td{padding:8px 12px;text-align:left;border-bottom:1px solid #f0f0f0;font-size:.82em}
.audit-table th{background:#f7fafc;font-weight:600;color:#4a5568}
.audit-filters{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px;background:#fff;padding:10px 14px;border-radius:8px;box-shadow:0 1px 4px rgba(0,0,0,.04);align-items:flex-end}
.audit-filters .fg{display:flex;flex-direction:column;gap:3px}
.audit-filters label{font-size:.75em;font-weight:600;color:#4a5568}
.audit-filters select{padding:5px 8px;border:1px solid #d2d6dc;border-radius:5px;font-size:.82em}
.empty-state{text-align:center;padding:40px;color:#a0aec0;font-size:.95em}
.pagination{display:flex;gap:4px;justify-content:center;margin-top:16px}
.pagination a,.pagination span{padding:5px 10px;border-radius:4px;font-size:.82em;text-decoration:none;border:1px solid #e2e8f0;color:#4a5568}
.pagination span.current{background:#0f4c81;color:#fff;border-color:#0f4c81}
.pagination a:hover{background:#f7fafc}
.msg-toast{padding:12px 16px;border-radius:6px;margin-bottom:16px;font-size:.92em;display:flex;align-items:center;gap:8px}
.msg-success{background:#d1fae5;color:#065f46}
.msg-error{background:#fee2e2;color:#991b1b}
</style>
<?php
$extraHeadHtml = ob_get_clean();
include __DIR__ . '/../includes/header.php';
?>

<div class="tech-container">

<?php if ($message): ?>
<div class="msg-toast msg-<?= $messageType ?>">
    <i class="fas fa-<?= $messageType === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
    <?= htmlspecialchars($message) ?>
</div>
<?php endif; ?>

<!-- Statistiques -->
<div class="tech-stats">
    <div class="tech-stat">
        <div class="tech-stat-value" style="color:#48bb78"><?= $totalActive ?></div>
        <div class="tech-stat-label">Actifs</div>
    </div>
    <div class="tech-stat">
        <div class="tech-stat-value" style="color:#ed8936"><?= $totalExpired ?></div>
        <div class="tech-stat-label">Expires</div>
    </div>
    <div class="tech-stat">
        <div class="tech-stat-value" style="color:#e53e3e"><?= $totalRevoked ?></div>
        <div class="tech-stat-label">Revoques</div>
    </div>
    <div class="tech-stat">
        <div class="tech-stat-value"><?= count($allAccess) ?></div>
        <div class="tech-stat-label">Total</div>
    </div>
</div>

<!-- Onglets -->
<div class="tab-nav">
    <button class="tab-btn active" onclick="switchTab('actifs',this)"><i class="fas fa-shield-alt"></i> Acces actifs (<?= $totalActive ?>)</button>
    <button class="tab-btn" onclick="switchTab('historique',this)"><i class="fas fa-history"></i> Historique</button>
    <button class="tab-btn" onclick="switchTab('creer',this)"><i class="fas fa-plus-circle"></i> Creer un acces</button>
    <button class="tab-btn" onclick="switchTab('audit',this)"><i class="fas fa-clipboard-list"></i> Journal d'audit</button>
</div>

<!-- ═══ ACCES ACTIFS ═══ -->
<div id="tab-actifs" class="tab-pane active">
    <?php if (empty($activeAccess)): ?>
        <div class="empty-state">
            <i class="fas fa-shield-alt" style="font-size:2em;margin-bottom:10px;display:block;opacity:.4"></i>
            Aucun acces technicien actif.
        </div>
    <?php else: ?>
        <?php foreach ($activeAccess as $acc): ?>
        <div class="tech-card">
            <div class="tech-card-header">
                <div>
                    <span class="tech-card-name"><?= htmlspecialchars($acc['prenom'] . ' ' . $acc['nom']) ?></span>
                    <span class="tech-card-login"><?= htmlspecialchars($acc['identifiant']) ?></span>
                </div>
                <span class="badge-status badge-active"><i class="fas fa-circle" style="font-size:.5em"></i> Actif</span>
            </div>
            <div class="tech-card-meta">
                <?php if (!empty($acc['email'])): ?>
                    <span><i class="fas fa-envelope"></i> <?= htmlspecialchars($acc['email']) ?></span>
                <?php endif; ?>
                <span><i class="fas fa-calendar-plus"></i> Cree le <?= date('d/m/Y H:i', strtotime($acc['created_at'])) ?></span>
                <span><i class="fas fa-user-plus"></i> Par <?= htmlspecialchars($acc['created_by_name'] ?? 'Inconnu') ?></span>
                <span><i class="fas fa-sign-in-alt"></i> <?= intval($acc['login_count']) ?> connexion(s)</span>
                <?php
                    $perms = json_decode($acc['permissions'] ?? '[]', true);
                    $permLevel = 'Lecture seule';
                    $permClass = 'badge-perm';
                    if (in_array('delete', $perms) || in_array('config', $perms)) {
                        $permLevel = 'Complet';
                        $permClass = 'badge-perm badge-perm-complet';
                    } elseif (in_array('write', $perms)) {
                        $permLevel = 'Standard';
                        $permClass = 'badge-perm badge-perm-standard';
                    }
                ?>
                <span class="<?= $permClass ?>"><?= $permLevel ?></span>
            </div>
            <div class="tech-card-motif">
                <strong>Motif :</strong> <?= htmlspecialchars($acc['motif']) ?>
            </div>
            <?php
                $ipList = json_decode($acc['ip_whitelist'] ?? 'null', true);
                $modulesList = json_decode($acc['modules_autorises'] ?? 'null', true);
            ?>
            <?php if (!empty($ipList)): ?>
            <div style="font-size:.82em;color:#718096;margin-bottom:6px">
                <i class="fas fa-network-wired"></i> IP autorisees : <?= htmlspecialchars(implode(', ', $ipList)) ?>
            </div>
            <?php endif; ?>
            <?php if (!empty($modulesList)): ?>
            <div style="font-size:.82em;color:#718096;margin-bottom:6px">
                <i class="fas fa-puzzle-piece"></i> Modules : <?= htmlspecialchars(implode(', ', $modulesList)) ?>
            </div>
            <?php endif; ?>
            <div class="tech-card-footer">
                <div style="display:flex;align-items:center;gap:12px">
                    <span style="font-size:.85em;color:#718096">
                        <i class="fas fa-clock"></i> Expire le <?= date('d/m/Y H:i', strtotime($acc['date_expiration'])) ?>
                    </span>
                    <span class="countdown" data-expires="<?= strtotime($acc['date_expiration']) ?>">
                        --:--:--
                    </span>
                </div>
                <form method="post" style="margin:0" onsubmit="return confirm('Revoquer immediatement cet acces technicien ?')">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="action" value="revoke">
                    <input type="hidden" name="tech_id" value="<?= $acc['id'] ?>">
                    <button type="submit" class="btn-revoke"><i class="fas fa-ban"></i> Revoquer</button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- ═══ HISTORIQUE ═══ -->
<div id="tab-historique" class="tab-pane">
    <?php if (empty($historyAccess)): ?>
        <div class="empty-state">
            <i class="fas fa-history" style="font-size:2em;margin-bottom:10px;display:block;opacity:.4"></i>
            Aucun historique.
        </div>
    <?php else: ?>
        <?php foreach ($historyAccess as $acc): ?>
        <div class="tech-card" style="<?= $acc['_status'] === 'revoked' ? 'border-left:3px solid #e53e3e;opacity:.8' : 'border-left:3px solid #ed8936;opacity:.8' ?>">
            <div class="tech-card-header">
                <div>
                    <span class="tech-card-name"><?= htmlspecialchars($acc['prenom'] . ' ' . $acc['nom']) ?></span>
                    <span class="tech-card-login"><?= htmlspecialchars($acc['identifiant']) ?></span>
                </div>
                <?php if ($acc['_status'] === 'revoked'): ?>
                    <span class="badge-status badge-revoked"><i class="fas fa-ban" style="font-size:.7em"></i> Revoque</span>
                <?php else: ?>
                    <span class="badge-status badge-expired"><i class="fas fa-clock" style="font-size:.7em"></i> Expire</span>
                <?php endif; ?>
            </div>
            <div class="tech-card-meta">
                <?php if (!empty($acc['email'])): ?>
                    <span><i class="fas fa-envelope"></i> <?= htmlspecialchars($acc['email']) ?></span>
                <?php endif; ?>
                <span><i class="fas fa-calendar-plus"></i> Du <?= date('d/m/Y H:i', strtotime($acc['date_debut'])) ?></span>
                <span><i class="fas fa-calendar-times"></i> Au <?= date('d/m/Y H:i', strtotime($acc['date_expiration'])) ?></span>
                <span><i class="fas fa-sign-in-alt"></i> <?= intval($acc['login_count']) ?> connexion(s)</span>
                <?php
                    $perms = json_decode($acc['permissions'] ?? '[]', true);
                    $permLevel = 'Lecture seule';
                    if (in_array('delete', $perms) || in_array('config', $perms)) {
                        $permLevel = 'Complet';
                    } elseif (in_array('write', $perms)) {
                        $permLevel = 'Standard';
                    }
                ?>
                <span class="badge-perm"><?= $permLevel ?></span>
            </div>
            <div class="tech-card-motif">
                <strong>Motif :</strong> <?= htmlspecialchars($acc['motif']) ?>
            </div>
            <?php if ($acc['_status'] === 'revoked' && !empty($acc['revoked_by_name'])): ?>
            <div style="font-size:.82em;color:#991b1b;margin-top:4px">
                <i class="fas fa-user-slash"></i> Revoque par <?= htmlspecialchars($acc['revoked_by_name']) ?> le <?= date('d/m/Y H:i', strtotime($acc['revoked_at'])) ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- ═══ CREER UN ACCES ═══ -->
<div id="tab-creer" class="tab-pane">
    <div class="form-card">
        <h3 style="margin:0 0 18px;color:#2d3748;font-size:1.1em"><i class="fas fa-user-shield"></i> Nouveau compte technicien</h3>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <input type="hidden" name="action" value="create">

            <div class="form-grid">
                <div class="form-group">
                    <label for="nom"><i class="fas fa-user"></i> Nom *</label>
                    <input type="text" id="nom" name="nom" required placeholder="Dupont" maxlength="100">
                </div>
                <div class="form-group">
                    <label for="prenom"><i class="fas fa-user"></i> Prenom *</label>
                    <input type="text" id="prenom" name="prenom" required placeholder="Jean" maxlength="100">
                </div>
                <div class="form-group">
                    <label for="email"><i class="fas fa-envelope"></i> Email</label>
                    <input type="email" id="email" name="email" placeholder="technicien@exemple.fr" maxlength="150">
                    <span class="help">Optionnel, pour contact uniquement</span>
                </div>
                <div class="form-group">
                    <label for="duree_heures"><i class="fas fa-clock"></i> Duree d'acces</label>
                    <select id="duree_heures" name="duree_heures">
                        <option value="1">1 heure</option>
                        <option value="2">2 heures</option>
                        <option value="4">4 heures</option>
                        <option value="8">8 heures</option>
                        <option value="12">12 heures</option>
                        <option value="24" selected>24 heures (defaut)</option>
                        <option value="48">48 heures</option>
                        <option value="72">3 jours</option>
                        <option value="120">5 jours</option>
                        <option value="168">7 jours (max)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="permission_level"><i class="fas fa-key"></i> Niveau de permission</label>
                    <select id="permission_level" name="permission_level">
                        <option value="lecture">Lecture seule (consultation)</option>
                        <option value="standard">Standard (consultation + modification)</option>
                        <option value="complet">Complet (tous les droits)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="ip_whitelist"><i class="fas fa-network-wired"></i> Restriction IP</label>
                    <input type="text" id="ip_whitelist" name="ip_whitelist" placeholder="192.168.1.1, 10.0.0.0/24">
                    <span class="help">Optionnel. IPs separees par des virgules. Laisser vide = toutes les IPs</span>
                </div>
                <div class="form-group full">
                    <label for="motif"><i class="fas fa-file-alt"></i> Motif de l'acces *</label>
                    <textarea id="motif" name="motif" required placeholder="Ex: Maintenance du serveur, correction bug critique, mise a jour module..."></textarea>
                </div>
                <div class="form-group full">
                    <label><i class="fas fa-puzzle-piece"></i> Restriction par modules</label>
                    <div style="display:flex;flex-wrap:wrap;gap:10px;padding:8px 0">
                        <?php
                        $availableModules = [
                            'users' => 'Utilisateurs',
                            'scolaire' => 'Vie scolaire',
                            'classes' => 'Classes',
                            'messagerie' => 'Messagerie',
                            'etablissement' => 'Etablissement',
                            'systeme' => 'Systeme',
                            'modules' => 'Modules',
                        ];
                        foreach ($availableModules as $modKey => $modLabel): ?>
                        <label style="display:flex;align-items:center;gap:4px;font-size:.88em;color:#4a5568;cursor:pointer">
                            <input type="checkbox" name="modules_autorises[]" value="<?= $modKey ?>">
                            <?= $modLabel ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    <span class="help">Optionnel. Si aucun module selectionne, acces a tous les modules selon le niveau de permission.</span>
                </div>
            </div>

            <div style="margin-top:20px;display:flex;align-items:center;gap:14px">
                <button type="submit" class="btn-create">
                    <i class="fas fa-user-plus"></i> Creer l'acces technicien
                </button>
                <span style="font-size:.82em;color:#a0aec0">L'identifiant et le mot de passe seront generes automatiquement.</span>
            </div>
        </form>
    </div>
</div>

<!-- ═══ JOURNAL D'AUDIT ═══ -->
<div id="tab-audit" class="tab-pane">
    <form method="get" class="audit-filters">
        <div class="fg">
            <label>Technicien</label>
            <select name="filter_tech">
                <option value="0">Tous</option>
                <?php foreach ($allAccess as $acc): ?>
                    <option value="<?= $acc['id'] ?>" <?= $filterTechId == $acc['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($acc['identifiant'] . ' - ' . $acc['prenom'] . ' ' . $acc['nom']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="fg">
            <label>Action</label>
            <select name="filter_action">
                <option value="">Toutes</option>
                <?php foreach ($auditActions as $aa): ?>
                    <option value="<?= htmlspecialchars($aa) ?>" <?= $filterAuditAction === $aa ? 'selected' : '' ?>>
                        <?= htmlspecialchars($aa) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button class="btn-create" style="padding:6px 14px;font-size:.82em"><i class="fas fa-filter"></i> Filtrer</button>
        <a href="technicien.php" style="font-size:.82em;text-decoration:none;padding:6px;color:#718096">Reset</a>
    </form>

    <?php if (empty($auditLogs)): ?>
        <div class="empty-state">
            <i class="fas fa-clipboard-list" style="font-size:2em;margin-bottom:10px;display:block;opacity:.4"></i>
            Aucune entree dans le journal d'audit.
        </div>
    <?php else: ?>
    <table class="audit-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Date</th>
                <th>Technicien</th>
                <th>Action</th>
                <th>Details</th>
                <th>IP</th>
                <th>User-Agent</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($auditLogs as $log): ?>
            <tr>
                <td style="color:#a0aec0"><?= $log['id'] ?></td>
                <td style="white-space:nowrap"><?= date('d/m/Y H:i:s', strtotime($log['created_at'])) ?></td>
                <td>
                    <span style="font-weight:600;color:#2d3748"><?= htmlspecialchars($log['tech_name'] ?? '-') ?></span>
                    <br><span style="font-family:monospace;font-size:.9em;color:#667eea"><?= htmlspecialchars($log['tech_login'] ?? '-') ?></span>
                </td>
                <td>
                    <?php
                    $actionClass = 'background:#e2e8f0;color:#4a5568';
                    if (str_contains($log['action'], 'created') || str_contains($log['action'], 'login')) {
                        $actionClass = 'background:#d1fae5;color:#065f46';
                    } elseif (str_contains($log['action'], 'revoked') || str_contains($log['action'], 'denied')) {
                        $actionClass = 'background:#fee2e2;color:#991b1b';
                    } elseif (str_contains($log['action'], 'action')) {
                        $actionClass = 'background:#dbeafe;color:#1e40af';
                    }
                    ?>
                    <span style="display:inline-block;padding:2px 8px;border-radius:6px;font-size:.82em;font-weight:600;font-family:monospace;<?= $actionClass ?>">
                        <?= htmlspecialchars($log['action']) ?>
                    </span>
                </td>
                <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-family:monospace;font-size:.78em;color:#666;cursor:pointer" title="<?= htmlspecialchars($log['details'] ?? '') ?>">
                    <?= htmlspecialchars(mb_substr($log['details'] ?? '-', 0, 60)) ?>
                </td>
                <td style="font-family:monospace;font-size:.82em"><?= htmlspecialchars($log['ip_address'] ?? '-') ?></td>
                <td style="max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:.78em;color:#999" title="<?= htmlspecialchars($log['user_agent'] ?? '') ?>">
                    <?= htmlspecialchars(mb_substr($log['user_agent'] ?? '-', 0, 40)) ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <?php if ($auditTotalPages > 1): ?>
    <div class="pagination">
        <?php
        $qs = http_build_query(array_filter(['filter_tech' => $filterTechId ?: null, 'filter_action' => $filterAuditAction ?: null]));
        $start = max(1, $auditPage - 5);
        $end = min($auditTotalPages, $auditPage + 5);
        if ($auditPage > 1): ?>
            <a href="?<?= $qs ?>&audit_page=<?= $auditPage - 1 ?>">&laquo;</a>
        <?php endif;
        for ($i = $start; $i <= $end; $i++):
            if ($i === $auditPage): ?>
                <span class="current"><?= $i ?></span>
            <?php else: ?>
                <a href="?<?= $qs ?>&audit_page=<?= $i ?>"><?= $i ?></a>
            <?php endif;
        endfor;
        if ($auditPage < $auditTotalPages): ?>
            <a href="?<?= $qs ?>&audit_page=<?= $auditPage + 1 ?>">&raquo;</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

</div><!-- fin tech-container -->

<!-- Modal mot de passe genere -->
<?php if ($showPasswordModal): ?>
<div class="modal-overlay" id="passwordModal">
    <div class="modal-box">
        <h3><i class="fas fa-key" style="color:#48bb78"></i> Acces technicien cree</h3>
        <p style="font-size:.9em;color:#4a5568;margin:0 0 14px">
            Voici les identifiants de connexion. <strong>Le mot de passe ne sera plus jamais affiche.</strong>
        </p>

        <div class="cred-block">
            <div class="cred-label">Identifiant</div>
            <div class="cred-value" id="credLogin"><?= htmlspecialchars($generatedLogin) ?></div>
        </div>
        <div class="cred-block">
            <div class="cred-label">Mot de passe</div>
            <div class="cred-value" id="credPassword"><?= htmlspecialchars($generatedPassword) ?></div>
        </div>

        <div style="display:flex;gap:8px;margin:12px 0 6px">
            <button class="btn-copy" onclick="copyCredentials()"><i class="fas fa-copy"></i> Copier les identifiants</button>
        </div>

        <div class="warning">
            <i class="fas fa-exclamation-triangle"></i>
            Conservez ces informations en lieu sur. Le mot de passe est irreversiblement chiffre et ne pourra pas etre recupere.
        </div>

        <button class="btn-modal-close" onclick="document.getElementById('passwordModal').remove()">
            <i class="fas fa-check"></i> J'ai note les identifiants
        </button>
    </div>
</div>
<?php endif; ?>

<script>
// Gestion des onglets
function switchTab(name, btn) {
    document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('tab-' + name).classList.add('active');
    btn.classList.add('active');
}

// Ouvrir l'onglet audit si des filtres sont actifs dans l'URL
(function() {
    const params = new URLSearchParams(window.location.search);
    if (params.has('filter_tech') || params.has('filter_action') || params.has('audit_page')) {
        const btn = document.querySelectorAll('.tab-btn')[3];
        if (btn) switchTab('audit', btn);
    }
})();

// Countdown timers
function updateCountdowns() {
    document.querySelectorAll('.countdown').forEach(el => {
        const expires = parseInt(el.dataset.expires, 10);
        const now = Math.floor(Date.now() / 1000);
        let remaining = expires - now;

        if (remaining <= 0) {
            el.textContent = 'Expire';
            el.style.background = '#fee2e2';
            el.style.color = '#991b1b';
            return;
        }

        const days = Math.floor(remaining / 86400);
        remaining %= 86400;
        const hours = Math.floor(remaining / 3600);
        remaining %= 3600;
        const minutes = Math.floor(remaining / 60);
        const seconds = remaining % 60;

        let text = '';
        if (days > 0) text += days + 'j ';
        text += String(hours).padStart(2, '0') + ':' +
                String(minutes).padStart(2, '0') + ':' +
                String(seconds).padStart(2, '0');

        el.textContent = text;

        // Couleur selon le temps restant
        const totalSeconds = expires - Math.floor(Date.now() / 1000);
        if (totalSeconds < 3600) {
            el.style.background = '#fee2e2';
            el.style.color = '#991b1b';
        } else if (totalSeconds < 7200) {
            el.style.background = '#fef3c7';
            el.style.color = '#92400e';
        }
    });
}
updateCountdowns();
setInterval(updateCountdowns, 1000);

// Copier les identifiants
function copyCredentials() {
    const login = document.getElementById('credLogin')?.textContent || '';
    const password = document.getElementById('credPassword')?.textContent || '';
    const text = 'Identifiant: ' + login + '\nMot de passe: ' + password;

    navigator.clipboard.writeText(text).then(() => {
        const btn = event.target.closest('.btn-copy');
        const original = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-check"></i> Copie !';
        btn.style.background = '#48bb78';
        setTimeout(() => {
            btn.innerHTML = original;
            btn.style.background = '';
        }, 2000);
    }).catch(() => {
        // Fallback
        const ta = document.createElement('textarea');
        ta.value = text;
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
    });
}

// Si la modal est affichee, ouvrir l'onglet "Creer"
<?php if ($showPasswordModal): ?>
(function() {
    const btn = document.querySelectorAll('.tab-btn')[2];
    if (btn) switchTab('creer', btn);
})();
<?php endif; ?>
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
