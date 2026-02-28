<?php
/**
 * Tableau de bord — Vue d'ensemble de l'administration
 */
require_once __DIR__ . '/../API/core.php';
require_once __DIR__ . '/includes/admin_functions.php';

requireAuth();
requireRole('administrateur');

$pdo = getPDO();
$admin = getCurrentUser();

// --- Compteurs principaux ---
$counts = [];
try {
    $counts['eleves']      = (int)$pdo->query("SELECT COUNT(*) FROM eleves WHERE actif = 1")->fetchColumn();
    $counts['professeurs'] = (int)$pdo->query("SELECT COUNT(*) FROM professeurs WHERE actif = 1")->fetchColumn();
    $counts['parents']     = (int)$pdo->query("SELECT COUNT(*) FROM parents WHERE actif = 1")->fetchColumn();
    $counts['vie_scolaire']= (int)$pdo->query("SELECT COUNT(*) FROM vie_scolaire WHERE actif = 1")->fetchColumn();
} catch (Exception $e) { $counts = array_merge(['eleves'=>0,'professeurs'=>0,'parents'=>0,'vie_scolaire'=>0], $counts); }

try {
    $counts['absences_today'] = (int)$pdo->query("SELECT COUNT(*) FROM absences WHERE DATE(date_debut) = CURDATE()")->fetchColumn();
} catch (Exception $e) { $counts['absences_today'] = 0; }

try {
    $counts['reset_pending'] = (int)$pdo->query("SELECT COUNT(*) FROM demandes_reinitialisation WHERE status = 'pending'")->fetchColumn();
} catch (Exception $e) { $counts['reset_pending'] = 0; }

try {
    $counts['justificatifs'] = (int)$pdo->query("SELECT COUNT(*) FROM justificatifs WHERE traite = 0")->fetchColumn();
} catch (Exception $e) { $counts['justificatifs'] = 0; }

try {
    $counts['messages_24h'] = (int)$pdo->query("SELECT COUNT(*) FROM messages WHERE is_deleted = 0 AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)")->fetchColumn();
} catch (Exception $e) { $counts['messages_24h'] = 0; }

