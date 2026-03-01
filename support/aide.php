<?php
/**
 * M34 – Support — FAQ / Centre d'aide
 */
$pageTitle = 'Centre d\'aide';
require_once __DIR__ . '/includes/header.php';

$categorie = $_GET['cat'] ?? '';
$recherche = trim($_GET['q'] ?? '');
$articles = $supportService->getFaqArticles($categorie ?: null, $recherche ?: null);
$categories = SupportService::categoriesFaq();

// Vote utile
if (isset($_GET['utile'])) {
    $supportService->voterUtile((int)$_GET['faq'], $_GET['utile'] === '1');
    header('Location: aide.php' . ($categorie ? "?cat=$categorie" : ''));
    exit;
}
?>

<div class="content-wrapper">
    <div class="content-header">
        <h1><i class="fas fa-question-circle"></i> Centre d'aide</h1>
        <a href="nouveau_ticket.php" class="btn btn-primary"><i class="fas fa-ticket-alt"></i> Ouvrir un ticket</a>
    </div>

    <!-- Recherche -->
    <div class="search-section">
        <form method="get" class="search-bar">
            <input type="text" name="q" class="form-control" placeholder="Rechercher dans la FAQ..." value="<?= htmlspecialchars($recherche) ?>">
            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
        </form>
    </div>

    <!-- Catégories -->
    <div class="faq-categories">
        <a href="aide.php" class="cat-chip <?= !$categorie ? 'active' : '' ?>">Toutes</a>
        <?php foreach ($categories as $key => $label): ?>
        <a href="aide.php?cat=<?= $key ?>" class="cat-chip <?= $categorie === $key ? 'active' : '' ?>"><?= $label ?></a>
        <?php endforeach; ?>
    </div>

    <!-- Articles FAQ -->
    <div class="faq-list">
        <?php if (empty($articles)): ?>
            <div class="empty-state">
                <i class="fas fa-search"></i>
                <p>Aucun article trouvé.</p>
            </div>
        <?php else: ?>
            <?php foreach ($articles as $art): ?>
            <details class="faq-item" <?= isset($_GET['open']) && (int)$_GET['open'] === $art['id'] ? 'open' : '' ?>>
                <summary class="faq-question">
                    <i class="fas fa-chevron-right faq-chevron"></i>
                    <?= htmlspecialchars($art['question']) ?>
                    <span class="faq-cat-badge"><?= $categories[$art['categorie']] ?? $art['categorie'] ?></span>
                </summary>
                <div class="faq-answer">
                    <p><?= nl2br(htmlspecialchars($art['reponse'])) ?></p>
                    <div class="faq-feedback">
                        <span>Cet article vous a-t-il aidé ?</span>
                        <a href="?utile=1&faq=<?= $art['id'] ?><?= $categorie ? "&cat=$categorie" : '' ?>" class="feedback-btn"><i class="fas fa-thumbs-up"></i> Oui (<?= $art['utile_oui'] ?>)</a>
                        <a href="?utile=0&faq=<?= $art['id'] ?><?= $categorie ? "&cat=$categorie" : '' ?>" class="feedback-btn"><i class="fas fa-thumbs-down"></i> Non (<?= $art['utile_non'] ?>)</a>
                    </div>
                </div>
            </details>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Section contact -->
    <div class="help-footer">
        <div class="help-card">
            <i class="fas fa-envelope"></i>
            <h3>Toujours besoin d'aide ?</h3>
            <p>Si vous ne trouvez pas la réponse, ouvrez un ticket de support.</p>
            <a href="nouveau_ticket.php" class="btn btn-primary">Ouvrir un ticket</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
