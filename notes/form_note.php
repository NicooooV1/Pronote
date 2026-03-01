<?php
/**
 * Module Notes — Formulaire unifié ajout / modification d'une note.
 *
 * ?id=X  → mode édition (modifier la note #X)
 * sinon  → mode ajout   (saisie par lot pour une classe)
 *
 * Utilise NoteService pour toutes les opérations SQL.
 */
ob_start();
require_once __DIR__ . '/../API/core.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/NoteService.php';

requireAuth();
if (!canManageNotes()) {
    header('Location: notes.php');
    exit;
}

$user          = getCurrentUser();
$user_role     = getUserRole();
$user_fullname = getUserFullName();
$user_initials = getUserInitials();
$pdo           = getPDO();
$noteService   = new NoteService($pdo);

// ─── Détection du mode ───────────────────────────────────────────
$id     = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$isEdit = $id !== null && $id > 0;

$errors  = [];
$success = false;
$note    = null;
$csrf_token = generateCSRFToken();

// Données de référence
$classes  = $noteService->getClasses();
$matieres = $noteService->getMatieres();

// ═══════════════════════════════════════════════════════════════════
//  MODE ÉDITION
// ═══════════════════════════════════════════════════════════════════
if ($isEdit) {

    $profId = (isTeacher() && !isAdmin() && !isVieScolaire()) ? $user['id'] : null;
    $note = $noteService->getNoteById($id, $profId);

    if (!$note) {
        setFlashMessage('error', "Note non trouvée ou accès non autorisé.");
        header('Location: notes.php');
        exit;
    }

    // Traitement du formulaire de modification
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        validateCSRFToken($_POST['csrf_token'] ?? '');

        $data = [
            'note'        => filter_input(INPUT_POST, 'note', FILTER_VALIDATE_FLOAT),
            'coefficient' => filter_input(INPUT_POST, 'coefficient', FILTER_VALIDATE_FLOAT),
            'commentaire' => filter_input(INPUT_POST, 'commentaire', FILTER_SANITIZE_FULL_SPECIAL_CHARS),
            'date_note'   => filter_input(INPUT_POST, 'date_note', FILTER_SANITIZE_FULL_SPECIAL_CHARS),
            'trimestre'   => filter_input(INPUT_POST, 'trimestre', FILTER_VALIDATE_INT),
        ];

        if ($data['note'] === false || $data['note'] < 0 || $data['note'] > ($note['note_sur'] ?? 20)) {
            $errors[] = "La note doit être comprise entre 0 et " . ($note['note_sur'] ?? 20) . ".";
        }
        if ($data['coefficient'] === false || $data['coefficient'] <= 0 || $data['coefficient'] > 10) {
            $errors[] = "Le coefficient doit être compris entre 0.25 et 10.";
        }
        if ($data['trimestre'] === false || $data['trimestre'] < 1 || $data['trimestre'] > 3) {
            $errors[] = "Le trimestre doit être compris entre 1 et 3.";
        }
        if ($data['date_note'] && !validateDate($data['date_note'])) {
            $errors[] = "Date d'évaluation invalide.";
        }

        if (empty($errors)) {
            try {
                $noteService->updateNote($id, $data);

                if (function_exists('logSecurityEvent')) {
                    logSecurityEvent('note_modified', [
                        'note_id' => $id, 'modified_by' => $user['id'],
                        'old_value' => $note['note'], 'new_value' => $data['note'],
                    ]);
                }

                setFlashMessage('success', "Note modifiée avec succès.");
                header('Location: notes.php');
                exit;
            } catch (Exception $e) {
                error_log("Erreur modification note: " . $e->getMessage());
                $errors[] = "Erreur lors de la modification de la note.";
            }
        }
    }

    $pageTitle = 'Modifier la note';

