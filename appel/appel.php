<?php
/**
 * appel.php — Page principale du module Appel / Présence (M04).
 *
 * - Professeur : sélectionne un cours et fait l'appel
 * - Admin/Vie scolaire : consulte/gère les appels du jour
 */
ob_start();

require_once __DIR__ . '/../API/core.php';
require_once __DIR__ . '/includes/AppelService.php';

$pdo = getPDO();
requireAuth();

$user      = getCurrentUser();
$user_role = getUserRole();
$user_fullname = getUserFullName();
$user_initials = getUserInitials();

if (!isAdmin() && !isVieScolaire() && !isTeacher()) {
    redirect('/accueil/accueil.php');
}

$service = new AppelService($pdo);
$date = $_GET['date'] ?? date('Y-m-d');
$appelId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$success = '';
$errors = [];

// --- Traitement POST : sauvegarder un appel ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken()) {
        $errors[] = 'Token CSRF invalide.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'creer_appel') {
            $classeId = (int)($_POST['classe_id'] ?? 0);
            $matiereId = !empty($_POST['matiere_id']) ? (int)$_POST['matiere_id'] : null;
            $heureDebut = $_POST['heure_debut'] ?? date('H:i:s');
            $heureFin = $_POST['heure_fin'] ?? date('H:i:s', strtotime('+1 hour'));

            if ($classeId > 0) {
                $appelId = $service->createAppel([
                    'classe_id'     => $classeId,
                    'professeur_id' => $user['id'],
                    'matiere_id'    => $matiereId,
                    'date_appel'    => $date,
                    'heure_debut'   => $heureDebut,
                    'heure_fin'     => $heureFin,
                    'type_appel'    => 'cours',
                ]);
                header("Location: appel.php?id={$appelId}&date={$date}");
                exit;
            } else {
                $errors[] = 'Veuillez sélectionner une classe.';
            }
        }

        if ($action === 'sauvegarder') {
            $appelId = (int)($_POST['appel_id'] ?? 0);
            $statuts = $_POST['eleves'] ?? [];
            if ($appelId > 0 && !empty($statuts)) {
                $service->sauvegarderAppel($appelId, $statuts);
                $success = 'Appel enregistré avec succès.';
            }
        }

        if ($action === 'valider') {
            $appelId = (int)($_POST['appel_id'] ?? 0);
            if ($appelId > 0) {
                $service->validerAppel($appelId);
                $success = 'Appel validé. Les absences et retards ont été enregistrés.';
            }
        }

        if ($action === 'generer_edt') {
            $generes = $service->genererAppelsDepuisEDT($user['id'], $date);
            if (!empty($generes)) {
                $success = count($generes) . ' appel(s) créé(s) depuis l\'emploi du temps.';
            } else {
                $success = 'Aucun nouveau cours à créer (appels déjà existants ou pas de cours aujourd\'hui).';
            }
        }
    }
}

// --- Charger les données ---
$appel = null;
$eleves = [];

if ($appelId > 0) {
    $appel = $service->getAppel($appelId);
    $eleves = $service->getAppelEleves($appelId);
}

// Liste des appels du jour
if (isAdmin() || isVieScolaire()) {
    $classeFilter = isset($_GET['classe']) ? (int)$_GET['classe'] : null;
    $appelsJour = $service->getAppelsJour($date, $classeFilter);
} else {
    $appelsJour = $service->getAppelsProfesseur($user['id'], $date);
}

$classes = $service->getClasses();
$matieres = $service->getMatieres();

$pageTitle = 'Appel / Présence';
$currentPage = 'appel';
$pageSubtitle = 'Date : ' . date('d/m/Y', strtotime($date));

include 'includes/header.php';
?>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
    <ul class="mb-0">
        <?php foreach ($errors as $err): ?>
            <li><?= htmlspecialchars($err) ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<?php if ($success): ?>
<div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<!-- Navigation date -->
<div class="date-nav">
    <a href="?date=<?= date('Y-m-d', strtotime($date . ' -1 day')) ?>" class="btn btn-sm btn-secondary">
        <i class="fas fa-chevron-left"></i> Veille
    </a>
    <input type="date" value="<?= htmlspecialchars($date) ?>"
           onchange="window.location.href='?date='+this.value" class="form-control date-input">
    <a href="?date=<?= date('Y-m-d', strtotime($date . ' +1 day')) ?>" class="btn btn-sm btn-secondary">
        Lendemain <i class="fas fa-chevron-right"></i>
    </a>
    <a href="?date=<?= date('Y-m-d') ?>" class="btn btn-sm btn-primary">Aujourd'hui</a>
</div>

