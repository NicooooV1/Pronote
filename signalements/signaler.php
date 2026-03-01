<?php
/**
 * M45 – Anti-harcèlement — Formulaire de signalement
 */
$pageTitle = 'Signaler un problème';
$activePage = 'signaler';
require_once __DIR__ . '/includes/header.php';

$types = SignalementService::typesSignalement();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCSRFToken()) {
    $data = [
        'auteur_id' => getUserId(),
        'auteur_type' => getUserRole(),
        'type' => $_POST['type'] ?? 'autre',
        'description' => trim($_POST['description'] ?? ''),
        'lieu' => trim($_POST['lieu'] ?? ''),
        'date_faits' => $_POST['date_faits'] ?: null,
        'personnes_impliquees' => trim($_POST['personnes_impliquees'] ?? ''),
        'temoins' => trim($_POST['temoins'] ?? ''),
        'anonyme' => isset($_POST['anonyme']),
        'urgence' => $_POST['urgence'] ?? 'normale',
    ];

    if (empty($data['description'])) {
        $error = 'Veuillez décrire la situation.';
    } else {
        $signalementService->creerSignalement($data);
        $_SESSION['success_message'] = 'Votre signalement a été enregistré. ' . ($data['anonyme'] ? 'Il a été envoyé de manière anonyme.' : 'Vous serez tenu informé du suivi.');
        header('Location: ' . (isAdmin() || isPersonnelVS() ? 'signalements.php' : 'mes_signalements.php'));
        exit;
    }
}
?>

<div class="content-wrapper">
    <div class="content-header">
        <h1><i class="fas fa-shield-alt"></i> Signaler un problème</h1>
    </div>

    <div class="info-banner">
        <i class="fas fa-info-circle"></i>
        <div>
            <strong>Votre signalement sera traité en toute confidentialité.</strong>
            <p>Vous pouvez signaler de manière anonyme. Les informations seront communiquées uniquement aux personnes habilitées à traiter ce type de situation.</p>
        </div>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <form method="post">
                <?= csrfField() ?>

                <div class="form-grid-2">
                    <div class="form-group">
                        <label for="type">Type de signalement *</label>
                        <select name="type" id="type" class="form-control" required>
                            <?php foreach ($types as $k => $v): ?>
                            <option value="<?= $k ?>"><?= $v ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="urgence">Niveau d'urgence</label>
                        <select name="urgence" id="urgence" class="form-control">
                            <option value="basse">Basse</option>
                            <option value="normale" selected>Normale</option>
                            <option value="haute">Haute</option>
                            <option value="critique">Critique — nécessite intervention immédiate</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="description">Description des faits *</label>
                    <textarea name="description" id="description" class="form-control" rows="5" required placeholder="Décrivez la situation le plus précisément possible : que s'est-il passé, quand, comment..."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                </div>

                <div class="form-grid-2">
                    <div class="form-group">
                        <label for="lieu">Lieu</label>
                        <input type="text" name="lieu" id="lieu" class="form-control" placeholder="ex: cour de récréation, salle 204..." value="<?= htmlspecialchars($_POST['lieu'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label for="date_faits">Date des faits</label>
                        <input type="date" name="date_faits" id="date_faits" class="form-control" value="<?= $_POST['date_faits'] ?? date('Y-m-d') ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="personnes_impliquees">Personne(s) impliquée(s)</label>
                    <textarea name="personnes_impliquees" id="personnes_impliquees" class="form-control" rows="2" placeholder="Noms ou descriptions des personnes concernées"><?= htmlspecialchars($_POST['personnes_impliquees'] ?? '') ?></textarea>
                </div>

                <div class="form-group">
                    <label for="temoins">Témoin(s)</label>
                    <textarea name="temoins" id="temoins" class="form-control" rows="2" placeholder="Noms des témoins éventuels"><?= htmlspecialchars($_POST['temoins'] ?? '') ?></textarea>
                </div>

                <div class="anonyme-section">
                    <label class="checkbox-label">
                        <input type="checkbox" name="anonyme" value="1" <?= isset($_POST['anonyme']) ? 'checked' : '' ?>>
                        <span class="checkmark"></span>
                        <div>
                            <strong>Signalement anonyme</strong>
                            <p>Votre identité ne sera pas communiquée. Attention : le suivi sera limité.</p>
                        </div>
                    </label>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-paper-plane"></i> Envoyer le signalement</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Numéros utiles -->
    <div class="help-numbers">
        <h3><i class="fas fa-phone-alt"></i> Numéros d'aide</h3>
        <div class="numbers-grid">
            <div class="number-card"><strong>3020</strong><span>Non au harcèlement</span></div>
            <div class="number-card"><strong>3018</strong><span>Net Écoute (cyberharcèlement)</span></div>
            <div class="number-card"><strong>119</strong><span>Enfance en danger</span></div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
