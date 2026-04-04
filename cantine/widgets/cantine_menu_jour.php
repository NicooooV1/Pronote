<?php
$menu = $data['menu'] ?? null;
$date = $data['date'] ?? null;
?>
<?php if (!$menu): ?>
    <p class="widget-empty">Aucun menu disponible.</p>
<?php else: ?>
    <?php if ($date): ?>
        <div class="widget-stat-mini">Menu du <?= htmlspecialchars(date('d/m/Y', strtotime($date))) ?></div>
    <?php endif; ?>
    <ul class="widget-list">
        <?php if (!empty($menu['entree'])): ?>
        <li class="widget-list-item">
            <span class="widget-list-icon"><i class="fas fa-leaf" style="color:#48bb78"></i></span>
            <div class="widget-list-content">
                <div class="widget-list-title">Entrée</div>
                <div class="widget-list-meta"><?= htmlspecialchars($menu['entree']) ?></div>
            </div>
        </li>
        <?php endif; ?>
        <?php if (!empty($menu['plat_principal'])): ?>
        <li class="widget-list-item">
            <span class="widget-list-icon"><i class="fas fa-drumstick-bite" style="color:#ed8936"></i></span>
            <div class="widget-list-content">
                <div class="widget-list-title">Plat</div>
                <div class="widget-list-meta"><?= htmlspecialchars($menu['plat_principal']) ?></div>
            </div>
        </li>
        <?php endif; ?>
        <?php if (!empty($menu['accompagnement'])): ?>
        <li class="widget-list-item">
            <span class="widget-list-icon"><i class="fas fa-carrot" style="color:#d69e2e"></i></span>
            <div class="widget-list-content">
                <div class="widget-list-title">Accompagnement</div>
                <div class="widget-list-meta"><?= htmlspecialchars($menu['accompagnement']) ?></div>
            </div>
        </li>
        <?php endif; ?>
        <?php if (!empty($menu['dessert'])): ?>
        <li class="widget-list-item">
            <span class="widget-list-icon"><i class="fas fa-ice-cream" style="color:#e53e3e"></i></span>
            <div class="widget-list-content">
                <div class="widget-list-title">Dessert</div>
                <div class="widget-list-meta"><?= htmlspecialchars($menu['dessert']) ?></div>
            </div>
        </li>
        <?php endif; ?>
    </ul>
    <?php if (!empty($menu['remarques'])): ?>
        <div class="widget-list-meta" style="padding:4px 12px;font-style:italic"><?= htmlspecialchars($menu['remarques']) ?></div>
    <?php endif; ?>
<?php endif; ?>
