<?php
/**
 * AJAX — Récupère le profil complet d'un utilisateur pour la modale
 */
require_once __DIR__ . '/../../API/core.php';
require_once __DIR__ . '/../includes/admin_functions.php';

requireAuth();
requireRole('administrateur');

$pdo = getPDO();
$userObj = new User($pdo);

$id = intval($_GET['id'] ?? 0);
$type = $_GET['type'] ?? '';

if ($id <= 0 || empty($type)) { echo '<p class="alert alert-danger">Paramètres invalides.</p>'; exit; }

$u = $userObj->getById($id, $type);
if (!$u) { echo '<p class="alert alert-danger">Utilisateur non trouvé.</p>'; exit; }

$csrf_token = $_SESSION['csrf_token'] ?? '';
?>
<div class="detail-grid">
    <span class="detail-label">Nom</span><span class="detail-value"><?= htmlspecialchars($u['nom']) ?></span>
    <span class="detail-label">Prénom</span><span class="detail-value"><?= htmlspecialchars($u['prenom']) ?></span>
    <span class="detail-label">Identifiant</span><span class="detail-value"><code><?= htmlspecialchars($u['identifiant']) ?></code></span>
    <span class="detail-label">Email</span><span class="detail-value"><?= htmlspecialchars($u['mail'] ?? '') ?></span>
    <span class="detail-label">Profil</span><span class="detail-value"><span class="badge-profil <?= getProfilBadgeClass($type) ?>"><?= getProfilLabel($type) ?></span></span>
    <?php if (isset($u['adresse'])): ?>
    <span class="detail-label">Adresse</span><span class="detail-value"><?= htmlspecialchars($u['adresse']) ?></span>
    <?php endif; ?>
    <?php if (isset($u['telephone'])): ?>
    <span class="detail-label">Téléphone</span><span class="detail-value"><?= htmlspecialchars($u['telephone']) ?></span>
    <?php endif; ?>
    <?php if ($type === 'eleve'): ?>
    <span class="detail-label">Classe</span><span class="detail-value"><?= htmlspecialchars($u['classe'] ?? '') ?></span>
    <span class="detail-label">Date naissance</span><span class="detail-value"><?= !empty($u['date_naissance']) ? date('d/m/Y', strtotime($u['date_naissance'])) : '-' ?></span>
    <span class="detail-label">Lieu naissance</span><span class="detail-value"><?= htmlspecialchars($u['lieu_naissance'] ?? '') ?></span>
    <?php endif; ?>
    <?php if ($type === 'professeur'): ?>
    <span class="detail-label">Matière</span><span class="detail-value"><?= htmlspecialchars($u['matiere'] ?? '') ?></span>
    <span class="detail-label">Prof. principal</span><span class="detail-value"><?= htmlspecialchars($u['professeur_principal'] ?? 'non') ?></span>
    <?php endif; ?>
    <?php if ($type === 'parent'): ?>
    <span class="detail-label">Métier</span><span class="detail-value"><?= htmlspecialchars($u['metier'] ?? '') ?></span>
    <span class="detail-label">Parent élève</span><span class="detail-value"><?= htmlspecialchars($u['est_parent_eleve'] ?? 'non') ?></span>
    <?php endif; ?>
    <?php if ($type === 'vie_scolaire'): ?>
    <span class="detail-label">CPE</span><span class="detail-value"><?= htmlspecialchars($u['est_CPE'] ?? 'non') ?></span>
    <span class="detail-label">Infirmerie</span><span class="detail-value"><?= htmlspecialchars($u['est_infirmerie'] ?? 'non') ?></span>
    <?php endif; ?>
    <span class="detail-label">Statut</span><span class="detail-value"><?= ($u['actif'] ?? 1) ? '<span class="status-active">Actif</span>' : '<span class="status-inactive">Inactif</span>' ?></span>
    <span class="detail-label">Créé le</span><span class="detail-value"><?= !empty($u['date_creation']) ? date('d/m/Y', strtotime($u['date_creation'])) : '-' ?></span>
    <span class="detail-label">Dernière connexion</span><span class="detail-value"><?= !empty($u['last_login']) ? date('d/m/Y H:i', strtotime($u['last_login'])) : '<em>Jamais</em>' ?></span>
    <span class="detail-label">Tentatives échouées</span><span class="detail-value"><?= intval($u['failed_login_attempts'] ?? 0) ?></span>
</div>

