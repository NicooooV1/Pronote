<?php
/**
 * M35 – Archivage — Créer une archive
 */
$pageTitle = 'Nouvelle archive';
$activePage = 'creer';
require_once __DIR__ . '/includes/header.php';

$currentYear = date('Y') . '-' . (date('Y') + 1);
$types = ArchiveService::typesArchive();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCSRFToken()) {
    $annee = trim($_POST['annee'] ?? $currentYear);
    $typeArchive = $_POST['type'] ?? 'tout';

    try {
        if ($typeArchive === 'tout') {
            $resultats = $archiveService->archiverTout($annee);
            $_SESSION['success_message'] = 'Archivage complet effectué : ' . count($resultats) . ' types archivés.';
        } else {
            $method = 'archiver' . ucfirst($typeArchive);
            $archiveService->$method($annee);
            $_SESSION['success_message'] = "Archive « {$types[$typeArchive]} » créée pour $annee.";
        }
    } catch (Exception $e) {
        $error = 'Erreur lors de l\'archivage : ' . $e->getMessage();
    }

    if (empty($error)) {
        header('Location: archivage.php');
        exit;
    }
}
?>

<div class="content-wrapper">
    <div class="content-header">
        <h1><i class="fas fa-plus-circle"></i> Nouvelle archive</h1>
        <a href="archivage.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Retour</a>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <div class="archive-warning">
                <i class="fas fa-exclamation-triangle"></i>
                <p><strong>Attention :</strong> L'archivage crée une copie des données au format JSON. Les données originales ne sont pas supprimées. Vous pouvez verrouiller les archives pour empêcher leur suppression.</p>
            </div>

            <form method="post">
                <?= csrfField() ?>
                <div class="form-group">
                    <label for="annee">Année scolaire</label>
                    <input type="text" name="annee" id="annee" class="form-control" value="<?= htmlspecialchars($currentYear) ?>" placeholder="ex: 2024-2025">
                </div>

                <div class="form-group">
                    <label>Que voulez-vous archiver ?</label>
                    <div class="archive-type-grid">
                        <label class="archive-type-option">
                            <input type="radio" name="type" value="tout" checked>
                            <div class="type-card">
                                <i class="fas fa-layer-group"></i>
                                <span>Tout archiver</span>
                                <small>Notes, absences, bulletins, devoirs, incidents</small>
                            </div>
                        </label>
                        <?php foreach ($types as $k => $v): ?>
                        <label class="archive-type-option">
                            <input type="radio" name="type" value="<?= $k ?>">
                            <div class="type-card">
                                <i class="fas fa-<?= $k === 'notes' ? 'star' : ($k === 'absences' ? 'user-clock' : ($k === 'bulletins' ? 'file-alt' : ($k === 'devoirs' ? 'book' : 'exclamation-triangle'))) ?>"></i>
                                <span><?= $v ?></span>
                            </div>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary" onclick="return confirm('Lancer l\'archivage ?')"><i class="fas fa-archive"></i> Lancer l'archivage</button>
                    <a href="archivage.php" class="btn btn-outline">Annuler</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
