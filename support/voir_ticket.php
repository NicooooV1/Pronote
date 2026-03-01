<?php
/**
 * M34 – Support — Voir un ticket
 */
$pageTitle = 'Détail ticket';
require_once __DIR__ . '/includes/header.php';

$id = (int)($_GET['id'] ?? 0);
$ticket = $supportService->getTicket($id);
if (!$ticket) { header('Location: tickets.php'); exit; }

// Vérifier accès (admin ou propriétaire)
if (!isAdmin() && ($ticket['user_id'] !== getUserId() || $ticket['user_type'] !== getUserRole())) {
    header('Location: tickets.php');
    exit;
}

// Réponse admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCSRFToken() && isAdmin()) {
    $action = $_POST['action'] ?? '';
    if ($action === 'repondre') {
        $supportService->repondre($id, trim($_POST['reponse']), getUserId());
        $_SESSION['success_message'] = 'Réponse envoyée.';
    } elseif ($action === 'statut') {
        $supportService->changerStatut($id, $_POST['statut']);
        $_SESSION['success_message'] = 'Statut mis à jour.';
    }
    header('Location: voir_ticket.php?id=' . $id);
    exit;
}
?>

<div class="content-wrapper">
    <div class="content-header">
        <h1><i class="fas fa-ticket-alt"></i> Ticket #<?= $id ?></h1>
        <a href="tickets.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Retour</a>
    </div>

    <?php if (!empty($_SESSION['success_message'])): ?>
        <div class="alert alert-success"><?= $_SESSION['success_message'] ?></div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>

    <div class="ticket-detail">
        <div class="card">
            <div class="card-header">
                <div class="ticket-detail-header">
                    <div>
                        <h2><?= htmlspecialchars($ticket['sujet']) ?></h2>
                        <div class="ticket-meta">
                            <?= SupportService::statutBadge($ticket['statut']) ?>
                            <?= SupportService::prioriteBadge($ticket['priorite']) ?>
                            <span><?= SupportService::categoriesTicket()[$ticket['categorie']] ?? $ticket['categorie'] ?></span>
                            <span>Créé le <?= formatDateTime($ticket['date_creation']) ?></span>
                        </div>
                    </div>
                    <?php if (isAdmin()): ?>
                    <form method="post" class="statut-form">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="statut">
                        <select name="statut" class="form-control form-control-sm" onchange="this.form.submit()">
                            <option value="ouvert" <?= $ticket['statut'] === 'ouvert' ? 'selected' : '' ?>>Ouvert</option>
                            <option value="en_cours" <?= $ticket['statut'] === 'en_cours' ? 'selected' : '' ?>>En cours</option>
                            <option value="resolu" <?= $ticket['statut'] === 'resolu' ? 'selected' : '' ?>>Résolu</option>
                            <option value="ferme" <?= $ticket['statut'] === 'ferme' ? 'selected' : '' ?>>Fermé</option>
                        </select>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body">
                <!-- Message original -->
                <div class="ticket-message ticket-question">
                    <div class="message-avatar"><i class="fas fa-user"></i></div>
                    <div class="message-body">
                        <div class="message-header">
                            <strong>Utilisateur</strong>
                            <span><?= formatDateTime($ticket['date_creation']) ?></span>
                        </div>
                        <p><?= nl2br(htmlspecialchars($ticket['description'])) ?></p>
                    </div>
                </div>

                <!-- Réponse -->
                <?php if ($ticket['reponse']): ?>
                <div class="ticket-message ticket-response">
                    <div class="message-avatar admin-avatar"><i class="fas fa-user-shield"></i></div>
                    <div class="message-body">
                        <div class="message-header">
                            <strong>Support</strong>
                            <span><?= formatDateTime($ticket['date_reponse']) ?></span>
                        </div>
                        <p><?= nl2br(htmlspecialchars($ticket['reponse'])) ?></p>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Formulaire réponse admin -->
                <?php if (isAdmin() && $ticket['statut'] !== 'ferme'): ?>
                <div class="ticket-reply">
                    <form method="post">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="repondre">
                        <div class="form-group">
                            <label>Répondre au ticket</label>
                            <textarea name="reponse" class="form-control" rows="4" required placeholder="Votre réponse..."><?= htmlspecialchars($ticket['reponse'] ?? '') ?></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-reply"></i> Répondre & marquer résolu</button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