<!-- Onglets : Modifier / Notes / Absences -->
<div class="tab-buttons">
    <button class="tab-btn active" onclick="switchTab('edit')">Modifier</button>
    <?php if ($type === 'eleve'): ?>
    <button class="tab-btn" onclick="switchTab('notes')">Notes</button>
    <button class="tab-btn" onclick="switchTab('absences')">Absences</button>
    <?php endif; ?>
</div>

<!-- Onglet Modifier -->
<div class="tab-pane active" id="tab-edit">
    <form method="post" action="index.php">
        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
        <input type="hidden" name="action" value="edit_user">
        <input type="hidden" name="user_id" value="<?= $id ?>">
        <input type="hidden" name="user_type" value="<?= $type ?>">
        <div class="form-row">
            <div class="form-group"><label>Nom</label><input type="text" name="nom" value="<?= htmlspecialchars($u['nom']) ?>" required></div>
            <div class="form-group"><label>Prénom</label><input type="text" name="prenom" value="<?= htmlspecialchars($u['prenom']) ?>" required></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label>Email</label><input type="email" name="mail" value="<?= htmlspecialchars($u['mail'] ?? '') ?>" required></div>
            <div class="form-group"><label>Téléphone</label><input type="text" name="telephone" value="<?= htmlspecialchars($u['telephone'] ?? '') ?>"></div>
        </div>
        <?php if (isset($u['adresse'])): ?>
        <div class="form-row">
            <div class="form-group"><label>Adresse</label><input type="text" name="adresse" value="<?= htmlspecialchars($u['adresse'] ?? '') ?>"></div>
        </div>
        <?php endif; ?>
        <?php if ($type === 'eleve'): ?>
        <div class="form-row">
            <div class="form-group"><label>Classe</label><input type="text" name="classe" value="<?= htmlspecialchars($u['classe'] ?? '') ?>"></div>
            <div class="form-group"><label>Date naissance</label><input type="date" name="date_naissance" value="<?= htmlspecialchars($u['date_naissance'] ?? '') ?>"></div>
            <div class="form-group"><label>Lieu naissance</label><input type="text" name="lieu_naissance" value="<?= htmlspecialchars($u['lieu_naissance'] ?? '') ?>"></div>
        </div>
        <?php endif; ?>
        <?php if ($type === 'professeur'): ?>
        <div class="form-row">
            <div class="form-group"><label>Matière</label><input type="text" name="matiere" value="<?= htmlspecialchars($u['matiere'] ?? '') ?>"></div>
            <div class="form-group"><label>Prof. principal</label>
                <select name="professeur_principal">
                    <option value="non" <?= ($u['professeur_principal'] ?? 'non')==='non'?'selected':'' ?>>Non</option>
                    <option value="oui" <?= ($u['professeur_principal'] ?? 'non')!=='non'?'selected':'' ?>>Oui</option>
                </select>
            </div>
        </div>
        <?php endif; ?>
        <?php if ($type === 'parent'): ?>
        <div class="form-row">
            <div class="form-group"><label>Métier</label><input type="text" name="metier" value="<?= htmlspecialchars($u['metier'] ?? '') ?>"></div>
            <div class="form-group"><label>Parent d'élève</label>
                <select name="est_parent_eleve"><option value="non" <?= ($u['est_parent_eleve']??'non')==='non'?'selected':'' ?>>Non</option><option value="oui" <?= ($u['est_parent_eleve']??'non')==='oui'?'selected':'' ?>>Oui</option></select>
            </div>
        </div>
        <?php endif; ?>
        <?php if ($type === 'vie_scolaire'): ?>
        <div class="form-row">
            <div class="form-group"><label>CPE</label><select name="est_CPE"><option value="non" <?= ($u['est_CPE']??'non')==='non'?'selected':'' ?>>Non</option><option value="oui" <?= ($u['est_CPE']??'non')==='oui'?'selected':'' ?>>Oui</option></select></div>
            <div class="form-group"><label>Infirmerie</label><select name="est_infirmerie"><option value="non" <?= ($u['est_infirmerie']??'non')==='non'?'selected':'' ?>>Non</option><option value="oui" <?= ($u['est_infirmerie']??'non')==='oui'?'selected':'' ?>>Oui</option></select></div>
        </div>
        <?php endif; ?>
        <button type="submit" class="btn btn-primary" style="margin-top:10px;"><i class="fas fa-save"></i> Enregistrer</button>
    </form>
</div>

