<?php
/**
 * M29 – Bibliothèque — Détail livre + emprunt
 */
$pageTitle = 'Détail livre';
require_once __DIR__ . '/includes/header.php';

$id = (int)($_GET['id'] ?? 0);
$livre = $biblioService->getLivre($id);
if (!$livre) { header('Location: catalogue.php'); exit; }

$isGestionnaire = isAdmin() || isPersonnelVS();

// Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCSRFToken()) {
    $action = $_POST['action'] ?? '';
    if ($action === 'emprunter') {
        try {
            $biblioService->emprunter($id, getUserId(), getUserRole());
            $_SESSION['success_message'] = 'Emprunt enregistré. Retour prévu le ' . date('d/m/Y', strtotime('+21 days'));
        } catch (RuntimeException $e) {
            $_SESSION['error_message'] = $e->getMessage();
        }
    } elseif ($action === 'supprimer' && $isGestionnaire) {
        $biblioService->supprimerLivre($id);
        header('Location: catalogue.php');
        exit;
    }
    header('Location: livre.php?id=' . $id);
    exit;
}

$cats = BibliothequeService::categories();
?>

<div class="content-wrapper">
    <div class="content-header">
        <h1><i class="fas fa-book"></i> <?= htmlspecialchars($livre['titre']) ?></h1>
        <a href="catalogue.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Catalogue</a>
    </div>

    <?php if (!empty($_SESSION['success_message'])): ?>
        <div class="alert alert-success"><?= $_SESSION['success_message'] ?></div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>
    <?php if (!empty($_SESSION['error_message'])): ?>
        <div class="alert alert-danger"><?= $_SESSION['error_message'] ?></div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>

    <div class="livre-detail">
        <div class="card">
            <div class="card-body">
                <div class="detail-grid">
                    <div class="detail-item"><label>Titre</label><span><?= htmlspecialchars($livre['titre']) ?></span></div>
                    <div class="detail-item"><label>Auteur</label><span><?= htmlspecialchars($livre['auteur'] ?: '—') ?></span></div>
                    <div class="detail-item"><label>ISBN</label><span><?= htmlspecialchars($livre['isbn'] ?: '—') ?></span></div>
                    <div class="detail-item"><label>Éditeur</label><span><?= htmlspecialchars($livre['editeur'] ?: '—') ?></span></div>
                    <div class="detail-item"><label>Année</label><span><?= $livre['annee_publication'] ?: '—' ?></span></div>
                    <div class="detail-item"><label>Catégorie</label><span><?= $cats[$livre['categorie']] ?? $livre['categorie'] ?></span></div>
                    <div class="detail-item"><label>Emplacement</label><span><?= htmlspecialchars($livre['emplacement'] ?: '—') ?></span></div>
                    <div class="detail-item"><label>Exemplaires</label><span><?= $livre['exemplaires_disponibles'] ?> / <?= $livre['exemplaires_total'] ?></span></div>
                </div>
                <?php if ($livre['description']): ?>
                <div class="livre-description"><h3>Description</h3><p><?= nl2br(htmlspecialchars($livre['description'])) ?></p></div>
                <?php endif; ?>

                <div class="livre-detail-actions">
                    <?php if ($livre['exemplaires_disponibles'] > 0 && (isEleve() || isProfesseur())): ?>
                    <form method="post" style="display:inline;">
                        <?= csrfField() ?>
                        <button name="action" value="emprunter" class="btn btn-primary"><i class="fas fa-hand-holding"></i> Emprunter</button>
                    </form>
                    <?php endif; ?>
                    <?php if ($isGestionnaire): ?>
                    <a href="ajouter.php?edit=<?= $id ?>" class="btn btn-outline"><i class="fas fa-edit"></i> Modifier</a>
                    <form method="post" style="display:inline;">
                        <?= csrfField() ?>
                        <button name="action" value="supprimer" class="btn btn-danger" onclick="return confirm('Supprimer ?')"><i class="fas fa-trash"></i></button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
