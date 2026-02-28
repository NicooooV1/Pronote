<?php
ob_start();
require_once __DIR__ . '/../API/core.php';

requireAuth();
if (!canManageNotes()) {
    header('Location: notes.php');
    exit;
}

$user = getCurrentUser();
$user_role = getUserRole();
$user_fullname = getUserFullName();
$user_initials = getUserInitials();
$pdo = getPDO();

// Charger les classes et matières depuis la BDD
$classes = [];
$matieres = [];
try {
    $stmt = $pdo->query("SELECT DISTINCT nom FROM classes WHERE actif = 1 ORDER BY nom");
    $classes = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {}
try {
    $stmt = $pdo->query("SELECT id, nom FROM matieres WHERE actif = 1 ORDER BY nom");
    $matieres = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// Traitement du formulaire (étape 2 : enregistrement des notes)
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    if ($_POST['action'] === 'save_notes') {
        validateCSRFToken($_POST['csrf_token'] ?? '');

        $id_matiere = filter_input(INPUT_POST, 'id_matiere', FILTER_VALIDATE_INT);
        $date_note = filter_input(INPUT_POST, 'date_note', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $trimestre = filter_input(INPUT_POST, 'trimestre', FILTER_VALIDATE_INT);
        $coefficient = filter_input(INPUT_POST, 'coefficient', FILTER_VALIDATE_FLOAT);
        $note_sur = filter_input(INPUT_POST, 'note_sur', FILTER_VALIDATE_FLOAT);
        $type_evaluation = trim(filter_input(INPUT_POST, 'type_evaluation', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '');
        $notes_eleves = $_POST['notes'] ?? [];
        $commentaires = $_POST['commentaire'] ?? [];

        // Validations
        if (!$id_matiere) $errors[] = "Matière invalide.";
        if (!$date_note) $errors[] = "Date d'évaluation requise.";
        if (!$trimestre || $trimestre < 1 || $trimestre > 3) $errors[] = "Trimestre invalide.";
        if (!$coefficient || $coefficient <= 0) $errors[] = "Coefficient invalide.";
        if (!$note_sur || $note_sur <= 0) $errors[] = "Barème invalide.";
        if (empty($type_evaluation)) $errors[] = "Type d'évaluation requis.";

        // Vérifier qu'au moins une note est saisie
        $hasNote = false;
        foreach ($notes_eleves as $val) {
            if ($val !== '' && $val !== null) { $hasNote = true; break; }
        }
        if (!$hasNote) $errors[] = "Saisissez au moins une note.";

        if (empty($errors)) {
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("
                    INSERT INTO notes (id_eleve, id_matiere, id_professeur, note, note_sur, coefficient, type_evaluation, date_note, commentaire, trimestre)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $inserted = 0;
                foreach ($notes_eleves as $id_eleve => $val) {
                    if ($val === '' || $val === null) continue;
                    $noteVal = floatval($val);
                    if ($noteVal < 0 || $noteVal > $note_sur) continue;
                    $comm = $commentaires[$id_eleve] ?? '';
                    $stmt->execute([
                        (int)$id_eleve, $id_matiere, $user['id'],
                        $noteVal, $note_sur, $coefficient,
                        $type_evaluation, $date_note, $comm, $trimestre
                    ]);
                    $inserted++;
                }
                $pdo->commit();
                $success = true;
                setFlashMessage('success', "$inserted note(s) enregistrée(s) avec succès.");
                header('Location: interface_notes.php?trimestre=' . $trimestre);
                exit;
            } catch (Exception $e) {
                $pdo->rollBack();
                $errors[] = "Erreur lors de l'enregistrement : " . $e->getMessage();
            }
        }
    }
}

// Si une classe est sélectionnée (étape 2), charger ses élèves
$selectedClasse = $_GET['classe'] ?? ($_POST['classe'] ?? '');
$selectedMatiere = $_GET['matiere'] ?? ($_POST['id_matiere'] ?? '');
$eleves = [];
if (!empty($selectedClasse)) {
    try {
        $stmt = $pdo->prepare("SELECT id, nom, prenom FROM eleves WHERE classe = ? AND actif = 1 ORDER BY nom, prenom");
        $stmt->execute([$selectedClasse]);
        $eleves = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {}
}

$csrf_token = generateCSRFToken();
$pageTitle = 'Ajouter des notes';
include 'includes/header.php';
?>

                <!-- Erreurs -->
                <?php if (!empty($errors)): ?>
                <div class="alert alert-danger" style="margin-bottom:20px;">
                    <ul style="margin:0; padding-left:20px;">
                        <?php foreach ($errors as $e): ?>
                        <li><?= htmlspecialchars($e) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>

                <?php if (empty($eleves)): ?>
                <!-- ========== ÉTAPE 1 : Sélection classe / matière ========== -->
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
                <!-- ========== ÉTAPE 2 : Saisie des notes ========== -->
                <div style="background:white; border-radius:10px; padding:30px; box-shadow:0 2px 8px rgba(0,0,0,0.06);">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                        <h2 style="font-size:1.1em; color:#2d3748; margin:0;">
                            Saisie des notes — <?= htmlspecialchars($selectedClasse) ?>
                            <span style="font-weight:400; color:#718096; font-size:0.9em;">(<?= count($eleves) ?> élèves)</span>
                        </h2>
                        <a href="ajouter_note.php" class="btn btn-secondary" style="font-size:13px;"><i class="fas fa-arrow-left"></i> Changer de classe</a>
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
                                    $mois = date('n');
                                    $currentTri = ($mois >= 9 ? 1 : ($mois <= 3 ? 2 : 3));
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

                        <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:20px; padding-top:15px; border-top:1px solid #edf2f7;">
                            <a href="interface_notes.php" class="btn btn-secondary">Annuler</a>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Enregistrer les notes</button>
                        </div>
                    </form>
                </div>
                <?php endif; ?>

<?php
include 'includes/footer.php';
ob_end_flush();
?>