<?php
/**
 * M34 – Support — Nouveau ticket
 */
$pageTitle = 'Nouveau ticket';
require_once __DIR__ . '/includes/header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCSRFToken()) {
    $sujet = trim($_POST['sujet'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $categorie = $_POST['categorie'] ?? 'technique';
    $priorite = $_POST['priorite'] ?? 'normale';

    if (empty($sujet) || empty($description)) {
        $error = 'Veuillez remplir tous les champs obligatoires.';
    } else {
        $supportService->creerTicket(getUserId(), getUserRole(), $sujet, $description, $categorie, $priorite);
        $_SESSION['success_message'] = 'Ticket créé avec succès. Nous vous répondrons dès que possible.';
        header('Location: tickets.php');
        exit;
    }
}
?>

<div class="content-wrapper">
    <div class="content-header">
        <h1><i class="fas fa-plus-circle"></i> Nouveau ticket</h1>
        <a href="tickets.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Mes tickets</a>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <form method="post">
                <?= csrfField() ?>
                <div class="form-group">
                    <label for="sujet">Sujet *</label>
                    <input type="text" name="sujet" id="sujet" class="form-control" required value="<?= htmlspecialchars($_POST['sujet'] ?? '') ?>" placeholder="Décrivez brièvement votre problème">
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="categorie">Catégorie</label>
                        <select name="categorie" id="categorie" class="form-control">
                            <?php foreach (SupportService::categoriesTicket() as $val => $label): ?>
                            <option value="<?= $val ?>"><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="priorite">Priorité</label>
                        <select name="priorite" id="priorite" class="form-control">
                            <option value="basse">Basse</option>
                            <option value="normale" selected>Normale</option>
                            <option value="haute">Haute</option>
                            <option value="urgente">Urgente</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label for="description">Description détaillée *</label>
                    <textarea name="description" id="description" class="form-control" rows="6" required placeholder="Décrivez votre problème en détail : que faisiez-vous, quel message d'erreur avez-vous vu, etc."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Envoyer</button>
                    <a href="tickets.php" class="btn btn-outline">Annuler</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
