<?php
/**
 * M12 – Notifications — Liste des notifications
 */
$pageTitle = 'Mes notifications';
require_once __DIR__ . '/includes/header.php';

$userId = getUserId();
$userType = getUserRole();

// Actions POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCSRFToken()) {
    $action = $_POST['action'] ?? '';
    if ($action === 'marquer_lue') {
        $notifService->marquerLue((int)$_POST['id'], $userId, $userType);
    } elseif ($action === 'marquer_toutes_lues') {
        $notifService->marquerToutesLues($userId, $userType);
    } elseif ($action === 'supprimer') {
        $notifService->supprimer((int)$_POST['id'], $userId, $userType);
    }
    header('Location: notifications.php' . (!empty($_GET['filtre']) ? '?filtre=' . $_GET['filtre'] : ''));
    exit;
}

// Filtre
$filtre = $_GET['filtre'] ?? 'toutes';
$lu = null;
if ($filtre === 'non_lues') $lu = false;
elseif ($filtre === 'lues') $lu = true;

$notifications = $notifService->getNotifications($userId, $userType, 100, 0, $lu);
$stats = $notifService->getStats($userId, $userType);
?>

<div class="content-wrapper">
    <div class="content-header">
        <h1><i class="fas fa-bell"></i> Mes notifications</h1>
        <div class="header-actions">
            <?php if ((int)$stats['non_lues'] > 0): ?>
            <form method="post" style="display:inline">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="marquer_toutes_lues">
                <button type="submit" class="btn btn-outline"><i class="fas fa-check-double"></i> Tout marquer comme lu</button>
            </form>
            <?php endif; ?>
            <a href="preferences.php" class="btn btn-outline"><i class="fas fa-sliders-h"></i> Préférences</a>
        </div>
    </div>

    <!-- Stats rapides -->
    <div class="notif-stats">
        <div class="stat-card">
            <div class="stat-value"><?= (int)$stats['total'] ?></div>
            <div class="stat-label">Total</div>
        </div>
        <div class="stat-card stat-warning">
            <div class="stat-value"><?= (int)$stats['non_lues'] ?></div>
            <div class="stat-label">Non lues</div>
        </div>
        <div class="stat-card stat-danger">
            <div class="stat-value"><?= (int)$stats['urgentes'] ?></div>
            <div class="stat-label">Urgentes</div>
        </div>
    </div>

    <!-- Filtres -->
    <div class="notif-filters">
        <a href="?filtre=toutes" class="filter-btn <?= $filtre === 'toutes' ? 'active' : '' ?>">Toutes</a>
        <a href="?filtre=non_lues" class="filter-btn <?= $filtre === 'non_lues' ? 'active' : '' ?>">Non lues</a>
        <a href="?filtre=lues" class="filter-btn <?= $filtre === 'lues' ? 'active' : '' ?>">Lues</a>
    </div>

    <!-- Liste -->
    <div class="notif-list">
        <?php if (empty($notifications)): ?>
            <div class="empty-state">
                <i class="fas fa-bell-slash"></i>
                <p>Aucune notification<?= $filtre !== 'toutes' ? ' dans cette catégorie' : '' ?>.</p>
            </div>
        <?php else: ?>
            <?php foreach ($notifications as $notif): ?>
            <div class="notif-item <?= !$notif['lu'] ? 'notif-unread' : '' ?> notif-importance-<?= $notif['importance'] ?>">
                <div class="notif-icon">
                    <i class="fas <?= htmlspecialchars($notif['icone']) ?>"></i>
                </div>
                <div class="notif-content">
                    <div class="notif-header">
                        <span class="notif-titre"><?= htmlspecialchars($notif['titre']) ?></span>
                        <?= NotificationService::importanceBadge($notif['importance']) ?>
                    </div>
                    <?php if ($notif['contenu']): ?>
                        <p class="notif-text"><?= htmlspecialchars($notif['contenu']) ?></p>
                    <?php endif; ?>
                    <div class="notif-meta">
                        <span class="notif-date"><i class="fas fa-clock"></i> <?= formatDateTime($notif['date_creation']) ?></span>
                        <span class="notif-type"><?= htmlspecialchars(NotificationService::typesNotification()[$notif['type']] ?? $notif['type']) ?></span>
                    </div>
                </div>
                <div class="notif-actions">
                    <?php if ($notif['lien']): ?>
                        <a href="<?= htmlspecialchars($notif['lien']) ?>" class="btn btn-sm btn-primary" title="Voir"><i class="fas fa-external-link-alt"></i></a>
                    <?php endif; ?>
                    <?php if (!$notif['lu']): ?>
                    <form method="post" style="display:inline">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="marquer_lue">
                        <input type="hidden" name="id" value="<?= $notif['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline" title="Marquer comme lu"><i class="fas fa-check"></i></button>
                    </form>
                    <?php endif; ?>
                    <form method="post" style="display:inline" onsubmit="return confirm('Supprimer cette notification ?')">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="supprimer">
                        <input type="hidden" name="id" value="<?= $notif['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Supprimer"><i class="fas fa-trash"></i></button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
