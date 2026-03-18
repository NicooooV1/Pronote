<?php
/**
 * M18 – Cantine — Menus de la semaine
 */
$activePage = 'menus';
$pageTitle = 'Cantine — Menus';
require_once __DIR__ . '/includes/header.php';

$semaine = isset($_GET['semaine']) ? $_GET['semaine'] : date('Y-\WW');
$dateRef = new DateTime();
$dateRef->setISODate((int)substr($semaine, 0, 4), (int)substr($semaine, 6));
$lundi = (clone $dateRef)->modify('monday this week');
$vendredi = (clone $lundi)->modify('+4 days');

$menus = $cantineService->getMenus($lundi->format('Y-m-d'), $vendredi->format('Y-m-d'));
$menusByDate = [];
foreach ($menus as $m) {
    $menusByDate[$m['date_menu']][$m['regime_special'] ?? 'normal'] = $m;
}

$prevWeek = (clone $lundi)->modify('-7 days')->format('Y-\WW');
$nextWeek = (clone $lundi)->modify('+7 days')->format('Y-\WW');

// Sauvegarde menu (admin)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isGestionnaire && isset($_POST['save_menu'])) {
    $cantineService->sauvegarderMenu([
        'date_menu'       => $_POST['date_menu'],
        'entree'          => $_POST['entree'] ?? '',
        'plat_principal'  => $_POST['plat_principal'] ?? '',
        'accompagnement'  => $_POST['accompagnement'] ?? '',
        'dessert'         => $_POST['dessert'] ?? '',
        'allergenes'      => $_POST['allergenes'] ?? '',
        'regime_special'  => $_POST['regime_special'] ?? 'normal',
    ]);
    header('Location: menus.php?semaine=' . $semaine . '&saved=1');
    exit;
}
?>

<div class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-utensils"></i> Menus de la cantine</h1>
        <div class="week-nav">
            <a href="?semaine=<?= $prevWeek ?>" class="btn btn-outline"><i class="fas fa-chevron-left"></i></a>
            <span class="week-label">Semaine du <?= $lundi->format('d/m') ?> au <?= $vendredi->format('d/m/Y') ?></span>
            <a href="?semaine=<?= $nextWeek ?>" class="btn btn-outline"><i class="fas fa-chevron-right"></i></a>
        </div>
    </div>

    <?php if (isset($_GET['saved'])): ?>
        <div class="alert alert-success">Menu enregistré avec succès.</div>
    <?php endif; ?>

    <div class="menus-grid">
        <?php
        $joursLabels = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi'];
        for ($i = 0; $i < 5; $i++):
            $jour = (clone $lundi)->modify("+{$i} days");
            $dateStr = $jour->format('Y-m-d');
            $menu = $menusByDate[$dateStr]['normal'] ?? null;
            $isToday = $dateStr === date('Y-m-d');
        ?>
        <div class="menu-card <?= $isToday ? 'menu-today' : '' ?>">
            <div class="menu-card-header">
                <h3><?= $joursLabels[$i] ?> <?= $jour->format('d/m') ?></h3>
                <?php if ($isToday): ?><span class="badge badge-primary">Aujourd'hui</span><?php endif; ?>
            </div>
            <div class="menu-card-body">
                <?php if ($menu): ?>
                    <?php if ($menu['entree']): ?>
                        <div class="menu-item"><span class="menu-label">Entrée</span><span><?= htmlspecialchars($menu['entree']) ?></span></div>
                    <?php endif; ?>
                    <div class="menu-item menu-main"><span class="menu-label">Plat</span><span><?= htmlspecialchars($menu['plat_principal'] ?? '—') ?></span></div>
                    <?php if ($menu['accompagnement']): ?>
                        <div class="menu-item"><span class="menu-label">Accompagnement</span><span><?= htmlspecialchars($menu['accompagnement']) ?></span></div>
                    <?php endif; ?>
                    <?php if ($menu['dessert']): ?>
                        <div class="menu-item"><span class="menu-label">Dessert</span><span><?= htmlspecialchars($menu['dessert']) ?></span></div>
                    <?php endif; ?>
                    <?php if ($menu['allergenes']): ?>
                        <div class="menu-allergenes"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($menu['allergenes']) ?></div>
                    <?php endif; ?>
                <?php else: ?>
                    <p class="text-muted">Menu non renseigné</p>
                <?php endif; ?>
            </div>
            <?php if ($isGestionnaire): ?>
            <div class="menu-card-footer">
                <button class="btn btn-sm btn-outline" onclick="editMenu('<?= $dateStr ?>')"><i class="fas fa-edit"></i> Modifier</button>
            </div>
            <?php endif; ?>
        </div>
        <?php endfor; ?>
    </div>
</div>

<?php if ($isGestionnaire): ?>
<div id="menuModal" class="modal" style="display:none">
    <div class="modal-content">
        <div class="modal-header"><h3>Modifier le menu</h3><button onclick="closeModal()" class="btn-close">&times;</button></div>
        <form method="post">
            <input type="hidden" name="save_menu" value="1">
            <input type="hidden" name="date_menu" id="modal_date">
            <div class="form-group"><label>Entrée</label><input type="text" name="entree" id="modal_entree" class="form-control"></div>
            <div class="form-group"><label>Plat principal</label><input type="text" name="plat_principal" id="modal_plat" class="form-control"></div>
            <div class="form-group"><label>Accompagnement</label><input type="text" name="accompagnement" id="modal_accomp" class="form-control"></div>
            <div class="form-group"><label>Dessert</label><input type="text" name="dessert" id="modal_dessert" class="form-control"></div>
            <div class="form-group"><label>Allergènes</label><input type="text" name="allergenes" id="modal_allergenes" class="form-control"></div>
            <div class="form-group"><label>Régime</label>
                <select name="regime_special" class="form-select">
                    <option value="normal">Normal</option>
                    <option value="végétarien">Végétarien</option>
                    <option value="sans porc">Sans porc</option>
                    <option value="sans gluten">Sans gluten</option>
                </select>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-primary">Enregistrer</button></div>
        </form>
    </div>
</div>
<script>
function editMenu(date) {
    document.getElementById('modal_date').value = date;
    document.getElementById('menuModal').style.display = 'flex';
}
function closeModal() { document.getElementById('menuModal').style.display = 'none'; }
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
