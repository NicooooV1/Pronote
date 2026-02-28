<?php
// Inclure l'API centralisée
require_once __DIR__ . '/../API/core.php';

// Vérifier l'authentification
requireAuth();

// Récupération des informations utilisateur via l'API
$user = getCurrentUser();
$user_role = getUserRole();
$user_fullname = getUserFullName();
$user_initials = getUserInitials();

$pdo = getPDO();

// Récupérer le trimestre sélectionné (par défaut: actuel)
$selectedTrimestre = isset($_GET['trimestre']) ? (int)$_GET['trimestre'] : null;
if ($selectedTrimestre === null) {
    $mois = date('n');
    if ($mois >= 9 && $mois <= 12) $selectedTrimestre = 1;
    elseif ($mois >= 1 && $mois <= 3) $selectedTrimestre = 2;
    elseif ($mois >= 4 && $mois <= 6) $selectedTrimestre = 3;
    else $selectedTrimestre = 1;
}

// Récupérer les matières
$matieres = [];
try {
    $stmt = $pdo->query("SELECT * FROM matieres WHERE actif = 1 ORDER BY nom");
    $matieres = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { /* table may not exist */ }

// Récupérer les notes selon le rôle
$notes = [];
$moyennes_par_matiere = [];

try {
    if ($user_role === 'eleve') {
        // Élève : voir ses propres notes
        $stmt = $pdo->prepare("
            SELECT n.*, m.nom AS matiere_nom, m.couleur AS matiere_couleur, m.code AS matiere_code,
                   CONCAT(p.prenom, ' ', p.nom) AS professeur_nom
            FROM notes n
            LEFT JOIN matieres m ON n.id_matiere = m.id
            LEFT JOIN professeurs p ON n.id_professeur = p.id
            WHERE n.id_eleve = ? AND n.trimestre = ?
            ORDER BY n.date_note DESC
        ");
        $stmt->execute([$user['id'], $selectedTrimestre]);
        $notes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Calculer les moyennes par matière
        $stmt2 = $pdo->prepare("
            SELECT n.id_matiere, m.nom AS matiere_nom, m.couleur, m.code,
                   ROUND(SUM(n.note / n.note_sur * 20 * n.coefficient) / SUM(n.coefficient), 2) AS moyenne,
                   COUNT(n.id) AS nb_notes
            FROM notes n 
            LEFT JOIN matieres m ON n.id_matiere = m.id
            WHERE n.id_eleve = ? AND n.trimestre = ?
            GROUP BY n.id_matiere, m.nom, m.couleur, m.code
            ORDER BY m.nom
        ");
        $stmt2->execute([$user['id'], $selectedTrimestre]);
        $moyennes_par_matiere = $stmt2->fetchAll(PDO::FETCH_ASSOC);

    } elseif ($user_role === 'professeur') {
        // Professeur : voir les notes qu'il a données
        $stmt = $pdo->prepare("
            SELECT n.*, m.nom AS matiere_nom, m.couleur AS matiere_couleur,
                   CONCAT(e.prenom, ' ', e.nom) AS eleve_nom, e.classe
            FROM notes n
            LEFT JOIN matieres m ON n.id_matiere = m.id
            LEFT JOIN eleves e ON n.id_eleve = e.id
            WHERE n.id_professeur = ? AND n.trimestre = ?
            ORDER BY n.date_note DESC
        ");
        $stmt->execute([$user['id'], $selectedTrimestre]);
        $notes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } else {
        // Admin / vie scolaire : voir toutes les notes
        $stmt = $pdo->prepare("
            SELECT n.*, m.nom AS matiere_nom, m.couleur AS matiere_couleur,
                   CONCAT(e.prenom, ' ', e.nom) AS eleve_nom, e.classe,
                   CONCAT(p.prenom, ' ', p.nom) AS professeur_nom
            FROM notes n
            LEFT JOIN matieres m ON n.id_matiere = m.id
            LEFT JOIN eleves e ON n.id_eleve = e.id
            LEFT JOIN professeurs p ON n.id_professeur = p.id
            WHERE n.trimestre = ?
            ORDER BY n.date_note DESC
            LIMIT 200
        ");
        $stmt->execute([$selectedTrimestre]);
        $notes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    error_log("Erreur notes: " . $e->getMessage());
}

// Calculer la moyenne générale (pour élève)
$moyenneGenerale = null;
if ($user_role === 'eleve' && !empty($moyennes_par_matiere)) {
    $total = 0; $count = 0;
    foreach ($moyennes_par_matiere as $m) {
        $total += $m['moyenne'];
        $count++;
    }
    $moyenneGenerale = $count > 0 ? round($total / $count, 2) : null;
}

// Configuration des templates partagés
$pageTitle = 'Notes et résultats';
$activePage = 'notes';
$isAdmin = $user_role === 'administrateur';
$extraCss = ['assets/css/notes.css'];

// Contenu sidebar spécifique au module
ob_start();
?>
            <div class="sidebar-nav">
                <a href="interface_notes.php" class="sidebar-nav-item active">
                    <span class="sidebar-nav-icon"><i class="fas fa-list"></i></span>
                    <span>Liste des notes</span>
                </a>
                <?php if (in_array($user_role, ['professeur', 'administrateur'])): ?>
                <a href="ajouter_note.php" class="sidebar-nav-item">
                    <span class="sidebar-nav-icon"><i class="fas fa-plus"></i></span>
                    <span>Ajouter des notes</span>
                </a>
                <?php endif; ?>
            </div>
<?php
$sidebarExtraContent = ob_get_clean();

// Inclusion des templates partagés
include __DIR__ . '/../templates/shared_header.php';
include __DIR__ . '/../templates/shared_sidebar.php';
include __DIR__ . '/../templates/shared_topbar.php';
?>

            <!-- Contenu principal -->
            <div class="content-container">
                
                <!-- Sélecteur de trimestre -->
                <div class="trimestre-selector" style="display:flex; gap:10px; margin-bottom:25px; flex-wrap:wrap; align-items:center;">
                    <span style="font-weight:600; color:#4a5568; margin-right:5px;">Période :</span>
                    <?php for ($t = 1; $t <= 3; $t++): ?>
                    <a href="?trimestre=<?= $t ?>" class="btn <?= $selectedTrimestre === $t ? 'btn-primary' : 'btn-secondary' ?>" style="min-width:130px; text-align:center;">
                        <?= $t === 1 ? '1er' : $t . 'ème' ?> trimestre
                    </a>
                    <?php endfor; ?>
                </div>

                <?php if ($user_role === 'eleve'): ?>
                <!-- ========== VUE ÉLÈVE ========== -->
                
                <!-- Carte moyenne générale -->
                <?php if ($moyenneGenerale !== null): ?>
                <div style="background: linear-gradient(135deg, var(--primary-color), #1a6cb5); color:white; border-radius:12px; padding:25px 30px; margin-bottom:25px; display:flex; align-items:center; justify-content:space-between;">
                    <div>
                        <div style="font-size:14px; opacity:0.85; margin-bottom:4px;">Moyenne générale — <?= $selectedTrimestre === 1 ? '1er' : $selectedTrimestre . 'ème' ?> trimestre</div>
                        <div style="font-size:36px; font-weight:700;"><?= $moyenneGenerale ?><span style="font-size:18px; opacity:0.7;">/20</span></div>
                    </div>
                    <div style="font-size:48px; opacity:0.2;"><i class="fas fa-graduation-cap"></i></div>
                </div>
                <?php endif; ?>

                <!-- Moyennes par matière -->
                <?php if (!empty($moyennes_par_matiere)): ?>
                <h2 style="font-size:1.1em; margin-bottom:15px; color:#2d3748;">Moyennes par matière</h2>
                <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap:15px; margin-bottom:30px;">
                    <?php foreach ($moyennes_par_matiere as $m): ?>
                    <div style="background:white; border-radius:10px; padding:18px; box-shadow:0 2px 8px rgba(0,0,0,0.06); border-left:4px solid <?= htmlspecialchars($m['couleur'] ?? '#3498db') ?>;">
                        <div style="font-size:13px; color:#718096; margin-bottom:6px;"><?= htmlspecialchars($m['matiere_nom'] ?? 'Matière') ?></div>
                        <div style="font-size:26px; font-weight:700; color:#2d3748;">
                            <?= $m['moyenne'] ?><span style="font-size:14px; color:#a0aec0;">/20</span>
                        </div>
                        <div style="font-size:12px; color:#a0aec0; margin-top:4px;"><?= $m['nb_notes'] ?> évaluation<?= $m['nb_notes'] > 1 ? 's' : '' ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <!-- Liste des notes -->
                <h2 style="font-size:1.1em; margin-bottom:15px; color:#2d3748;">Détail des notes</h2>
                <?php if (empty($notes)): ?>
                    <div class="alert alert-info"><i class="fas fa-info-circle"></i> Aucune note pour ce trimestre.</div>
                <?php else: ?>
                <div style="background:white; border-radius:10px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,0.06);">
                    <table style="width:100%; border-collapse:collapse;">
                        <thead>
                            <tr style="background:#f7fafc;">
                                <th style="padding:12px 15px; text-align:left; font-size:13px; color:#4a5568; font-weight:600;">Date</th>
                                <th style="padding:12px 15px; text-align:left; font-size:13px; color:#4a5568; font-weight:600;">Matière</th>
                                <th style="padding:12px 15px; text-align:left; font-size:13px; color:#4a5568; font-weight:600;">Évaluation</th>
                                <th style="padding:12px 15px; text-align:center; font-size:13px; color:#4a5568; font-weight:600;">Note</th>
                                <th style="padding:12px 15px; text-align:center; font-size:13px; color:#4a5568; font-weight:600;">Coeff.</th>
                                <th style="padding:12px 15px; text-align:left; font-size:13px; color:#4a5568; font-weight:600;">Professeur</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($notes as $n): ?>
                            <tr style="border-bottom:1px solid #edf2f7;">
                                <td style="padding:12px 15px; font-size:14px;"><?= date('d/m/Y', strtotime($n['date_note'])) ?></td>
                                <td style="padding:12px 15px;">
                                    <span style="display:inline-block; padding:3px 10px; border-radius:12px; font-size:12px; font-weight:500; background:<?= htmlspecialchars($n['matiere_couleur'] ?? '#3498db') ?>20; color:<?= htmlspecialchars($n['matiere_couleur'] ?? '#3498db') ?>;">
                                        <?= htmlspecialchars($n['matiere_nom'] ?? '') ?>
                                    </span>
                                </td>
                                <td style="padding:12px 15px; font-size:14px;"><?= htmlspecialchars($n['type_evaluation'] ?? '') ?></td>
                                <td style="padding:12px 15px; text-align:center; font-weight:700; font-size:16px; color:<?= ($n['note'] / $n['note_sur'] * 20) >= 10 ? '#38a169' : '#e53e3e' ?>;">
                                    <?= $n['note'] ?><span style="font-size:12px; color:#a0aec0;">/<?= $n['note_sur'] ?></span>
                                </td>
                                <td style="padding:12px 15px; text-align:center; font-size:14px; color:#718096;">×<?= $n['coefficient'] ?></td>
                                <td style="padding:12px 15px; font-size:14px; color:#718096;"><?= htmlspecialchars($n['professeur_nom'] ?? '') ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>

                <?php elseif ($user_role === 'professeur'): ?>
                <!-- ========== VUE PROFESSEUR ========== -->
                
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                    <h2 style="font-size:1.1em; color:#2d3748; margin:0;">Notes attribuées — <?= $selectedTrimestre === 1 ? '1er' : $selectedTrimestre . 'ème' ?> trimestre</h2>
                    <a href="ajouter_note.php" class="btn btn-primary"><i class="fas fa-plus"></i> Ajouter des notes</a>
                </div>

                <?php if (empty($notes)): ?>
                    <div class="alert alert-info"><i class="fas fa-info-circle"></i> Aucune note pour ce trimestre. Commencez par en ajouter.</div>
                <?php else: ?>
                <div style="background:white; border-radius:10px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,0.06);">
                    <table style="width:100%; border-collapse:collapse;">
                        <thead>
                            <tr style="background:#f7fafc;">
                                <th style="padding:12px 15px; text-align:left; font-size:13px; color:#4a5568; font-weight:600;">Date</th>
                                <th style="padding:12px 15px; text-align:left; font-size:13px; color:#4a5568; font-weight:600;">Élève</th>
                                <th style="padding:12px 15px; text-align:left; font-size:13px; color:#4a5568; font-weight:600;">Classe</th>
                                <th style="padding:12px 15px; text-align:left; font-size:13px; color:#4a5568; font-weight:600;">Matière</th>
                                <th style="padding:12px 15px; text-align:left; font-size:13px; color:#4a5568; font-weight:600;">Évaluation</th>
                                <th style="padding:12px 15px; text-align:center; font-size:13px; color:#4a5568; font-weight:600;">Note</th>
                                <th style="padding:12px 15px; text-align:center; font-size:13px; color:#4a5568; font-weight:600;">Coeff.</th>
                                <th style="padding:12px 15px; text-align:center; font-size:13px; color:#4a5568; font-weight:600;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($notes as $n): ?>
                            <tr style="border-bottom:1px solid #edf2f7;">
                                <td style="padding:10px 15px; font-size:14px;"><?= date('d/m/Y', strtotime($n['date_note'])) ?></td>
                                <td style="padding:10px 15px; font-size:14px; font-weight:500;"><?= htmlspecialchars($n['eleve_nom'] ?? '') ?></td>
                                <td style="padding:10px 15px; font-size:13px; color:#718096;"><?= htmlspecialchars($n['classe'] ?? '') ?></td>
                                <td style="padding:10px 15px;">
                                    <span style="display:inline-block; padding:3px 10px; border-radius:12px; font-size:12px; font-weight:500; background:<?= htmlspecialchars($n['matiere_couleur'] ?? '#3498db') ?>20; color:<?= htmlspecialchars($n['matiere_couleur'] ?? '#3498db') ?>;">
                                        <?= htmlspecialchars($n['matiere_nom'] ?? '') ?>
                                    </span>
                                </td>
                                <td style="padding:10px 15px; font-size:14px;"><?= htmlspecialchars($n['type_evaluation'] ?? '') ?></td>
                                <td style="padding:10px 15px; text-align:center; font-weight:700; font-size:15px; color:<?= ($n['note'] / $n['note_sur'] * 20) >= 10 ? '#38a169' : '#e53e3e' ?>;">
                                    <?= $n['note'] ?>/<?= $n['note_sur'] ?>
                                </td>
                                <td style="padding:10px 15px; text-align:center; font-size:14px; color:#718096;">×<?= $n['coefficient'] ?></td>
                                <td style="padding:10px 15px; text-align:center;">
                                    <a href="modifier_note.php?id=<?= $n['id'] ?>" class="btn btn-secondary" style="padding:4px 10px; font-size:12px;" title="Modifier"><i class="fas fa-edit"></i></a>
                                    <a href="supprimer_note.php?id=<?= $n['id'] ?>" class="btn btn-danger" style="padding:4px 10px; font-size:12px;" title="Supprimer" onclick="return confirm('Supprimer cette note ?');"><i class="fas fa-trash"></i></a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>

                <?php else: ?>
                <!-- ========== VUE ADMIN / VIE SCOLAIRE ========== -->
                
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                    <h2 style="font-size:1.1em; color:#2d3748; margin:0;">Toutes les notes — <?= $selectedTrimestre === 1 ? '1er' : $selectedTrimestre . 'ème' ?> trimestre</h2>
                </div>

                <!-- Stats rapides -->
                <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap:15px; margin-bottom:25px;">
                    <div style="background:white; border-radius:10px; padding:18px; box-shadow:0 2px 8px rgba(0,0,0,0.06); text-align:center;">
                        <div style="font-size:28px; font-weight:700; color:var(--primary-color);"><?= count($notes) ?></div>
                        <div style="font-size:13px; color:#718096;">Notes enregistrées</div>
                    </div>
                    <div style="background:white; border-radius:10px; padding:18px; box-shadow:0 2px 8px rgba(0,0,0,0.06); text-align:center;">
                        <div style="font-size:28px; font-weight:700; color:#38a169;">
                            <?php 
                            $avg = 0;
                            if (!empty($notes)) {
                                $sum = 0;
                                foreach ($notes as $n) $sum += ($n['note'] / $n['note_sur'] * 20);
                                $avg = round($sum / count($notes), 1);
                            }
                            echo $avg;
                            ?>
                        </div>
                        <div style="font-size:13px; color:#718096;">Moyenne globale /20</div>
                    </div>
                    <div style="background:white; border-radius:10px; padding:18px; box-shadow:0 2px 8px rgba(0,0,0,0.06); text-align:center;">
                        <div style="font-size:28px; font-weight:700; color:#667eea;"><?= count(array_unique(array_column($notes, 'id_matiere'))) ?></div>
                        <div style="font-size:13px; color:#718096;">Matières évaluées</div>
                    </div>
                </div>

                <?php if (empty($notes)): ?>
                    <div class="alert alert-info"><i class="fas fa-info-circle"></i> Aucune note pour ce trimestre.</div>
                <?php else: ?>
                <div style="background:white; border-radius:10px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,0.06);">
                    <table style="width:100%; border-collapse:collapse;">
                        <thead>
                            <tr style="background:#f7fafc;">
                                <th style="padding:12px 15px; text-align:left; font-size:13px; color:#4a5568;">Date</th>
                                <th style="padding:12px 15px; text-align:left; font-size:13px; color:#4a5568;">Élève</th>
                                <th style="padding:12px 15px; text-align:left; font-size:13px; color:#4a5568;">Classe</th>
                                <th style="padding:12px 15px; text-align:left; font-size:13px; color:#4a5568;">Matière</th>
                                <th style="padding:12px 15px; text-align:left; font-size:13px; color:#4a5568;">Type</th>
                                <th style="padding:12px 15px; text-align:center; font-size:13px; color:#4a5568;">Note</th>
                                <th style="padding:12px 15px; text-align:left; font-size:13px; color:#4a5568;">Professeur</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($notes as $n): ?>
                            <tr style="border-bottom:1px solid #edf2f7;">
                                <td style="padding:10px 15px; font-size:14px;"><?= date('d/m/Y', strtotime($n['date_note'])) ?></td>
                                <td style="padding:10px 15px; font-size:14px; font-weight:500;"><?= htmlspecialchars($n['eleve_nom'] ?? '') ?></td>
                                <td style="padding:10px 15px; font-size:13px; color:#718096;"><?= htmlspecialchars($n['classe'] ?? '') ?></td>
                                <td style="padding:10px 15px;">
                                    <span style="display:inline-block; padding:3px 10px; border-radius:12px; font-size:12px; background:<?= htmlspecialchars($n['matiere_couleur'] ?? '#3498db') ?>20; color:<?= htmlspecialchars($n['matiere_couleur'] ?? '#3498db') ?>;">
                                        <?= htmlspecialchars($n['matiere_nom'] ?? '') ?>
                                    </span>
                                </td>
                                <td style="padding:10px 15px; font-size:14px;"><?= htmlspecialchars($n['type_evaluation'] ?? '') ?></td>
                                <td style="padding:10px 15px; text-align:center; font-weight:700; font-size:15px; color:<?= ($n['note'] / $n['note_sur'] * 20) >= 10 ? '#38a169' : '#e53e3e' ?>;">
                                    <?= $n['note'] ?>/<?= $n['note_sur'] ?>
                                </td>
                                <td style="padding:10px 15px; font-size:14px; color:#718096;"><?= htmlspecialchars($n['professeur_nom'] ?? '') ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
                <?php endif; ?>

            </div>

<?php
include __DIR__ . '/../templates/shared_footer.php';
?>