<?php
/**
 * Tableau de bord — Hub central de l'administration
 * Affiche des cartes cliquables organisées en sections,
 * des alertes, des compteurs et l'activité récente.
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

// --- Stats supplémentaires ---
$totalUsers = $counts['eleves'] + $counts['professeurs'] + $counts['parents'] + $counts['vie_scolaire'];
$totalClasses = 0;
$totalSessions = 0;
$totalAdmins = 0;
try {
    $totalClasses = (int)$pdo->query("SELECT COUNT(*) FROM classes")->fetchColumn();
    $totalAdmins = (int)$pdo->query("SELECT COUNT(*) FROM administrateurs WHERE actif = 1")->fetchColumn();
} catch (Exception $e) {}
try {
    $totalSessions = (int)$pdo->query("SELECT COUNT(*) FROM session_security WHERE is_active = 1")->fetchColumn();
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
$extraCss = ['../assets/css/admin.css'];

include 'includes/header.php';
?>

<div class="admin-dashboard">
    <!-- Alertes urgentes -->
    <?php if (!empty($locked) || $counts['reset_pending'] > 0 || $counts['justificatifs'] > 0): ?>
    <div class="admin-alerts">
        <?php if ($counts['reset_pending'] > 0): ?>
        <div class="admin-alert-item">
            <i class="fas fa-key"></i>
            <?= $counts['reset_pending'] ?> demande(s) de réinitialisation en attente
            <a href="users/passwords.php">Traiter <i class="fas fa-arrow-right"></i></a>
        </div>
        <?php endif; ?>
        <?php if ($counts['justificatifs'] > 0): ?>
        <div class="admin-alert-item">
            <i class="fas fa-file-medical"></i>
            <?= $counts['justificatifs'] ?> justificatif(s) en attente de traitement
            <a href="scolaire/justificatifs.php">Voir <i class="fas fa-arrow-right"></i></a>
        </div>
        <?php endif; ?>
        <?php foreach ($locked as $l): ?>
        <div class="admin-alert-item">
            <i class="fas fa-lock"></i>
            <strong><?= htmlspecialchars($l['identifiant']) ?></strong> (<?= htmlspecialchars(getProfilLabel($l['type'])) ?>) — verrouillé jusqu'à <?= date('d/m H:i', strtotime($l['locked_until'])) ?>
            <a href="users/index.php">Gérer <i class="fas fa-arrow-right"></i></a>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Compteurs KPI -->
    <div class="admin-stats-row">
        <div class="admin-stat-card">
            <div class="admin-stat-value" style="color:#0f4c81"><?= $counts['eleves'] ?></div>
            <div class="admin-stat-label">Élèves actifs</div>
        </div>
        <div class="admin-stat-card">
            <div class="admin-stat-value" style="color:#2d7d46"><?= $counts['professeurs'] ?></div>
            <div class="admin-stat-label">Professeurs actifs</div>
        </div>
        <div class="admin-stat-card">
            <div class="admin-stat-value" style="color:#b45309"><?= $counts['parents'] ?></div>
            <div class="admin-stat-label">Parents actifs</div>
        </div>
        <div class="admin-stat-card">
            <div class="admin-stat-value" style="color:#6b21a8"><?= $counts['vie_scolaire'] ?></div>
            <div class="admin-stat-label">Vie scolaire</div>
        </div>
        <div class="admin-stat-card">
            <div class="admin-stat-value" style="color:#dc2626"><?= $counts['absences_today'] ?></div>
            <div class="admin-stat-label">Absences aujourd'hui</div>
        </div>
    </div>

    <!-- ══════ GESTION DES UTILISATEURS ══════ -->
    <div class="admin-cards-section">
        <div class="admin-cards-section-title"><i class="fas fa-users"></i> Gestion des utilisateurs</div>
        <div class="admin-cards-grid">
            <a href="users/index.php" class="admin-card">
                <div class="admin-card-icon"><i class="fas fa-users"></i></div>
                <div class="admin-card-title">Tous les utilisateurs</div>
                <div class="admin-card-stat"><?= $totalUsers ?> actifs</div>
            </a>
            <a href="users/create.php" class="admin-card">
                <div class="admin-card-icon"><i class="fas fa-user-plus"></i></div>
                <div class="admin-card-title">Ajouter un utilisateur</div>
                <div class="admin-card-stat">Créer un compte</div>
            </a>
            <a href="users/admins.php" class="admin-card">
                <div class="admin-card-icon"><i class="fas fa-user-shield"></i></div>
                <div class="admin-card-title">Administrateurs</div>
                <div class="admin-card-stat"><?= $totalAdmins ?> admin(s)</div>
            </a>
            <a href="users/passwords.php" class="admin-card">
                <div class="admin-card-icon"><i class="fas fa-key"></i></div>
                <div class="admin-card-title">Mots de passe</div>
                <?php if ($counts['reset_pending'] > 0): ?>
                    <span class="admin-card-badge"><?= $counts['reset_pending'] ?></span>
                <?php endif; ?>
                <div class="admin-card-stat"><?= $counts['reset_pending'] ?> en attente</div>
            </a>
            <a href="users/sessions.php" class="admin-card">
                <div class="admin-card-icon"><i class="fas fa-desktop"></i></div>
                <div class="admin-card-title">Sessions actives</div>
                <div class="admin-card-stat"><?= $totalSessions ?> en ligne</div>
            </a>
        </div>
    </div>

    <!-- ══════ VIE SCOLAIRE ══════ -->
    <div class="admin-cards-section">
        <div class="admin-cards-section-title"><i class="fas fa-graduation-cap"></i> Vie scolaire</div>
        <div class="admin-cards-grid">
            <a href="scolaire/notes.php" class="admin-card">
                <div class="admin-card-icon"><i class="fas fa-graduation-cap"></i></div>
                <div class="admin-card-title">Notes & Évaluations</div>
                <div class="admin-card-stat">Consulter / modifier</div>
            </a>
            <a href="scolaire/absences.php" class="admin-card">
                <div class="admin-card-icon"><i class="fas fa-calendar-times"></i></div>
                <div class="admin-card-title">Absences & Retards</div>
                <div class="admin-card-stat"><?= $counts['absences_today'] ?> aujourd'hui</div>
            </a>
            <a href="scolaire/justificatifs.php" class="admin-card">
                <div class="admin-card-icon"><i class="fas fa-file-medical"></i></div>
                <div class="admin-card-title">Justificatifs</div>
                <?php if ($counts['justificatifs'] > 0): ?>
                    <span class="admin-card-badge"><?= $counts['justificatifs'] ?></span>
                <?php endif; ?>
                <div class="admin-card-stat"><?= $counts['justificatifs'] ?> en attente</div>
            </a>
            <a href="scolaire/devoirs.php" class="admin-card">
                <div class="admin-card-icon"><i class="fas fa-book"></i></div>
                <div class="admin-card-title">Devoirs</div>
                <div class="admin-card-stat">Consulter / modifier</div>
            </a>
        </div>
    </div>

    <!-- ══════ CLASSES & ENSEIGNEMENT ══════ -->
    <div class="admin-cards-section">
        <div class="admin-cards-section-title"><i class="fas fa-chalkboard"></i> Classes & Enseignement</div>
        <div class="admin-cards-grid">
            <a href="classes/index.php" class="admin-card">
                <div class="admin-card-icon"><i class="fas fa-chalkboard"></i></div>
                <div class="admin-card-title">Gestion des classes</div>
                <div class="admin-card-stat"><?= $totalClasses ?> classes</div>
            </a>
            <a href="classes/affectations.php" class="admin-card">
                <div class="admin-card-icon"><i class="fas fa-project-diagram"></i></div>
                <div class="admin-card-title">Affectations professeurs</div>
                <div class="admin-card-stat">Matrice profs × classes</div>
            </a>
        </div>
    </div>

    <!-- ══════ MESSAGERIE ══════ -->
    <div class="admin-cards-section">
        <div class="admin-cards-section-title"><i class="fas fa-envelope"></i> Messagerie</div>
        <div class="admin-cards-grid">
            <a href="messagerie/moderation.php" class="admin-card">
                <div class="admin-card-icon"><i class="fas fa-shield-alt"></i></div>
                <div class="admin-card-title">Modération</div>
                <div class="admin-card-stat">Messages signalés</div>
            </a>
            <a href="messagerie/conversations.php" class="admin-card">
                <div class="admin-card-icon"><i class="fas fa-comments"></i></div>
                <div class="admin-card-title">Conversations</div>
                <div class="admin-card-stat"><?= $counts['messages_24h'] ?> msg (24h)</div>
            </a>
            <a href="messagerie/annonces.php" class="admin-card">
                <div class="admin-card-icon"><i class="fas fa-bullhorn"></i></div>
                <div class="admin-card-title">Annonces globales</div>
                <div class="admin-card-stat">Diffuser un message</div>
            </a>
        </div>
    </div>

    <!-- ══════ ÉTABLISSEMENT ══════ -->
    <div class="admin-cards-section">
        <div class="admin-cards-section-title"><i class="fas fa-school"></i> Établissement</div>
        <div class="admin-cards-grid">
            <a href="etablissement/info.php" class="admin-card">
                <div class="admin-card-icon"><i class="fas fa-school"></i></div>
                <div class="admin-card-title">Informations générales</div>
                <div class="admin-card-stat">Nom, adresse, contact</div>
            </a>
            <a href="etablissement/matieres.php" class="admin-card">
                <div class="admin-card-icon"><i class="fas fa-palette"></i></div>
                <div class="admin-card-title">Matières & Coefficients</div>
                <div class="admin-card-stat">Configurer les matières</div>
            </a>
            <a href="etablissement/periodes.php" class="admin-card">
                <div class="admin-card-icon"><i class="fas fa-calendar-alt"></i></div>
                <div class="admin-card-title">Périodes scolaires</div>
                <div class="admin-card-stat">Trimestres / semestres</div>
            </a>
            <a href="etablissement/evenements.php" class="admin-card">
                <div class="admin-card-icon"><i class="fas fa-calendar-check"></i></div>
                <div class="admin-card-title">Événements</div>
                <div class="admin-card-stat">Gérer les événements</div>
            </a>
        </div>
    </div>

    <!-- ══════ SYSTÈME ══════ -->
    <div class="admin-cards-section">
        <div class="admin-cards-section-title"><i class="fas fa-cog"></i> Système</div>
        <div class="admin-cards-grid">
            <a href="systeme/audit.php" class="admin-card">
                <div class="admin-card-icon"><i class="fas fa-history"></i></div>
                <div class="admin-card-title">Journal d'audit</div>
                <div class="admin-card-stat">Traçabilité complète</div>
            </a>
            <a href="systeme/stats.php" class="admin-card">
                <div class="admin-card-icon"><i class="fas fa-chart-bar"></i></div>
                <div class="admin-card-title">Statistiques avancées</div>
                <div class="admin-card-stat">Graphiques & KPI</div>
            </a>
        </div>
    </div>

    <!-- ══════ ACTIVITÉ RÉCENTE ══════ -->
    <div class="dashboard-row">
        <div class="admin-activity">
            <div class="admin-activity-header"><i class="fas fa-clock"></i> Activité récente</div>
            <?php if (empty($recentLogins)): ?>
                <div class="admin-activity-item" style="color:#999;font-size:14px">Aucune connexion récente.</div>
            <?php else: ?>
                <?php foreach ($recentLogins as $login): ?>
                <div class="admin-activity-item">
                    <span class="admin-activity-time"><?= date('H:i', strtotime($login['last_login'])) ?></span>
                    <span><strong><?= htmlspecialchars($login['identifiant']) ?></strong></span>
                    <span style="margin-left:8px"><span class="activity-type"><?= htmlspecialchars(getProfilLabel($login['type'])) ?></span></span>
                    <span style="margin-left:auto;font-size:12px;color:#999"><?= date('d/m', strtotime($login['last_login'])) ?></span>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="admin-activity">
            <div class="admin-activity-header"><i class="fas fa-envelope"></i> Messages récents</div>
            <?php if (empty($recentMessages)): ?>
                <div class="admin-activity-item" style="color:#999;font-size:14px">Aucun message récent.</div>
            <?php else: ?>
                <?php foreach ($recentMessages as $msg): ?>
                <div class="admin-activity-item">
                    <span class="admin-activity-time"><?= date('H:i', strtotime($msg['created_at'])) ?></span>
                    <span><span class="activity-type"><?= htmlspecialchars(getProfilLabel($msg['sender_type'])) ?></span></span>
                    <span class="msg-preview" style="margin-left:8px"><?= htmlspecialchars(mb_substr(strip_tags($msg['body']), 0, 60)) ?>…</span>
                    <span style="margin-left:auto;font-size:12px;color:#999"><?= date('d/m', strtotime($msg['created_at'])) ?></span>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