// ═══════════════════════════════════════════════════════════════════
//  MODE AJOUT (saisie par lot)
// ═══════════════════════════════════════════════════════════════════
} else {

    // Traitement du formulaire d'ajout
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_notes') {
        validateCSRFToken($_POST['csrf_token'] ?? '');

        $id_matiere      = filter_input(INPUT_POST, 'id_matiere', FILTER_VALIDATE_INT);
        $date_note       = filter_input(INPUT_POST, 'date_note', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $trimestre       = filter_input(INPUT_POST, 'trimestre', FILTER_VALIDATE_INT);
        $coefficient     = filter_input(INPUT_POST, 'coefficient', FILTER_VALIDATE_FLOAT);
        $note_sur        = filter_input(INPUT_POST, 'note_sur', FILTER_VALIDATE_FLOAT);
        $type_evaluation = trim(filter_input(INPUT_POST, 'type_evaluation', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '');
        $notes_eleves    = $_POST['notes'] ?? [];
        $commentaires    = $_POST['commentaire'] ?? [];

        if (!$id_matiere)                                 $errors[] = "Matière invalide.";
        if (!$date_note)                                  $errors[] = "Date d'évaluation requise.";
        if (!$trimestre || $trimestre < 1 || $trimestre > 3) $errors[] = "Trimestre invalide.";
        if (!$coefficient || $coefficient <= 0)           $errors[] = "Coefficient invalide.";
        if (!$note_sur || $note_sur <= 0)                 $errors[] = "Barème invalide.";
        if (empty($type_evaluation))                      $errors[] = "Type d'évaluation requis.";

        $hasNote = false;
        foreach ($notes_eleves as $val) {
            if ($val !== '' && $val !== null) { $hasNote = true; break; }
        }
        if (!$hasNote) $errors[] = "Saisissez au moins une note.";

        if (empty($errors)) {
            try {
                $notesData = [];
                foreach ($notes_eleves as $id_eleve => $val) {
                    if ($val === '' || $val === null) continue;
                    $noteVal = floatval($val);
                    if ($noteVal < 0 || $noteVal > $note_sur) continue;
                    $notesData[] = [
                        'id_eleve'    => (int) $id_eleve,
                        'note'        => $noteVal,
                        'commentaire' => $commentaires[$id_eleve] ?? '',
                    ];
                }

                $inserted = $noteService->bulkInsert($notesData, [
                    'id_matiere'      => $id_matiere,
                    'id_professeur'   => $user['id'],
                    'note_sur'        => $note_sur,
                    'coefficient'     => $coefficient,
                    'type_evaluation' => $type_evaluation,
                    'trimestre'       => $trimestre,
                    'date_note'       => $date_note,
                ]);

                setFlashMessage('success', "$inserted note(s) enregistrée(s) avec succès.");
                header('Location: notes.php?trimestre=' . $trimestre);
                exit;
            } catch (Exception $e) {
                $errors[] = "Erreur lors de l'enregistrement : " . $e->getMessage();
            }
        }
    }

    // Charger les élèves si classe sélectionnée
    $selectedClasse  = $_GET['classe'] ?? ($_POST['classe'] ?? '');
    $selectedMatiere = $_GET['matiere'] ?? ($_POST['id_matiere'] ?? '');
    $eleves = !empty($selectedClasse) ? $noteService->getElevesParClasse($selectedClasse) : [];

    $pageTitle = 'Ajouter des notes';
}

// ─── Rendu ───────────────────────────────────────────────────────
include 'includes/header.php';
?>

                <?php if (!empty($errors)): ?>
                <div class="alert alert-danger" style="margin-bottom:20px;">
                    <ul style="margin:0; padding-left:20px;">
                        <?php foreach ($errors as $e): ?>
                        <li><?= htmlspecialchars($e) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>

<?php if ($isEdit): ?>
                <!-- ═══════════ FORMULAIRE ÉDITION ═══════════ -->
                <div style="background:white; border-radius:10px; padding:30px; box-shadow:0 2px 8px rgba(0,0,0,0.06); max-width:700px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                        <h2 style="font-size:1.1em; color:#2d3748; margin:0;">Modification de la note</h2>
                        <a href="notes.php" class="btn btn-secondary" style="font-size:13px;"><i class="fas fa-arrow-left"></i> Retour</a>
                    </div>

                    <form method="post" action="" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px; margin-bottom:15px;">
                            <div>
                                <label style="display:block; font-size:12px; font-weight:600; color:#4a5568; margin-bottom:5px;">Élève</label>
                                <input type="text" value="<?= htmlspecialchars(($note['prenom_eleve'] ?? '') . ' ' . ($note['nom_eleve'] ?? '')) ?>" readonly class="form-control" style="background:#f7fafc;">
                            </div>
                            <div>
                                <label style="display:block; font-size:12px; font-weight:600; color:#4a5568; margin-bottom:5px;">Matière</label>
                                <input type="text" value="<?= htmlspecialchars($note['nom_matiere'] ?? '') ?>" readonly class="form-control" style="background:#f7fafc;">
                            </div>
                        </div>

                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px; margin-bottom:15px;">
                            <div>
                                <label for="note" style="display:block; font-size:12px; font-weight:600; color:#4a5568; margin-bottom:5px;">Note * <span style="color:#a0aec0;">/<?= $note['note_sur'] ?? 20 ?></span></label>
                                <input type="number" id="note" name="note" step="0.25" min="0" max="<?= $note['note_sur'] ?? 20 ?>"
                                       value="<?= htmlspecialchars($note['note'] ?? '') ?>" required class="form-control">
                            </div>
                            <div>
                                <label for="coefficient" style="display:block; font-size:12px; font-weight:600; color:#4a5568; margin-bottom:5px;">Coefficient *</label>
                                <input type="number" id="coefficient" name="coefficient" step="0.25" min="0.25" max="10"
                                       value="<?= htmlspecialchars($note['coefficient'] ?? '1') ?>" required class="form-control">
                            </div>
                        </div>

                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px; margin-bottom:15px;">
                            <div>
                                <label for="date_note" style="display:block; font-size:12px; font-weight:600; color:#4a5568; margin-bottom:5px;">Date d'évaluation</label>
                                <input type="date" id="date_note" name="date_note"
                                       value="<?= htmlspecialchars($note['date_note'] ?? '') ?>" max="<?= date('Y-m-d') ?>" class="form-control">
                            </div>
                            <div>
                                <label for="trimestre" style="display:block; font-size:12px; font-weight:600; color:#4a5568; margin-bottom:5px;">Trimestre *</label>
                                <select id="trimestre" name="trimestre" required class="form-control">
                                    <option value="1" <?= ($note['trimestre'] ?? '') == '1' ? 'selected' : '' ?>>1er trimestre</option>
                                    <option value="2" <?= ($note['trimestre'] ?? '') == '2' ? 'selected' : '' ?>>2ème trimestre</option>
                                    <option value="3" <?= ($note['trimestre'] ?? '') == '3' ? 'selected' : '' ?>>3ème trimestre</option>
                                </select>
                            </div>
                        </div>

                        <div style="margin-bottom:20px;">
                            <label for="commentaire" style="display:block; font-size:12px; font-weight:600; color:#4a5568; margin-bottom:5px;">Commentaire</label>
                            <textarea id="commentaire" name="commentaire" rows="3" maxlength="500" class="form-control"><?= htmlspecialchars($note['commentaire'] ?? '') ?></textarea>
                            <small style="color:#a0aec0; font-size:11px;">Maximum 500 caractères</small>
                        </div>

                        <div style="display:flex; gap:10px; justify-content:flex-end; padding-top:15px; border-top:1px solid #edf2f7;">
                            <a href="notes.php" class="btn btn-secondary"><i class="fas fa-times"></i> Annuler</a>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Enregistrer</button>
                        </div>
                    </form>
                </div>

<?php elseif (empty($eleves)): ?>
                <!-- ═══════════ ÉTAPE 1 : SÉLECTION CLASSE / MATIÈRE ═══════════ -->
                <div style="background:white; border-radius:10px; padding:30px; box-shadow:0 2px 8px rgba(0,0,0,0.06); max-width:600px;">
                    <h2 style="font-size:1.1em; color:#2d3748; margin-bottom:20px;">Sélection de la classe et matière</h2>
                    <form method="get" action="">
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px; margin-bottom:20px;">
                            <div>
                                <label style="display:block; font-size:13px; font-weight:600; color:#4a5568; margin-bottom:6px;">Classe <span style="color:#e53e3e;">*</span></label>
                                <select name="classe" class="form-control" required>
                                    <option value="">Sélectionnez</option>
                                    <?php foreach ($classes as $c): ?>
                                    <option value="<?= htmlspecialchars($c) ?>" <?= $selectedClasse === $c ? 'selected' : '' ?>><?= htmlspecialchars($c) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label style="display:block; font-size:13px; font-weight:600; color:#4a5568; margin-bottom:6px;">Matière <span style="color:#e53e3e;">*</span></label>
                                <select name="matiere" class="form-control" required>
                                    <option value="">Sélectionnez</option>
                                    <?php foreach ($matieres as $m): ?>
                                    <option value="<?= $m['id'] ?>" <?= $selectedMatiere == $m['id'] ? 'selected' : '' ?>><?= htmlspecialchars($m['nom']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-arrow-right"></i> Charger les élèves</button>
                    </form>
                </div>

<?php else: ?>
                <!-- ═══════════ ÉTAPE 2 : SAISIE DES NOTES ═══════════ -->
                <div style="background:white; border-radius:10px; padding:30px; box-shadow:0 2px 8px rgba(0,0,0,0.06);">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                        <h2 style="font-size:1.1em; color:#2d3748; margin:0;">
                            Saisie des notes — <?= htmlspecialchars($selectedClasse) ?>
                            <span style="font-weight:400; color:#718096; font-size:0.9em;">(<?= count($eleves) ?> élèves)</span>
                        </h2>
                        <a href="form_note.php" class="btn btn-secondary" style="font-size:13px;"><i class="fas fa-arrow-left"></i> Changer de classe</a>
                    </div>

                    <form method="post" action="">
                        <input type="hidden" name="action" value="save_notes">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                        <input type="hidden" name="classe" value="<?= htmlspecialchars($selectedClasse) ?>">

                        <!-- Paramètres de l'évaluation -->
                        <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap:15px; margin-bottom:25px; padding-bottom:20px; border-bottom:1px solid #edf2f7;">
                            <div>
                                <label style="display:block; font-size:12px; font-weight:600; color:#4a5568; margin-bottom:5px;">Matière</label>
                                <select name="id_matiere" class="form-control" required>
                                    <?php foreach ($matieres as $m): ?>
                                    <option value="<?= $m['id'] ?>" <?= $selectedMatiere == $m['id'] ? 'selected' : '' ?>><?= htmlspecialchars($m['nom']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label style="display:block; font-size:12px; font-weight:600; color:#4a5568; margin-bottom:5px;">Type d'évaluation</label>
                                <input type="text" name="type_evaluation" class="form-control" value="<?= htmlspecialchars($_POST['type_evaluation'] ?? 'Contrôle') ?>" required placeholder="Ex: Contrôle, DS, DM...">
                            </div>
                            <div>
                                <label style="display:block; font-size:12px; font-weight:600; color:#4a5568; margin-bottom:5px;">Date</label>
                                <input type="date" name="date_note" class="form-control" value="<?= htmlspecialchars($_POST['date_note'] ?? date('Y-m-d')) ?>" required>
                            </div>
                            <div>
                                <label style="display:block; font-size:12px; font-weight:600; color:#4a5568; margin-bottom:5px;">Trimestre</label>
                                <select name="trimestre" class="form-control" required>
                                    <?php
                                    $currentTri = NoteService::getTrimestreCourant();
                                    $postTri = $_POST['trimestre'] ?? $currentTri;
                                    ?>
                                    <option value="1" <?= $postTri == 1 ? 'selected' : '' ?>>1er trimestre</option>
                                    <option value="2" <?= $postTri == 2 ? 'selected' : '' ?>>2ème trimestre</option>
                                    <option value="3" <?= $postTri == 3 ? 'selected' : '' ?>>3ème trimestre</option>
                                </select>
                            </div>
                            <div>
                                <label style="display:block; font-size:12px; font-weight:600; color:#4a5568; margin-bottom:5px;">Coefficient</label>
                                <input type="number" name="coefficient" class="form-control" min="0.25" max="10" step="0.25" value="<?= htmlspecialchars($_POST['coefficient'] ?? '1') ?>" required>
                            </div>
                            <div>
                                <label style="display:block; font-size:12px; font-weight:600; color:#4a5568; margin-bottom:5px;">Barème (note sur)</label>
                                <input type="number" name="note_sur" class="form-control" min="1" max="100" step="1" value="<?= htmlspecialchars($_POST['note_sur'] ?? '20') ?>" required>
                            </div>
                        </div>

                        <!-- Tableau des élèves -->
                        <table style="width:100%; border-collapse:collapse;">
                            <thead>
                                <tr style="background:#f7fafc;">
                                    <th style="padding:10px 15px; text-align:left; font-size:13px; color:#4a5568; font-weight:600; width:35%;">Élève</th>
                                    <th style="padding:10px 15px; text-align:center; font-size:13px; color:#4a5568; font-weight:600; width:15%;">Note</th>
                                    <th style="padding:10px 15px; text-align:left; font-size:13px; color:#4a5568; font-weight:600;">Commentaire</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($eleves as $el): ?>
                                <tr style="border-bottom:1px solid #edf2f7;">
                                    <td style="padding:10px 15px; font-size:14px;">
                                        <strong><?= htmlspecialchars($el['nom']) ?></strong> <?= htmlspecialchars($el['prenom']) ?>
                                    </td>
                                    <td style="padding:10px 15px; text-align:center;">
                                        <input type="number" name="notes[<?= $el['id'] ?>]" min="0" max="100" step="0.25" class="form-control" style="width:80px; margin:auto; text-align:center;"
                                            value="<?= htmlspecialchars($_POST['notes'][$el['id']] ?? '') ?>">
                                    </td>
                                    <td style="padding:10px 15px;">
                                        <input type="text" name="commentaire[<?= $el['id'] ?>]" class="form-control" placeholder="Optionnel"
                                            value="<?= htmlspecialchars($_POST['commentaire'][$el['id']] ?? '') ?>">
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                        <!-- Statistiques temps réel -->
                        <div id="live-stats" style="display:none; background:#f7fafc; border-radius:10px; padding:15px 20px; margin-top:20px;">
                            <h4 style="margin:0 0 10px; font-size:13px; color:#4a5568; font-weight:600;">
                                <i class="fas fa-chart-bar" style="margin-right:5px;"></i> Statistiques en temps réel
                            </h4>
                            <div style="display:grid; grid-template-columns:repeat(auto-fill,minmax(120px,1fr)); gap:12px; text-align:center;">
                                <div>
                                    <div id="stat-moyenne" style="font-size:20px; font-weight:700; color:#0f4c81;">—</div>
                                    <div style="font-size:11px; color:#718096;">Moyenne</div>
                                </div>
                                <div>
                                    <div id="stat-min" style="font-size:20px; font-weight:700; color:#e53e3e;">—</div>
                                    <div style="font-size:11px; color:#718096;">Min</div>
                                </div>
                                <div>
                                    <div id="stat-max" style="font-size:20px; font-weight:700; color:#38a169;">—</div>
                                    <div style="font-size:11px; color:#718096;">Max</div>
                                </div>
                                <div>
                                    <div id="stat-mediane" style="font-size:20px; font-weight:700; color:#667eea;">—</div>
                                    <div style="font-size:11px; color:#718096;">Médiane</div>
                                </div>
                                <div>
                                    <div id="stat-nb" style="font-size:20px; font-weight:700; color:#4a5568;">0</div>
                                    <div style="font-size:11px; color:#718096;">Saisies</div>
                                </div>
                            </div>
                        </div>

                        <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:20px; padding-top:15px; border-top:1px solid #edf2f7;">
                            <a href="notes.php" class="btn btn-secondary">Annuler</a>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Enregistrer les notes</button>
                        </div>
                    </form>
                </div>
<?php endif; ?>

<?php
// JavaScript pour le mode ajout : stats temps réel + adaptation barème
if (!$isEdit && !empty($eleves)):
    ob_start();
?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const noteInputs  = document.querySelectorAll('input[name^="notes["]');
    const noteSurInput = document.querySelector('input[name="note_sur"]');
    const statsBox     = document.getElementById('live-stats');

    // ── Adaptation automatique du barème (UX 4.1) ──
    if (noteSurInput) {
        noteSurInput.addEventListener('change', function() {
            const max = parseFloat(this.value) || 20;
            noteInputs.forEach(function(input) {
                input.max = max;
                input.placeholder = '/ ' + max;
            });
            updateStats();
        });
    }

    // ── Statistiques temps réel (FEAT-6) ──
    function updateStats() {
        const noteSur = parseFloat(noteSurInput ? noteSurInput.value : 20) || 20;
        const values = [];
        noteInputs.forEach(function(input) {
            const v = parseFloat(input.value);
            if (!isNaN(v) && v >= 0 && v <= noteSur) {
                values.push(v / noteSur * 20); // normaliser sur 20
            }
        });

        if (values.length === 0) {
            statsBox.style.display = 'none';
            return;
        }
        statsBox.style.display = 'block';

        values.sort(function(a, b) { return a - b; });
        var sum = values.reduce(function(a, b) { return a + b; }, 0);
        var moyenne = sum / values.length;
        var min = values[0];
        var max = values[values.length - 1];
        var mid = Math.floor(values.length / 2);
        var mediane = values.length % 2 === 0
            ? (values[mid - 1] + values[mid]) / 2
            : values[mid];

        document.getElementById('stat-moyenne').textContent = moyenne.toFixed(2);
        document.getElementById('stat-min').textContent     = min.toFixed(2);
        document.getElementById('stat-max').textContent     = max.toFixed(2);
        document.getElementById('stat-mediane').textContent  = mediane.toFixed(2);
        document.getElementById('stat-nb').textContent       = values.length;
    }

    noteInputs.forEach(function(input) {
        input.addEventListener('input', updateStats);
    });
});
</script>
<?php
    $extraScriptHtml = ob_get_clean();
endif;

include 'includes/footer.php';
ob_end_flush();
?>