<?php if ($type === 'eleve'): ?>
<!-- Onglet Notes -->
<div class="tab-pane" id="tab-notes">
    <?php
    $notes = [];
    try {
        $stmt = $pdo->prepare("SELECT n.*, m.nom as nom_matiere, CONCAT(p.prenom,' ',p.nom) as nom_professeur FROM notes n JOIN matieres m ON n.id_matiere = m.id JOIN professeurs p ON n.id_professeur = p.id WHERE n.id_eleve = ? ORDER BY n.date_note DESC LIMIT 20");
        $stmt->execute([$id]);
        $notes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {} ?>
    <?php if (empty($notes)): ?>
        <p style="color:#999;">Aucune note enregistrée.</p>
    <?php else: ?>
        <table class="users-table" style="font-size:13px;">
            <tr><th>Matière</th><th>Note</th><th>Coef</th><th>Type</th><th>Date</th><th>Professeur</th></tr>
            <?php foreach ($notes as $n): ?>
            <tr>
                <td><?= htmlspecialchars($n['nom_matiere']) ?></td>
                <td><strong><?= $n['note'] ?>/<?= $n['note_sur'] ?></strong></td>
                <td><?= $n['coefficient'] ?></td>
                <td><?= htmlspecialchars($n['type_evaluation']) ?></td>
                <td><?= date('d/m/Y', strtotime($n['date_note'])) ?></td>
                <td><?= htmlspecialchars($n['nom_professeur']) ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>
</div>

<!-- Onglet Absences -->
<div class="tab-pane" id="tab-absences">
    <?php
    $absences = [];
    try {
        $stmt = $pdo->prepare("SELECT * FROM absences WHERE id_eleve = ? ORDER BY date_debut DESC LIMIT 20");
        $stmt->execute([$id]);
        $absences = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {} ?>
    <?php if (empty($absences)): ?>
        <p style="color:#999;">Aucune absence enregistrée.</p>
    <?php else: ?>
        <table class="users-table" style="font-size:13px;">
            <tr><th>Début</th><th>Fin</th><th>Type</th><th>Motif</th><th>Justifié</th></tr>
            <?php foreach ($absences as $a): ?>
            <tr>
                <td><?= date('d/m/Y H:i', strtotime($a['date_debut'])) ?></td>
                <td><?= date('d/m/Y H:i', strtotime($a['date_fin'])) ?></td>
                <td><?= htmlspecialchars($a['type_absence']) ?></td>
                <td><?= htmlspecialchars($a['motif'] ?? '-') ?></td>
                <td><?= $a['justifie'] ? '<span class="status-active">Oui</span>' : '<span class="status-inactive">Non</span>' ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- Actions rapides -->
<div class="modal-actions">
    <form method="post" action="index.php" style="display:inline"><input type="hidden" name="csrf_token" value="<?= $csrf_token ?>"><input type="hidden" name="action" value="reset_password"><input type="hidden" name="user_id" value="<?= $id ?>"><input type="hidden" name="user_type" value="<?= $type ?>"><button class="btn-xs warning"><i class="fas fa-key"></i> Réinit. MDP</button></form>
    <form method="post" action="index.php" style="display:inline"><input type="hidden" name="csrf_token" value="<?= $csrf_token ?>"><input type="hidden" name="action" value="toggle_active"><input type="hidden" name="user_id" value="<?= $id ?>"><input type="hidden" name="user_type" value="<?= $type ?>"><input type="hidden" name="new_active" value="<?= ($u['actif'] ?? 1) ? 0 : 1 ?>"><button class="btn-xs <?= ($u['actif'] ?? 1) ? 'warning' : 'success' ?>"><i class="fas fa-<?= ($u['actif'] ?? 1) ? 'ban' : 'check' ?>"></i> <?= ($u['actif'] ?? 1) ? 'Désactiver' : 'Activer' ?></button></form>
    <form method="post" action="index.php" style="display:inline" onsubmit="return confirm('Supprimer définitivement cet utilisateur ?')"><input type="hidden" name="csrf_token" value="<?= $csrf_token ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="user_id" value="<?= $id ?>"><input type="hidden" name="user_type" value="<?= $type ?>"><button class="btn-xs danger"><i class="fas fa-trash"></i> Supprimer</button></form>
</div>

<script>
function switchTab(name) {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
    event.target.classList.add('active');
    document.getElementById('tab-' + name).classList.add('active');
}
</script>
