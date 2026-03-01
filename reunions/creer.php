<?php
/**
 * M14 – Réunions — Créer une réunion
 */
if (!isAdmin() && !isTeacher() && !isVieScolaire()) { header('Location: reunions.php'); exit; }

$pageTitle = 'Planifier une réunion';
require_once __DIR__ . '/includes/header.php';

$classes = $reunionService->getClasses();
$professeurs = $reunionService->getProfesseurs();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCSRFToken()) {
    $data = [
        'titre' => trim($_POST['titre'] ?? ''),
        'description' => trim($_POST['description'] ?? ''),
        'type' => $_POST['type'] ?? 'parents_profs',
        'date_debut' => $_POST['date_debut'] ?? '',
        'date_fin' => $_POST['date_fin'] ?? '',
        'lieu' => trim($_POST['lieu'] ?? ''),
        'classe_id' => (int)($_POST['classe_id'] ?? 0),
        'organisateur_id' => getUserId(),
        'organisateur_type' => getUserRole(),
    ];

    if (empty($data['titre']) || empty($data['date_debut']) || empty($data['date_fin'])) {
        $error = 'Veuillez remplir tous les champs obligatoires.';
    } else {
        $reunionId = $reunionService->creerReunion($data);

        // Générer créneaux si parents-profs
        if ($data['type'] === 'parents_profs' && !empty($_POST['prof_ids'])) {
            $duree = (int)($_POST['duree_creneau'] ?? 15);
            $hDebut = $_POST['creneau_debut'] ?? '17:00';
            $hFin = $_POST['creneau_fin'] ?? '20:00';
            foreach ($_POST['prof_ids'] as $profId) {
                $reunionService->genererCreneaux($reunionId, (int)$profId, $hDebut, $hFin, $duree, $data['lieu']);
            }
        }

        $_SESSION['success_message'] = 'Réunion créée avec succès.';
        header('Location: detail.php?id=' . $reunionId);
        exit;
    }
}
?>

<div class="content-wrapper">
    <div class="content-header">
        <h1><i class="fas fa-plus-circle"></i> Planifier une réunion</h1>
        <a href="reunions.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Retour</a>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <form method="post" id="formReunion">
                <?= csrfField() ?>

                <div class="form-grid">
                    <div class="form-group full-width">
                        <label for="titre">Titre *</label>
                        <input type="text" name="titre" id="titre" class="form-control" required value="<?= htmlspecialchars($_POST['titre'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label for="type">Type *</label>
                        <select name="type" id="type" class="form-control" required>
                            <?php foreach (ReunionService::typesReunion() as $val => $label): ?>
                            <option value="<?= $val ?>"><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="classe_id">Classe</label>
                        <select name="classe_id" id="classe_id" class="form-control">
                            <option value="">— Aucune —</option>
                            <?php foreach ($classes as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nom']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="date_debut">Date/heure début *</label>
                        <input type="datetime-local" name="date_debut" id="date_debut" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="date_fin">Date/heure fin *</label>
                        <input type="datetime-local" name="date_fin" id="date_fin" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label for="lieu">Lieu</label>
                        <input type="text" name="lieu" id="lieu" class="form-control" placeholder="Salle, bâtiment...">
                    </div>

                    <div class="form-group full-width">
                        <label for="description">Description</label>
                        <textarea name="description" id="description" class="form-control" rows="3"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                    </div>
                </div>

                <!-- Section créneaux parents-profs -->
                <div id="section-creneaux" style="display:none; margin-top: 2rem;">
                    <h3><i class="fas fa-clock"></i> Créneaux de rendez-vous</h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Durée par créneau (min)</label>
                            <input type="number" name="duree_creneau" class="form-control" value="15" min="5" max="60">
                        </div>
                        <div class="form-group">
                            <label>Heure début</label>
                            <input type="time" name="creneau_debut" class="form-control" value="17:00">
                        </div>
                        <div class="form-group">
                            <label>Heure fin</label>
                            <input type="time" name="creneau_fin" class="form-control" value="20:00">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Professeurs participant</label>
                        <div class="checkbox-grid">
                            <?php foreach ($professeurs as $p): ?>
                            <label class="checkbox-item">
                                <input type="checkbox" name="prof_ids[]" value="<?= $p['id'] ?>">
                                <?= htmlspecialchars($p['prenom'] . ' ' . $p['nom'] . ' — ' . $p['matiere']) ?>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Créer la réunion</button>
                    <a href="reunions.php" class="btn btn-outline">Annuler</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('type').addEventListener('change', function() {
    document.getElementById('section-creneaux').style.display = this.value === 'parents_profs' ? 'block' : 'none';
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