// --- Comptes verrouillés ---
$locked = [];
try {
    $stmt = $pdo->query("
        SELECT identifiant, locked_until, 'eleve' as type FROM eleves WHERE locked_until > NOW()
        UNION ALL
        SELECT identifiant, locked_until, 'professeur' FROM professeurs WHERE locked_until > NOW()
        UNION ALL
        SELECT identifiant, locked_until, 'parent' FROM parents WHERE locked_until > NOW()
        UNION ALL
        SELECT identifiant, locked_until, 'vie_scolaire' FROM vie_scolaire WHERE locked_until > NOW()
        UNION ALL
        SELECT identifiant, locked_until, 'administrateur' FROM administrateurs WHERE locked_until > NOW()
    ");
    $locked = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// --- Dernières connexions ---
$recentLogins = [];
try {
    $stmt = $pdo->query("
        (SELECT identifiant, last_login, 'eleve' as type FROM eleves WHERE last_login IS NOT NULL ORDER BY last_login DESC LIMIT 5)
        UNION ALL
        (SELECT identifiant, last_login, 'professeur' FROM professeurs WHERE last_login IS NOT NULL ORDER BY last_login DESC LIMIT 5)
        UNION ALL
        (SELECT identifiant, last_login, 'parent' FROM parents WHERE last_login IS NOT NULL ORDER BY last_login DESC LIMIT 5)
        UNION ALL
        (SELECT identifiant, last_login, 'vie_scolaire' FROM vie_scolaire WHERE last_login IS NOT NULL ORDER BY last_login DESC LIMIT 5)
        ORDER BY last_login DESC LIMIT 10
    ");
    $recentLogins = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// --- Messages récents ---
$recentMessages = [];
try {
    $stmt = $pdo->query("
        SELECT m.id, m.body, m.sender_type, m.created_at, c.subject
        FROM messages m
        JOIN conversations c ON m.conversation_id = c.id
        WHERE m.is_deleted = 0
        ORDER BY m.created_at DESC
        LIMIT 5
    ");
    $recentMessages = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$pageTitle = 'Tableau de bord';
$currentPage = 'dashboard';

ob_start();
?>
<style>
    .dashboard-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 30px; }
    .stat-card { background: white; border-radius: 10px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); display: flex; align-items: center; gap: 15px; transition: transform 0.15s; }
    .stat-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
    .stat-icon { width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 22px; color: white; }
    .stat-icon.blue { background: linear-gradient(135deg, #0f4c81, #1a6bc4); }
    .stat-icon.green { background: linear-gradient(135deg, #10b981, #059669); }
    .stat-icon.orange { background: linear-gradient(135deg, #f59e0b, #d97706); }
    .stat-icon.red { background: linear-gradient(135deg, #ef4444, #dc2626); }
    .stat-icon.purple { background: linear-gradient(135deg, #8b5cf6, #7c3aed); }
    .stat-icon.pink { background: linear-gradient(135deg, #ec4899, #db2777); }
    .stat-info { flex: 1; }
    .stat-number { font-size: 28px; font-weight: 700; color: #1a202c; line-height: 1; }
    .stat-label { font-size: 13px; color: #718096; margin-top: 4px; }

    .dashboard-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
    @media (max-width: 900px) { .dashboard-row { grid-template-columns: 1fr; } }
    .dashboard-section { background: white; border-radius: 10px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
    .dashboard-section h3 { margin: 0 0 15px; font-size: 16px; color: #2d3748; display: flex; align-items: center; gap: 8px; }
    .dashboard-section h3 i { color: #0f4c81; }

    .alert-list { list-style: none; padding: 0; margin: 0; }
    .alert-list li { padding: 10px 12px; border-left: 3px solid #e74c3c; background: #fef2f2; margin-bottom: 8px; border-radius: 4px; font-size: 14px; display: flex; align-items: center; gap: 8px; }
    .alert-list li.warning { border-left-color: #f59e0b; background: #fffbeb; }
    .alert-list li.info { border-left-color: #3b82f6; background: #eff6ff; }

    .activity-list { list-style: none; padding: 0; margin: 0; }
    .activity-list li { padding: 8px 0; border-bottom: 1px solid #f0f0f0; font-size: 14px; display: flex; justify-content: space-between; align-items: center; }
    .activity-list li:last-child { border-bottom: none; }
    .activity-type { font-size: 11px; padding: 2px 8px; border-radius: 10px; background: #e2e8f0; color: #4a5568; font-weight: 500; }

    .quick-actions { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; }
    .quick-action { display: flex; align-items: center; gap: 10px; padding: 12px 15px; background: #f7fafc; border: 1px solid #e2e8f0; border-radius: 8px; text-decoration: none; color: #2d3748; font-size: 14px; transition: all 0.15s; }
    .quick-action:hover { background: #edf2f7; border-color: #cbd5e0; }
    .quick-action i { color: #0f4c81; font-size: 16px; width: 20px; text-align: center; }
    .msg-preview { max-width: 250px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: #4a5568; }
</style>
<?php
$extraHeadHtml = ob_get_clean();
include 'includes/header.php';
?>

<div style="max-width: 1100px; margin: 0 auto;">
    <!-- Cartes compteurs -->
    <div class="dashboard-grid">
        <div class="stat-card">
            <div class="stat-icon blue"><i class="fas fa-user-graduate"></i></div>
            <div class="stat-info">
                <div class="stat-number"><?= $counts['eleves'] ?></div>
                <div class="stat-label">Élèves actifs</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon green"><i class="fas fa-chalkboard-teacher"></i></div>
            <div class="stat-info">
                <div class="stat-number"><?= $counts['professeurs'] ?></div>
                <div class="stat-label">Professeurs actifs</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon orange"><i class="fas fa-calendar-times"></i></div>
            <div class="stat-info">
                <div class="stat-number"><?= $counts['absences_today'] ?></div>
                <div class="stat-label">Absences aujourd'hui</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon red"><i class="fas fa-key"></i></div>
            <div class="stat-info">
                <div class="stat-number"><?= $counts['reset_pending'] ?></div>
                <div class="stat-label">Demandes en attente</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon purple"><i class="fas fa-file-medical"></i></div>
            <div class="stat-info">
                <div class="stat-number"><?= $counts['justificatifs'] ?></div>
                <div class="stat-label">Justificatifs à traiter</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon pink"><i class="fas fa-envelope"></i></div>
            <div class="stat-info">
                <div class="stat-number"><?= $counts['messages_24h'] ?></div>
                <div class="stat-label">Messages (24h)</div>
            </div>
        </div>
    </div>

    <!-- Alertes + Activité récente -->
    <div class="dashboard-row">
        <div class="dashboard-section">
            <h3><i class="fas fa-exclamation-triangle"></i> Alertes</h3>
            <ul class="alert-list">
                <?php if (!empty($locked)): ?>
                    <?php foreach ($locked as $l): ?>
                    <li><i class="fas fa-lock"></i> <strong><?= htmlspecialchars($l['identifiant']) ?></strong> (<?= htmlspecialchars(getProfilLabel($l['type'])) ?>) — verrouillé jusqu'à <?= date('d/m H:i', strtotime($l['locked_until'])) ?></li>
                    <?php endforeach; ?>
                <?php endif; ?>
                <?php if ($counts['reset_pending'] > 0): ?>
                    <li class="warning"><i class="fas fa-key"></i> <?= $counts['reset_pending'] ?> demande(s) de réinitialisation en attente — <a href="users/passwords.php">Traiter</a></li>
                <?php endif; ?>
                <?php if ($counts['justificatifs'] > 0): ?>
                    <li class="warning"><i class="fas fa-file-medical"></i> <?= $counts['justificatifs'] ?> justificatif(s) à traiter — <a href="scolaire/justificatifs.php">Voir</a></li>
                <?php endif; ?>
                <?php if (empty($locked) && $counts['reset_pending'] == 0 && $counts['justificatifs'] == 0): ?>
                    <li class="info"><i class="fas fa-check-circle"></i> Aucune alerte en cours</li>
                <?php endif; ?>
            </ul>
        </div>

        <div class="dashboard-section">
            <h3><i class="fas fa-clock"></i> Activité récente</h3>
            <?php if (empty($recentLogins)): ?>
                <p style="color: #999; font-size: 14px;">Aucune connexion récente.</p>
            <?php else: ?>
                <ul class="activity-list">
                    <?php foreach ($recentLogins as $login): ?>
                    <li>
                        <span><strong><?= htmlspecialchars($login['identifiant']) ?></strong></span>
                        <span>
                            <span class="activity-type"><?= htmlspecialchars(getProfilLabel($login['type'])) ?></span>
                            <?= date('d/m H:i', strtotime($login['last_login'])) ?>
                        </span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>

    <!-- Messages récents + Raccourcis -->
    <div class="dashboard-row">
        <div class="dashboard-section">
            <h3><i class="fas fa-envelope"></i> Messages récents</h3>
            <?php if (empty($recentMessages)): ?>
                <p style="color: #999; font-size: 14px;">Aucun message récent.</p>
            <?php else: ?>
                <ul class="activity-list">
                    <?php foreach ($recentMessages as $msg): ?>
                    <li>
                        <span>
                            <span class="activity-type"><?= htmlspecialchars(getProfilLabel($msg['sender_type'])) ?></span>
                            <span class="msg-preview"><?= htmlspecialchars(mb_substr(strip_tags($msg['body']), 0, 60)) ?>…</span>
                        </span>
                        <span style="font-size:12px;color:#999;"><?= date('d/m H:i', strtotime($msg['created_at'])) ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <div class="dashboard-section">
            <h3><i class="fas fa-bolt"></i> Raccourcis rapides</h3>
            <div class="quick-actions">
                <a href="users/create.php" class="quick-action"><i class="fas fa-user-plus"></i> Ajouter un utilisateur</a>
                <a href="users/index.php" class="quick-action"><i class="fas fa-users"></i> Gérer les utilisateurs</a>
                <a href="users/passwords.php" class="quick-action"><i class="fas fa-key"></i> Mots de passe</a>
                <a href="scolaire/notes.php" class="quick-action"><i class="fas fa-graduation-cap"></i> Notes & Évaluations</a>
                <a href="scolaire/absences.php" class="quick-action"><i class="fas fa-calendar-times"></i> Absences & Retards</a>
                <a href="classes/index.php" class="quick-action"><i class="fas fa-chalkboard"></i> Gestion des classes</a>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
