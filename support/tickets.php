<?php
/**
 * M34 – Support — Mes tickets
 */
$pageTitle = 'Mes tickets';
require_once __DIR__ . '/includes/header.php';

$userId = getUserId();
$userType = getUserRole();

// Admin voit tous les tickets
if (isAdmin()) {
    $filtreStatut = $_GET['statut'] ?? '';
    $filters = $filtreStatut ? ['statut' => $filtreStatut] : [];
    $tickets = $supportService->getTousTickets($filters);
    $stats = $supportService->getStatsTickets();
} else {
    $tickets = $supportService->getTicketsUser($userId, $userType);
    $stats = null;
}
?>

<div class="content-wrapper">
    <div class="content-header">
        <h1><i class="fas fa-ticket-alt"></i> <?= isAdmin() ? 'Gestion des tickets' : 'Mes tickets' ?></h1>
        <a href="nouveau_ticket.php" class="btn btn-primary"><i class="fas fa-plus"></i> Nouveau ticket</a>
    </div>

    <?php if (!empty($_SESSION['success_message'])): ?>
        <div class="alert alert-success"><?= $_SESSION['success_message'] ?></div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>

    <!-- Stats admin -->
    <?php if ($stats): ?>
    <div class="ticket-stats">
        <div class="stat-card"><div class="stat-value"><?= $stats['total'] ?></div><div class="stat-label">Total</div></div>
        <div class="stat-card stat-info"><div class="stat-value"><?= $stats['ouverts'] ?></div><div class="stat-label">Ouverts</div></div>
        <div class="stat-card stat-warning"><div class="stat-value"><?= $stats['en_cours'] ?></div><div class="stat-label">En cours</div></div>
        <div class="stat-card stat-success"><div class="stat-value"><?= $stats['resolus'] ?></div><div class="stat-label">Résolus</div></div>
        <div class="stat-card stat-danger"><div class="stat-value"><?= $stats['urgents'] ?></div><div class="stat-label">Urgents</div></div>
    </div>
    <!-- Filtres admin -->
    <div class="filter-bar">
        <a href="tickets.php" class="filter-btn <?= empty($filtreStatut) ? 'active' : '' ?>">Tous</a>
        <a href="tickets.php?statut=ouvert" class="filter-btn <?= ($filtreStatut ?? '') === 'ouvert' ? 'active' : '' ?>">Ouverts</a>
        <a href="tickets.php?statut=en_cours" class="filter-btn <?= ($filtreStatut ?? '') === 'en_cours' ? 'active' : '' ?>">En cours</a>
        <a href="tickets.php?statut=resolu" class="filter-btn <?= ($filtreStatut ?? '') === 'resolu' ? 'active' : '' ?>">Résolus</a>
    </div>
    <?php endif; ?>

    <!-- Liste -->
    <?php if (empty($tickets)): ?>
        <div class="empty-state"><i class="fas fa-inbox"></i><p>Aucun ticket.</p></div>
    <?php else: ?>
    <div class="ticket-list">
        <?php foreach ($tickets as $t): ?>
        <div class="ticket-item">
            <div class="ticket-id">#<?= $t['id'] ?></div>
            <div class="ticket-info">
                <h3><?= htmlspecialchars($t['sujet']) ?></h3>
                <?php if (isAdmin() && !empty($t['nom_utilisateur'])): ?>
                <span class="ticket-user"><i class="fas fa-user"></i> <?= htmlspecialchars($t['nom_utilisateur']) ?> (<?= $t['user_type'] ?>)</span>
                <?php endif; ?>
                <div class="ticket-meta">
                    <span><?= SupportService::statutBadge($t['statut']) ?></span>
                    <span><?= SupportService::prioriteBadge($t['priorite']) ?></span>
                    <span class="ticket-cat"><?= SupportService::categoriesTicket()[$t['categorie']] ?? $t['categorie'] ?></span>
                    <span class="ticket-date"><?= formatDateTime($t['date_creation']) ?></span>
                </div>
            </div>
            <a href="voir_ticket.php?id=<?= $t['id'] ?>" class="btn btn-sm btn-outline"><i class="fas fa-eye"></i></a>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
