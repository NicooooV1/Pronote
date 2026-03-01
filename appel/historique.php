<?php
/**
 * historique.php — Historique des appels (M04).
 *
 * Accès : admin, vie scolaire uniquement.
 */
ob_start();

require_once __DIR__ . '/../API/core.php';
require_once __DIR__ . '/includes/AppelService.php';

$pdo = getPDO();
requireAuth();

if (!isAdmin() && !isVieScolaire()) {
    redirect('/accueil/accueil.php');
}

$user      = getCurrentUser();
$user_fullname = getUserFullName();
$user_initials = getUserInitials();

$service = new AppelService($pdo);

$dateDebut = $_GET['date_debut'] ?? date('Y-m-01');
$dateFin   = $_GET['date_fin'] ?? date('Y-m-d');
$classeId  = isset($_GET['classe']) ? (int)$_GET['classe'] : null;

$classes = $service->getClasses();

// Récupérer les appels sur une période
$sql = "SELECT a.*, cl.nom AS classe_nom,
               CONCAT(p.prenom, ' ', p.nom) AS professeur_nom,
               m.nom AS matiere_nom,
               (SELECT COUNT(*) FROM appel_eleves ae WHERE ae.appel_id = a.id AND ae.statut = 'absent') AS nb_absents,
               (SELECT COUNT(*) FROM appel_eleves ae WHERE ae.appel_id = a.id AND ae.statut = 'retard') AS nb_retards,
               (SELECT COUNT(*) FROM appel_eleves ae WHERE ae.appel_id = a.id) AS nb_total
        FROM appels a
        JOIN classes cl ON a.classe_id = cl.id
        JOIN professeurs p ON a.professeur_id = p.id
        LEFT JOIN matieres m ON a.matiere_id = m.id
        WHERE a.date_appel BETWEEN ? AND ?";
$params = [$dateDebut, $dateFin];

if ($classeId) {
    $sql .= " AND a.classe_id = ?";
    $params[] = $classeId;
}

$sql .= " ORDER BY a.date_appel DESC, a.heure_debut";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$appels = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Historique des appels';
$currentPage = 'historique';

include 'includes/header.php';
?>

<div class="welcome-banner">
    <div class="welcome-content">
        <h2><i class="fas fa-history"></i> Historique des appels</h2>
        <p>Consultez tous les appels réalisés sur une période.</p>
    </div>
</div>

<!-- Filtres -->
<div class="card card-body mb-3">
    <form method="GET" class="filters-form">
        <div class="filter-group">
            <label>Du :</label>
            <input type="date" name="date_debut" value="<?= htmlspecialchars($dateDebut) ?>" class="form-control">
        </div>
        <div class="filter-group">
            <label>Au :</label>
            <input type="date" name="date_fin" value="<?= htmlspecialchars($dateFin) ?>" class="form-control">
        </div>
        <div class="filter-group">
            <label>Classe :</label>
            <select name="classe" class="form-control">
                <option value="">Toutes</option>
                <?php foreach ($classes as $cl): ?>
                    <option value="<?= $cl['id'] ?>" <?= $classeId == $cl['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cl['nom']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Filtrer</button>
    </form>
</div>

<?php if (!empty($appels)): ?>
<div class="card">
    <div class="card-body">
        <table class="table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Horaire</th>
                    <th>Classe</th>
                    <th>Matière</th>
                    <th>Professeur</th>
                    <th class="text-center">Effectif</th>
                    <th class="text-center">Absents</th>
                    <th class="text-center">Retards</th>
                    <th>Statut</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($appels as $a): ?>
                <tr>
                    <td><?= date('d/m/Y', strtotime($a['date_appel'])) ?></td>
                    <td><?= substr($a['heure_debut'], 0, 5) ?> — <?= substr($a['heure_fin'], 0, 5) ?></td>
                    <td><strong><?= htmlspecialchars($a['classe_nom']) ?></strong></td>
                    <td><?= htmlspecialchars($a['matiere_nom'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($a['professeur_nom']) ?></td>
                    <td class="text-center"><?= $a['nb_total'] ?></td>
                    <td class="text-center">
                        <?php if ($a['nb_absents'] > 0): ?>
                            <span class="badge badge-danger"><?= $a['nb_absents'] ?></span>
                        <?php else: ?>
                            0
                        <?php endif; ?>
                    </td>
                    <td class="text-center">
                        <?php if ($a['nb_retards'] > 0): ?>
                            <span class="badge badge-warning"><?= $a['nb_retards'] ?></span>
                        <?php else: ?>
                            0
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge badge-<?= $a['statut'] === 'valide' ? 'success' : 'warning' ?>">
                            <?= ucfirst($a['statut']) ?>
                        </span>
                    </td>
                    <td>
                        <a href="appel.php?id=<?= $a['id'] ?>&date=<?= $a['date_appel'] ?>" class="btn btn-sm btn-secondary">
                            <i class="fas fa-eye"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php else: ?>
<div class="empty-state">
    <i class="fas fa-clipboard fa-3x"></i>
    <h3>Aucun appel trouvé</h3>
    <p>Modifiez les critères de recherche pour trouver des appels.</p>
</div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
