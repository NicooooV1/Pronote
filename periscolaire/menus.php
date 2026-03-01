<?php
/**
 * M16 – Menus cantine
 */
$pageTitle = 'Menus cantine';
$activePage = 'menus';
require_once __DIR__ . '/includes/header.php';

$lundi = date('Y-m-d', strtotime('monday this week'));
$dimanche = date('Y-m-d', strtotime('friday this week'));
$semaine = $_GET['semaine'] ?? $lundi;
$lundiSemaine = date('Y-m-d', strtotime('monday this week', strtotime($semaine)));
$vendrediSemaine = date('Y-m-d', strtotime('friday this week', strtotime($semaine)));
$menus = $periService->getMenus($lundiSemaine, $vendrediSemaine);
$menusParDate = [];
foreach ($menus as $m) { $menusParDate[$m['date']] = $m; }
$isGestionnaire = isAdmin() || isPersonnelVS();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCSRFToken() && $isGestionnaire) {
    $periService->creerMenu([
        'date' => $_POST['date'], 'entree' => trim($_POST['entree'] ?? ''),
        'plat' => trim($_POST['plat'] ?? ''), 'accompagnement' => trim($_POST['accompagnement'] ?? ''),
        'dessert' => trim($_POST['dessert'] ?? ''), 'allergenes' => trim($_POST['allergenes'] ?? ''),
        'regime' => trim($_POST['regime'] ?? ''),
    ]);
    header('Location: menus.php?semaine=' . $lundiSemaine); exit;
}

$prevWeek = date('Y-m-d', strtotime('-7 days', strtotime($lundiSemaine)));
$nextWeek = date('Y-m-d', strtotime('+7 days', strtotime($lundiSemaine)));
$jours = PeriscolaireService::jours();
?>

<div class="content-wrapper">
    <div class="content-header"><h1><i class="fas fa-utensils"></i> Menus cantine</h1></div>

    <div class="filter-bar">
        <a href="menus.php?semaine=<?= $prevWeek ?>" class="btn btn-outline"><i class="fas fa-chevron-left"></i></a>
        <span style="font-weight:600;">Semaine du <?= formatDate($lundiSemaine) ?></span>
        <a href="menus.php?semaine=<?= $nextWeek ?>" class="btn btn-outline"><i class="fas fa-chevron-right"></i></a>
    </div>

    <div class="menus-week">
        <?php
        $jourKeys = array_keys($jours);
        for ($i = 0; $i < 5; $i++):
            $dateJour = date('Y-m-d', strtotime("+{$i} days", strtotime($lundiSemaine)));
            $menu = $menusParDate[$dateJour] ?? null;
        ?>
        <div class="menu-day <?= $dateJour === date('Y-m-d') ? 'today' : '' ?>">
            <div class="menu-day-header"><?= $jours[$jourKeys[$i]] ?> <small><?= formatDate($dateJour) ?></small></div>
            <?php if ($menu): ?>
            <div class="menu-content">
                <?php if ($menu['entree']): ?><div class="menu-course"><span class="course-label">Entrée</span> <?= htmlspecialchars($menu['entree']) ?></div><?php endif; ?>
                <?php if ($menu['plat']): ?><div class="menu-course"><span class="course-label">Plat</span> <?= htmlspecialchars($menu['plat']) ?></div><?php endif; ?>
                <?php if ($menu['accompagnement']): ?><div class="menu-course"><span class="course-label">Accomp.</span> <?= htmlspecialchars($menu['accompagnement']) ?></div><?php endif; ?>
                <?php if ($menu['dessert']): ?><div class="menu-course"><span class="course-label">Dessert</span> <?= htmlspecialchars($menu['dessert']) ?></div><?php endif; ?>
                <?php if ($menu['allergenes']): ?><div class="menu-allergenes"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($menu['allergenes']) ?></div><?php endif; ?>
            </div>
            <?php else: ?><p class="text-muted" style="padding:.5rem;">Menu non renseigné</p><?php endif; ?>
        </div>
        <?php endfor; ?>
    </div>

    <?php if ($isGestionnaire): ?>
    <div class="card" style="margin-top:1.5rem;">
        <div class="card-header"><h2>Ajouter un menu</h2></div>
        <div class="card-body">
            <form method="post">
                <?= csrfField() ?>
                <div class="form-grid-3">
                    <div class="form-group"><label>Date *</label><input type="date" name="date" class="form-control" required></div>
                    <div class="form-group"><label>Entrée</label><input type="text" name="entree" class="form-control"></div>
                    <div class="form-group"><label>Plat</label><input type="text" name="plat" class="form-control"></div>
                    <div class="form-group"><label>Accompagnement</label><input type="text" name="accompagnement" class="form-control"></div>
                    <div class="form-group"><label>Dessert</label><input type="text" name="dessert" class="form-control"></div>
                    <div class="form-group"><label>Allergènes</label><input type="text" name="allergenes" class="form-control"></div>
                    <div class="form-group"><label>Régime</label><input type="text" name="regime" class="form-control" placeholder="Végétarien, sans porc…"></div>
                </div>
                <button type="submit" class="btn btn-primary" style="margin-top:.5rem;"><i class="fas fa-plus"></i> Ajouter</button>
            </form>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
