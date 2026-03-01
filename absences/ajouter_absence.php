<?php
/**
 * ajouter_absence.php — Formulaire d'ajout d'absence.
 * Utilise AbsenceRepository + AbsenceHelper.
 * Corrections: suppression error_log debug, sanitization propre.
 */
ob_start();

require_once __DIR__ . '/../API/core.php';
require_once __DIR__ . '/includes/AbsenceRepository.php';
require_once __DIR__ . '/includes/AbsenceHelper.php';

$pdo = getPDO();

if (!isLoggedIn() || !canManageAbsences()) {
    header('Location: ' . LOGIN_URL);
    exit;
}

$user          = getCurrentUser();
$user_fullname = getUserFullName();
$user_role     = getUserRole();
$user_initials = getUserInitials();

$repo = new AbsenceRepository($pdo);
$csrf_token = AbsenceHelper::generateCsrf();

$message = '';
$erreur  = '';

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!AbsenceHelper::verifyCsrf()) {
        $erreur = "Erreur de sécurité. Veuillez réessayer.";
    } else {
        if (empty($_POST['id_eleve']) || empty($_POST['date_debut']) || empty($_POST['heure_debut']) || 
            empty($_POST['date_fin']) || empty($_POST['heure_fin']) || empty($_POST['type_absence'])) {
            $erreur = "Veuillez remplir tous les champs obligatoires.";
        } else {
            $date_debut = AbsenceHelper::sanitize($_POST['date_debut']) . ' ' . AbsenceHelper::sanitize($_POST['heure_debut']) . ':00';
            $date_fin   = AbsenceHelper::sanitize($_POST['date_fin']) . ' ' . AbsenceHelper::sanitize($_POST['heure_fin']) . ':00';
            
            $type_absence = AbsenceHelper::sanitize($_POST['type_absence']);
            $motif = AbsenceHelper::sanitize($_POST['motif'] ?? '');
            
            if (!in_array($type_absence, AbsenceHelper::validTypes())) {
                $erreur = "Type d'absence invalide.";
            } elseif (!empty($motif) && !in_array($motif, AbsenceHelper::validMotifs())) {
                $erreur = "Motif invalide.";
            } elseif (strtotime($date_fin) <= strtotime($date_debut)) {
                $erreur = "La date/heure de fin doit être après la date/heure de début.";
            } else {
                $data = [
                    'id_eleve'      => intval($_POST['id_eleve']),
                    'date_debut'    => $date_debut,
                    'date_fin'      => $date_fin,
                    'type_absence'  => $type_absence,
                    'motif'         => !empty($motif) ? $motif : null,
                    'justifie'      => isset($_POST['justifie']),
                    'commentaire'   => !empty($_POST['commentaire']) ? AbsenceHelper::sanitize($_POST['commentaire']) : null,
                    'signale_par'   => $user_fullname
                ];
                
                $id_absence = $repo->create($data);
                
                if ($id_absence) {
                    $_SESSION['success_message'] = "L'absence a été ajoutée avec succès.";
                    header('Location: absences.php');
                    exit;
                } else {
                    $erreur = "Une erreur est survenue lors de l'ajout de l'absence.";
                }
            }
        }
    }
    // Regénérer le CSRF après une soumission
    $csrf_token = AbsenceHelper::generateCsrf();
}

// Récupérer la liste des élèves
$eleves = $repo->getAllEleves();

// Valeurs suggérées
$date_suggere        = AbsenceHelper::sanitizeDate($_GET['date'] ?? '') ?: date('Y-m-d');
$heure_debut_suggere = AbsenceHelper::sanitize($_GET['debut'] ?? '08:00');
$heure_fin_suggere   = AbsenceHelper::sanitize($_GET['fin'] ?? '09:00');
$id_eleve_suggere    = intval($_GET['eleve'] ?? 0);

$pageTitle   = 'Signaler une absence';
$currentPage = 'ajouter';
include 'includes/header.php';
?>
      