<?php if ($appel): ?>
<!-- ═══════ FEUILLE D'APPEL ═══════ -->
<div class="card">
    <div class="card-header">
        <h3>
            <i class="fas fa-clipboard-list"></i>
            Appel — <?= htmlspecialchars($appel['classe_nom']) ?>
            <?php if ($appel['matiere_nom']): ?>
                — <?= htmlspecialchars($appel['matiere_nom']) ?>
            <?php endif; ?>
            <span class="badge badge-<?= $appel['statut'] === 'valide' ? 'success' : ($appel['statut'] === 'en_cours' ? 'warning' : 'secondary') ?>">
                <?= ucfirst($appel['statut']) ?>
            </span>
        </h3>
        <span class="text-muted">
            <?= substr($appel['heure_debut'], 0, 5) ?> — <?= substr($appel['heure_fin'], 0, 5) ?>
        </span>
    </div>
    <div class="card-body">
        <?php if ($appel['statut'] !== 'valide'): ?>
        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="appel_id" value="<?= $appel['id'] ?>">

            <table class="table appel-table">
                <thead>
                    <tr>
                        <th>Élève</th>
                        <th class="text-center">Présent</th>
                        <th class="text-center">Absent</th>
                        <th class="text-center">Retard</th>
                        <th class="text-center">Dispensé</th>
                        <th>Motif</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($eleves as $i => $el): ?>
                    <tr class="appel-row" data-statut="<?= $el['statut'] ?>">
                        <td class="eleve-name">
                            <strong><?= htmlspecialchars($el['nom'] . ' ' . $el['prenom']) ?></strong>
                        </td>
                        <?php foreach (['present', 'absent', 'retard', 'dispense'] as $st): ?>
                        <td class="text-center">
                            <label class="appel-radio">
                                <input type="radio" name="eleves[<?= $el['eleve_id'] ?>][statut]"
                                       value="<?= $st ?>" <?= $el['statut'] === $st ? 'checked' : '' ?>
                                       class="statut-radio" data-eleve="<?= $el['eleve_id'] ?>">
                                <span class="appel-radio-mark appel-<?= $st ?>"></span>
                            </label>
                        </td>
                        <?php endforeach; ?>
                        <td>
                            <input type="text" name="eleves[<?= $el['eleve_id'] ?>][motif]"
                                   value="<?= htmlspecialchars($el['motif'] ?? '') ?>"
                                   class="form-control form-control-sm" placeholder="Motif...">
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="appel-summary">
                <span class="badge badge-success"><i class="fas fa-check"></i> Présents : <span id="count-present"><?= count(array_filter($eleves, fn($e) => $e['statut'] === 'present')) ?></span></span>
                <span class="badge badge-danger"><i class="fas fa-times"></i> Absents : <span id="count-absent"><?= count(array_filter($eleves, fn($e) => $e['statut'] === 'absent')) ?></span></span>
                <span class="badge badge-warning"><i class="fas fa-clock"></i> Retards : <span id="count-retard"><?= count(array_filter($eleves, fn($e) => $e['statut'] === 'retard')) ?></span></span>
            </div>

            <div class="form-actions">
                <button type="submit" name="action" value="sauvegarder" class="btn btn-primary">
                    <i class="fas fa-save"></i> Enregistrer
                </button>
                <button type="submit" name="action" value="valider" class="btn btn-success"
                        onclick="return confirm('Valider l\'appel ? Les absences et retards seront enregistrés.')">
                    <i class="fas fa-check-double"></i> Valider l'appel
                </button>
                <a href="appel.php?date=<?= $date ?>" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Retour
                </a>
            </div>
        </form>
        <?php else: ?>
        <!-- Appel validé (lecture seule) -->
        <table class="table appel-table">
            <thead>
                <tr><th>Élève</th><th class="text-center">Statut</th><th>Motif</th></tr>
            </thead>
            <tbody>
                <?php foreach ($eleves as $el): ?>
                <tr class="appel-row <?= $el['statut'] !== 'present' ? 'row-highlight-' . $el['statut'] : '' ?>">
                    <td><strong><?= htmlspecialchars($el['nom'] . ' ' . $el['prenom']) ?></strong></td>
                    <td class="text-center">
                        <span class="badge badge-<?= $el['statut'] === 'present' ? 'success' : ($el['statut'] === 'absent' ? 'danger' : 'warning') ?>">
                            <?= ucfirst($el['statut']) ?>
                        </span>
                    </td>
                    <td><?= htmlspecialchars($el['motif'] ?? '-') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <a href="appel.php?date=<?= $date ?>" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Retour</a>
        <?php endif; ?>
    </div>
</div>

<?php else: ?>
<!-- ═══════ LISTE DES APPELS DU JOUR ═══════ -->
<div class="welcome-banner">
    <div class="welcome-content">
        <h2><i class="fas fa-clipboard-check"></i> Appel du <?= date('d/m/Y', strtotime($date)) ?></h2>
        <p>
            <?php if (isTeacher()): ?>
                Pointez la présence de vos élèves pour chaque cours.
            <?php else: ?>
                Consultez les appels réalisés par les professeurs.
            <?php endif; ?>
        </p>
    </div>
</div>

<?php if (isTeacher()): ?>
<div class="appel-actions-bar">
    <form method="POST" class="inline-form">
        <?= csrfField() ?>
        <button type="submit" name="action" value="generer_edt" class="btn btn-primary">
            <i class="fas fa-magic"></i> Générer depuis l'emploi du temps
        </button>
    </form>

    <form method="POST" class="inline-form card card-body">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="creer_appel">
        <div class="form-row-inline">
            <select name="classe_id" class="form-control" required>
                <option value="">-- Classe --</option>
                <?php foreach ($classes as $cl): ?>
                    <option value="<?= $cl['id'] ?>"><?= htmlspecialchars($cl['nom']) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="matiere_id" class="form-control">
                <option value="">-- Matière --</option>
                <?php foreach ($matieres as $mat): ?>
                    <option value="<?= $mat['id'] ?>"><?= htmlspecialchars($mat['nom']) ?></option>
                <?php endforeach; ?>
            </select>
            <input type="time" name="heure_debut" class="form-control" value="<?= date('H:i') ?>">
            <input type="time" name="heure_fin" class="form-control" value="<?= date('H:i', strtotime('+1 hour')) ?>">
            <button type="submit" class="btn btn-success"><i class="fas fa-plus"></i> Créer</button>
        </div>
    </form>
</div>
<?php endif; ?>

<?php if (!empty($appelsJour)): ?>
<div class="appels-liste">
    <?php foreach ($appelsJour as $aj): ?>
    <a href="appel.php?id=<?= $aj['id'] ?>&date=<?= $date ?>" class="appel-card">
        <div class="appel-card-time">
            <?= substr($aj['heure_debut'], 0, 5) ?> — <?= substr($aj['heure_fin'], 0, 5) ?>
        </div>
        <div class="appel-card-info">
            <div class="appel-card-classe"><?= htmlspecialchars($aj['classe_nom']) ?></div>
            <?php if (!empty($aj['matiere_nom'])): ?>
                <div class="appel-card-matiere"><?= htmlspecialchars($aj['matiere_nom']) ?></div>
            <?php endif; ?>
            <?php if (!empty($aj['professeur_nom'])): ?>
                <div class="appel-card-prof"><i class="fas fa-user"></i> <?= htmlspecialchars($aj['professeur_nom']) ?></div>
            <?php endif; ?>
        </div>
        <div class="appel-card-stats">
            <?php if (isset($aj['nb_absents'])): ?>
                <span class="badge badge-danger" title="Absents"><?= $aj['nb_absents'] ?> abs.</span>
                <span class="badge badge-warning" title="Retards"><?= $aj['nb_retards'] ?> ret.</span>
            <?php endif; ?>
            <span class="badge badge-<?= $aj['statut'] === 'valide' ? 'success' : 'warning' ?>">
                <?= ucfirst($aj['statut']) ?>
            </span>
        </div>
    </a>
    <?php endforeach; ?>
</div>
<?php else: ?>
<div class="empty-state">
    <i class="fas fa-clipboard fa-3x"></i>
    <h3>Aucun appel pour cette date</h3>
    <?php if (isTeacher()): ?>
    <p>Utilisez le bouton "Générer depuis l'emploi du temps" ou créez un appel manuellement.</p>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php endif; ?>

<?php
ob_start();
?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Compteur en temps réel des statuts
    document.querySelectorAll('.statut-radio').forEach(function(radio) {
        radio.addEventListener('change', function() {
            var row = this.closest('.appel-row');
            row.setAttribute('data-statut', this.value);
            // Recompter
            var presents = document.querySelectorAll('.statut-radio[value="present"]:checked').length;
            var absents = document.querySelectorAll('.statut-radio[value="absent"]:checked').length;
            var retards = document.querySelectorAll('.statut-radio[value="retard"]:checked').length;
            document.getElementById('count-present').textContent = presents;
            document.getElementById('count-absent').textContent = absents;
            document.getElementById('count-retard').textContent = retards;
        });
    });
});
</script>
<?php
$extraScriptHtml = ob_get_clean();

include 'includes/footer.php';
?>