<div class="content-section">
    <?php if (!empty($message)): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <span><?= $message ?></span>
            <button class="alert-close"><i class="fas fa-times"></i></button>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($erreur)): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <span><?= $erreur ?></span>
            <button class="alert-close"><i class="fas fa-times"></i></button>
        </div>
    <?php endif; ?>
    
    <div class="form-container">
        <form method="post" action="ajouter_absence.php">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
            <div class="form-grid">
                <div class="form-group form-full">
                    <label for="id_eleve" class="form-label">Élève <span class="required">*</span></label>
                    <select name="id_eleve" id="id_eleve" class="form-control searchable-select" required>
                        <option value="">Sélectionner un élève</option>
                        <?php foreach ($eleves as $eleve): ?>
                            <option value="<?= $eleve['id'] ?>" <?= $id_eleve_suggere == $eleve['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($eleve['nom'] . ' ' . $eleve['prenom'] . ' (' . $eleve['classe'] . ')') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="date_debut" class="form-label">Date de début <span class="required">*</span></label>
                    <input type="date" name="date_debut" id="date_debut" value="<?= $date_suggere ?>" required max="<?= date('Y-m-d') ?>" class="form-control">
                </div>
                
                <div class="form-group">
                    <label for="heure_debut" class="form-label">Heure de début <span class="required">*</span></label>
                    <input type="time" name="heure_debut" id="heure_debut" value="<?= $heure_debut_suggere ?>" required class="form-control">
                </div>
                
                <div class="form-group">
                    <label for="date_fin" class="form-label">Date de fin <span class="required">*</span></label>
                    <input type="date" name="date_fin" id="date_fin" value="<?= $date_suggere ?>" required max="<?= date('Y-m-d') ?>" class="form-control">
                </div>
                
                <div class="form-group">
                    <label for="heure_fin" class="form-label">Heure de fin <span class="required">*</span></label>
                    <input type="time" name="heure_fin" id="heure_fin" value="<?= $heure_fin_suggere ?>" required class="form-control">
                </div>
                
                <div class="form-group">
                    <label for="type_absence" class="form-label">Type d'absence <span class="required">*</span></label>
                    <select name="type_absence" id="type_absence" required class="form-control">
                        <option value="">Sélectionner un type</option>
                        <?php foreach (AbsenceHelper::validTypes() as $t): ?>
                        <option value="<?= $t ?>"><?= AbsenceHelper::typeLabel($t) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="motif" class="form-label">Motif</label>
                    <select name="motif" id="motif" class="form-control">
                        <option value="">Sélectionner un motif</option>
                        <?php foreach (array_filter(AbsenceHelper::validMotifs()) as $m): ?>
                        <option value="<?= $m ?>"><?= AbsenceHelper::motifLabel($m) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group form-full">
                    <div class="checkbox-group">
                        <input type="checkbox" name="justifie" id="justifie" class="form-check">
                        <label for="justifie" class="form-check-label">Absence justifiée</label>
                    </div>
                </div>
                
                <div class="form-group form-full">
                    <label for="commentaire" class="form-label">Commentaire</label>
                    <textarea name="commentaire" id="commentaire" rows="4" class="form-control"></textarea>
                </div>
                
                <div class="form-actions form-full">
                    <a href="absences.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Annuler
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Enregistrer l'absence
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
    document.getElementById('date_debut').addEventListener('change', function() {
        document.getElementById('date_fin').value = this.value;
    });
    
    document.querySelector('form').addEventListener('submit', function(e) {
        const dateDebut = document.getElementById('date_debut').value;
        const dateFin = document.getElementById('date_fin').value;
        const heureDebut = document.getElementById('heure_debut').value;
        const heureFin = document.getElementById('heure_fin').value;
        
        const debutComplet = new Date(dateDebut + 'T' + heureDebut);
        const finComplet = new Date(dateFin + 'T' + heureFin);
        
        if (finComplet <= debutComplet) {
            alert("La date et l'heure de fin doivent être après la date et l'heure de début.");
            e.preventDefault();
        }
    });

    // Recherche dans le select élève
    (function() {
        const select = document.getElementById('id_eleve');
        if (!select) return;
        const wrapper = document.createElement('div');
        wrapper.className = 'searchable-select-wrapper';
        select.parentNode.insertBefore(wrapper, select);
        
        const input = document.createElement('input');
        input.type = 'text';
        input.className = 'form-control';
        input.placeholder = 'Rechercher un élève...';
        input.style.marginBottom = '5px';
        wrapper.appendChild(input);
        wrapper.appendChild(select);
        
        const options = Array.from(select.options);
        input.addEventListener('input', function() {
            const search = this.value.toLowerCase();
            options.forEach(opt => {
                if (opt.value === '') { opt.style.display = ''; return; }
                opt.style.display = opt.textContent.toLowerCase().includes(search) ? '' : 'none';
            });
        });
    })();
</script>

<?php
include 'includes/footer.php';
ob_end_flush();
