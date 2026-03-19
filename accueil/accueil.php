<?php
ob_start();

// Inclure l'API centralisee
require_once dirname(__DIR__) . '/API/core.php';
require_once __DIR__ . '/includes/DashboardService.php';

// Authentification via API
requireAuth();

// --- Donnees utilisateur ---
$user          = getCurrentUser();
$user_fullname = getUserFullName();
$user_role     = getUserRole();
$user_initials = getUserInitials();
$classe        = $user['classe'] ?? '';

$aujourdhui = date('d/m/Y');
$trimestre  = function_exists('getTrimestre') ? getTrimestre() : '';
$jours      = ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
$jour       = $jours[date('w')];

// --- Service metier ---
$pdo       = getPDO();
$dashboard = new DashboardService($pdo);

// Cache etablissement (REF-4)
$etablissement_data = DashboardService::getEtablissementData();
$nom_etablissement  = $etablissement_data['nom'] ?? 'Etablissement Scolaire';

// Greeting contextuel (FEAT-6)
$greeting = DashboardService::getGreeting();

// Modules adaptes au role (FEAT-3 + UX-1)
$modules = $dashboard->getModulesForRole($user_role);

// Badge messagerie (FEAT-4)
$unreadCount = $dashboard->getUnreadMessageCount($user['id'] ?? 0, $user_role);

// M104 — Widget-based dashboard
$userId = (int) ($user['id'] ?? 0);
$userWidgets    = $dashboard->getUserWidgets($userId, $user_role);
$availableAll   = $dashboard->getAvailableWidgets($user_role);

// Pre-render widget data for each visible widget
$widgetDataMap = [];
foreach ($userWidgets as $w) {
    if (!empty($w['visible'])) {
        $widgetDataMap[$w['widget_key']] = $dashboard->renderWidgetData($w['widget_key'], $userId, $user_role);
    }
}

// Determination admin
$isAdmin = $user_role === 'administrateur';

// Configuration des templates partages
$pageTitle  = 'Tableau de bord';
$activePage = 'accueil';
$extraCss   = ['assets/css/accueil.css'];

include __DIR__ . '/../templates/shared_header.php';
include __DIR__ . '/../templates/shared_sidebar.php';
include __DIR__ . '/../templates/shared_topbar.php';
?>

        <!-- Welcome Banner -->
        <div class="welcome-banner">
            <div class="welcome-content">
                <h2><?= $greeting ?>, <?= htmlspecialchars($user_fullname) ?></h2>
                <?php if (!empty($classe)): ?>
                <p>Classe de <?= htmlspecialchars($classe) ?></p>
                <?php endif; ?>
                <p class="welcome-date"><?= $jour . ' ' . $aujourdhui ?> - <?= $trimestre ?></p>
            </div>
            <div class="welcome-actions">
                <button type="button" class="btn-customize" id="btnPersonnaliser" title="Personnaliser le tableau de bord">
                    <i class="fas fa-sliders-h"></i> Personnaliser
                </button>
            </div>
        </div>

        <!-- Main Dashboard Content -->
        <div class="dashboard-content">

            <!-- Modules Grid (FEAT-3 + UX-1 : ordered & adapted to role) -->
            <div class="modules-grid">
                <?php foreach ($modules as $mod): ?>
                <a href="<?= htmlspecialchars($mod['href']) ?>" class="module-card <?= $mod['css'] ?>">
                    <div class="module-icon">
                        <i class="<?= $mod['icon'] ?>"></i>
                        <?php if ($mod['css'] === 'messagerie-card' && $unreadCount > 0): ?>
                            <span class="badge-count"><?= $unreadCount ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="module-info">
                        <h3><?= htmlspecialchars($mod['title']) ?></h3>
                        <p><?= htmlspecialchars($mod['desc']) ?></p>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>

            <!-- M104 : Widget Grid Dashboard -->
            <div class="widget-grid" id="widgetGrid">
                <?php foreach ($userWidgets as $idx => $widget):
                    if (empty($widget['visible'])) continue;
                    $wKey   = $widget['widget_key'];
                    $wType  = $widget['type'] ?? 'list';
                    $wLabel = $widget['label'] ?? $wKey;
                    $wIcon  = $widget['icon'] ?? 'fas fa-puzzle-piece';
                    $wWidth = (int) ($widget['width'] ?? $widget['default_width'] ?? 2);
                    $wData  = $widgetDataMap[$wKey] ?? ['type' => 'empty', 'items' => []];
                    $sizeClass = match(true) {
                        $wWidth >= 4 => 'widget-size-large',
                        $wWidth >= 2 => 'widget-size-medium',
                        default      => 'widget-size-small',
                    };
                ?>
                <div class="widget-card <?= $sizeClass ?>"
                     data-widget-key="<?= htmlspecialchars($wKey) ?>"
                     data-widget-type="<?= htmlspecialchars($wType) ?>"
                     data-position="<?= $idx ?>"
                     draggable="true">
                    <div class="widget-card-header">
                        <div class="widget-card-title">
                            <i class="<?= htmlspecialchars($wIcon) ?>"></i>
                            <span><?= htmlspecialchars($wLabel) ?></span>
                        </div>
                        <div class="widget-card-actions">
                            <button type="button" class="widget-btn widget-btn-minimize" title="Reduire" onclick="toggleWidgetBody(this)">
                                <i class="fas fa-chevron-up"></i>
                            </button>
                            <button type="button" class="widget-btn widget-btn-drag" title="Deplacer">
                                <i class="fas fa-grip-vertical"></i>
                            </button>
                        </div>
                    </div>
                    <div class="widget-card-body">
                        <?php
                        // Render based on widget type
                        switch ($wType) {
                            case 'stats':
                                renderStatWidget($wData);
                                break;
                            case 'chart':
                                renderChartWidget($wData, $wKey);
                                break;
                            case 'list':
                                renderListWidget($wData, $wKey);
                                break;
                            case 'calendar':
                                renderCalendarWidget($wData);
                                break;
                            case 'shortcut':
                                renderShortcutWidget($wData);
                                break;
                            default:
                                renderListWidget($wData, $wKey);
                                break;
                        }
                        ?>
                    </div>
                    <?php if (!empty($wData['link'])): ?>
                    <div class="widget-card-footer">
                        <a href="<?= htmlspecialchars($wData['link']) ?>" class="widget-footer-link">
                            <?= htmlspecialchars($wData['link_label'] ?? 'Voir plus') ?> <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>

        </div>

        <!-- Modal Personnaliser le dashboard -->
        <div class="modal-overlay" id="modalCustomize" style="display:none;">
            <div class="modal-customize">
                <div class="modal-customize-header">
                    <h2><i class="fas fa-sliders-h"></i> Personnaliser le tableau de bord</h2>
                    <button type="button" class="modal-close-btn" onclick="closeCustomizeModal()">&times;</button>
                </div>
                <div class="modal-customize-body">
                    <p class="modal-customize-hint">Activez ou desactivez les widgets, puis reordonnez-les par glisser-deposer.</p>
                    <div class="customize-widget-list" id="customizeWidgetList">
                        <!-- Filled by JS -->
                    </div>
                </div>
                <div class="modal-customize-footer">
                    <button type="button" class="btn-secondary" onclick="closeCustomizeModal()">Annuler</button>
                    <button type="button" class="btn-primary" onclick="saveCustomization()">
                        <i class="fas fa-save"></i> Enregistrer
                    </button>
                </div>
            </div>
        </div>

<?php
// --- PHP widget renderers ---

function renderStatWidget(array $data): void
{
    if (isset($data['type']) && $data['type'] === 'stats_grid' && !empty($data['items'])) {
        echo '<div class="widget-stats-grid">';
        foreach ($data['items'] as $card) {
            $color = $card['color'] ?? 'primary';
            echo '<div class="widget-stat-item widget-stat-' . htmlspecialchars($color) . '">';
            echo '  <div class="widget-stat-icon"><i class="' . htmlspecialchars($card['icon'] ?? 'fas fa-info') . '"></i></div>';
            echo '  <div class="widget-stat-info">';
            echo '    <div class="widget-stat-value">' . htmlspecialchars((string)($card['value'] ?? '-')) . '</div>';
            echo '    <div class="widget-stat-label">' . htmlspecialchars($card['label'] ?? '') . '</div>';
            echo '  </div>';
            echo '</div>';
        }
        echo '</div>';
        return;
    }

    // Single stat
    $value = $data['value'] ?? 0;
    $label = $data['label'] ?? '';
    $icon  = $data['icon'] ?? 'fas fa-info-circle';
    $color = $data['color'] ?? 'primary';
    $trend = $data['trend'] ?? null;

    echo '<div class="widget-stat-single widget-stat-' . htmlspecialchars($color) . '">';
    echo '  <div class="widget-stat-big-icon"><i class="' . htmlspecialchars($icon) . '"></i></div>';
    echo '  <div class="widget-stat-big-value">' . htmlspecialchars((string) $value) . '</div>';
    echo '  <div class="widget-stat-big-label">' . htmlspecialchars($label) . '</div>';
    if ($trend !== null) {
        $trendClass = $trend > 0 ? 'trend-up' : ($trend < 0 ? 'trend-down' : 'trend-neutral');
        $trendIcon  = $trend > 0 ? 'fa-arrow-up' : ($trend < 0 ? 'fa-arrow-down' : 'fa-minus');
        echo '  <div class="widget-stat-trend ' . $trendClass . '"><i class="fas ' . $trendIcon . '"></i> ' . abs($trend) . '%</div>';
    }
    echo '</div>';
}

function renderChartWidget(array $data, string $widgetKey): void
{
    echo '<div class="widget-chart-placeholder" data-widget="' . htmlspecialchars($widgetKey) . '">';
    echo '  <div class="chart-area" id="chart-' . htmlspecialchars($widgetKey) . '">';
    echo '    <div class="chart-placeholder-content">';
    echo '      <i class="fas fa-chart-area"></i>';
    echo '      <p>Graphique disponible prochainement</p>';
    echo '    </div>';
    echo '  </div>';
    echo '</div>';
}

function renderListWidget(array $data, string $widgetKey): void
{
    $items = $data['items'] ?? [];

    if (empty($items)) {
        echo '<div class="empty-widget-message">';
        echo '  <i class="fas fa-info-circle"></i>';
        echo '  <p>Aucun element a afficher</p>';
        echo '</div>';
        return;
    }

    echo '<div class="widget-list-scroll">';
    echo '<ul class="widget-list">';

    foreach ($items as $item) {
        echo '<li class="widget-list-item">';

        // Determine rendering based on widget key and available fields
        if ($widgetKey === 'dernieres_notes' && isset($item['note'])) {
            $noteSur = $item['note_sur'] ?? 20;
            echo '<div class="widget-list-badge badge-primary">' . htmlspecialchars($item['note']) . '/' . $noteSur . '</div>';
            echo '<div class="widget-list-info">';
            echo '  <div class="widget-list-title">' . htmlspecialchars($item['nom_matiere'] ?? '') . '</div>';
            echo '  <div class="widget-list-sub">' . (!empty($item['date_creation']) ? date('d/m/Y', strtotime($item['date_creation'])) : '') . '</div>';
            echo '</div>';
        } elseif ($widgetKey === 'devoirs_a_faire' && isset($item['date_rendu'])) {
            echo '<div class="widget-list-badge badge-success">' . date('d/m', strtotime($item['date_rendu'])) . '</div>';
            echo '<div class="widget-list-info">';
            echo '  <div class="widget-list-title">' . htmlspecialchars($item['titre'] ?? '') . '</div>';
            echo '  <div class="widget-list-sub">' . htmlspecialchars(($item['nom_matiere'] ?? '') . ' - ' . ($item['nom_professeur'] ?? '')) . '</div>';
            echo '</div>';
        } elseif (($widgetKey === 'prochains_evenements' || $widgetKey === 'reunions_a_venir') && isset($item['date_debut'])) {
            $typeEvt = strtolower($item['type_evenement'] ?? 'autre');
            echo '<div class="widget-list-badge badge-info">' . date('d/m', strtotime($item['date_debut'])) . '</div>';
            echo '<div class="widget-list-info">';
            echo '  <div class="widget-list-title">' . htmlspecialchars($item['titre'] ?? '') . '</div>';
            echo '  <div class="widget-list-sub">' . date('H:i', strtotime($item['date_debut']));
            if (!empty($item['date_fin'])) echo ' - ' . date('H:i', strtotime($item['date_fin']));
            echo '</div>';
            echo '</div>';
        } elseif ($widgetKey === 'annonces_recentes' && isset($item['titre'])) {
            $priorite = $item['priorite'] ?? 'normale';
            $badgeClass = $priorite === 'urgente' ? 'badge-danger' : ($priorite === 'importante' ? 'badge-warning' : 'badge-info');
            echo '<div class="widget-list-badge ' . $badgeClass . '"><i class="fas fa-bullhorn"></i></div>';
            echo '<div class="widget-list-info">';
            echo '  <div class="widget-list-title">' . htmlspecialchars($item['titre']) . '</div>';
            echo '  <div class="widget-list-sub">' . htmlspecialchars($item['auteur'] ?? '') . (!empty($item['date_publication']) ? ' - ' . date('d/m/Y', strtotime($item['date_publication'])) : '') . '</div>';
            echo '</div>';
        } elseif ($widgetKey === 'absences_du_jour' && isset($item['nom_eleve'])) {
            echo '<div class="widget-list-badge badge-danger"><i class="fas fa-user-times"></i></div>';
            echo '<div class="widget-list-info">';
            echo '  <div class="widget-list-title">' . htmlspecialchars($item['nom_eleve']) . '</div>';
            echo '  <div class="widget-list-sub">' . htmlspecialchars($item['classe'] ?? '') . ' - ' . htmlspecialchars($item['statut'] ?? '') . '</div>';
            echo '</div>';
        } else {
            // Generic fallback
            echo '<div class="widget-list-info">';
            $title = $item['titre'] ?? $item['nom_eleve'] ?? $item['label'] ?? '';
            $sub   = $item['description'] ?? $item['date_debut'] ?? '';
            echo '  <div class="widget-list-title">' . htmlspecialchars((string) $title) . '</div>';
            if ($sub) echo '  <div class="widget-list-sub">' . htmlspecialchars((string) $sub) . '</div>';
            echo '</div>';
        }

        echo '</li>';
    }

    echo '</ul>';
    echo '</div>';
}

function renderCalendarWidget(array $data): void
{
    $items = $data['items'] ?? [];

    if (empty($items)) {
        echo '<div class="widget-calendar-mini">';
        echo '  <div class="calendar-today">';
        echo '    <div class="calendar-today-day">' . date('d') . '</div>';
        $moisFr = ['', 'janvier', 'fevrier', 'mars', 'avril', 'mai', 'juin', 'juillet', 'aout', 'septembre', 'octobre', 'novembre', 'decembre'];
    echo '    <div class="calendar-today-month">' . ($moisFr[(int)date('n')] ?? '') . ' ' . date('Y') . '</div>';
        echo '  </div>';
        echo '  <div class="empty-widget-message">';
        echo '    <i class="fas fa-check-circle"></i>';
        echo '    <p>Aucun cours aujourd\'hui</p>';
        echo '  </div>';
        echo '</div>';
        return;
    }

    echo '<div class="widget-calendar-mini">';
    echo '  <div class="calendar-day-header">';
    $joursFr = ['', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];
    $jourNum = (int) date('N');
    echo '    <span class="calendar-day-name">' . ($joursFr[$jourNum] ?? '') . ' ' . date('d/m/Y') . '</span>';
    echo '  </div>';
    echo '  <div class="calendar-timeline">';

    foreach ($items as $cours) {
        $hDebut = $cours['heure_debut'] ?? '';
        $hFin   = $cours['heure_fin'] ?? '';
        $matiere = $cours['matiere'] ?? '';
        $lieu    = $cours['salle'] ?? $cours['classe'] ?? '';
        $prof    = $cours['professeur'] ?? '';

        echo '<div class="calendar-slot">';
        echo '  <div class="calendar-slot-time">';
        if ($hDebut) echo htmlspecialchars(substr($hDebut, 0, 5));
        echo '  </div>';
        echo '  <div class="calendar-slot-content">';
        echo '    <div class="calendar-slot-title">' . htmlspecialchars($matiere) . '</div>';
        echo '    <div class="calendar-slot-sub">';
        $parts = array_filter([$lieu, $prof]);
        echo htmlspecialchars(implode(' - ', $parts));
        echo '    </div>';
        echo '  </div>';
        echo '</div>';
    }

    echo '  </div>';
    echo '</div>';
}

function renderShortcutWidget(array $data): void
{
    $items = $data['items'] ?? [];

    if (empty($items)) {
        echo '<div class="empty-widget-message"><i class="fas fa-info-circle"></i><p>Aucun raccourci</p></div>';
        return;
    }

    echo '<div class="widget-shortcuts-grid">';
    foreach ($items as $mod) {
        $href  = $mod['href'] ?? '#';
        $icon  = $mod['icon'] ?? 'fas fa-link';
        $title = $mod['title'] ?? '';
        echo '<a href="' . htmlspecialchars($href) . '" class="widget-shortcut-item">';
        echo '  <div class="widget-shortcut-icon"><i class="' . htmlspecialchars($icon) . '"></i></div>';
        echo '  <span>' . htmlspecialchars($title) . '</span>';
        echo '</a>';
    }
    echo '</div>';
}

// Pass data to JS
$jsWidgetConfig = [];
foreach ($userWidgets as $idx => $w) {
    $jsWidgetConfig[] = [
        'widget_key' => $w['widget_key'],
        'position_x' => (int) ($w['position_x'] ?? 0),
        'position_y' => (int) ($w['position_y'] ?? $idx),
        'width'      => (int) ($w['width'] ?? $w['default_width'] ?? 2),
        'height'     => (int) ($w['height'] ?? $w['default_height'] ?? 1),
        'visible'    => (int) ($w['visible'] ?? 1),
        'label'      => $w['label'] ?? '',
        'icon'       => $w['icon'] ?? '',
        'type'       => $w['type'] ?? 'list',
    ];
}

$jsAvailableWidgets = [];
foreach ($availableAll as $aw) {
    $jsAvailableWidgets[] = [
        'widget_key'  => $aw['widget_key'],
        'label'       => $aw['label'],
        'description' => $aw['description'] ?? '',
        'icon'        => $aw['icon'] ?? 'fas fa-puzzle-piece',
        'type'        => $aw['type'] ?? 'list',
    ];
}

$extraScriptHtml = '<script>
window.DASHBOARD_CONFIG = ' . json_encode($jsWidgetConfig, JSON_HEX_TAG | JSON_HEX_AMP) . ';
window.DASHBOARD_AVAILABLE = ' . json_encode($jsAvailableWidgets, JSON_HEX_TAG | JSON_HEX_AMP) . ';
window.DASHBOARD_CSRF = ' . json_encode($_SESSION['csrf_token'] ?? '', JSON_HEX_TAG | JSON_HEX_AMP) . ';
</script>
<script>
(function() {
    "use strict";

    // =====================================================================
    //  Drag & Drop Reordering (native HTML5 API)
    // =====================================================================
    var grid = document.getElementById("widgetGrid");
    var dragSrcEl = null;

    function handleDragStart(e) {
        dragSrcEl = this;
        this.classList.add("widget-dragging");
        e.dataTransfer.effectAllowed = "move";
        e.dataTransfer.setData("text/plain", this.dataset.widgetKey);
    }

    function handleDragOver(e) {
        e.preventDefault();
        e.dataTransfer.dropEffect = "move";
        this.classList.add("widget-drag-over");
        return false;
    }

    function handleDragEnter(e) {
        this.classList.add("widget-drag-over");
    }

    function handleDragLeave(e) {
        this.classList.remove("widget-drag-over");
    }

    function handleDrop(e) {
        e.stopPropagation();
        e.preventDefault();
        this.classList.remove("widget-drag-over");

        if (dragSrcEl !== this) {
            var parent = grid;
            var allCards = Array.from(parent.querySelectorAll(".widget-card"));
            var fromIdx = allCards.indexOf(dragSrcEl);
            var toIdx   = allCards.indexOf(this);

            if (fromIdx < toIdx) {
                parent.insertBefore(dragSrcEl, this.nextSibling);
            } else {
                parent.insertBefore(dragSrcEl, this);
            }

            saveLayoutToServer();
        }
        return false;
    }

    function handleDragEnd(e) {
        this.classList.remove("widget-dragging");
        var cards = grid.querySelectorAll(".widget-card");
        cards.forEach(function(c) { c.classList.remove("widget-drag-over"); });
    }

    function initDragAndDrop() {
        var cards = grid.querySelectorAll(".widget-card");
        cards.forEach(function(card) {
            card.addEventListener("dragstart", handleDragStart, false);
            card.addEventListener("dragenter", handleDragEnter, false);
            card.addEventListener("dragover", handleDragOver, false);
            card.addEventListener("dragleave", handleDragLeave, false);
            card.addEventListener("drop", handleDrop, false);
            card.addEventListener("dragend", handleDragEnd, false);
        });
    }

    // =====================================================================
    //  Toggle widget body (minimize/expand)
    // =====================================================================
    window.toggleWidgetBody = function(btn) {
        var card = btn.closest(".widget-card");
        var body = card.querySelector(".widget-card-body");
        var footer = card.querySelector(".widget-card-footer");
        var icon = btn.querySelector("i");

        if (card.classList.contains("widget-minimized")) {
            card.classList.remove("widget-minimized");
            body.style.display = "";
            if (footer) footer.style.display = "";
            icon.className = "fas fa-chevron-up";
        } else {
            card.classList.add("widget-minimized");
            body.style.display = "none";
            if (footer) footer.style.display = "none";
            icon.className = "fas fa-chevron-down";
        }
    };

    // =====================================================================
    //  Save layout to server
    // =====================================================================
    function saveLayoutToServer() {
        var cards = grid.querySelectorAll(".widget-card");
        var layout = [];
        cards.forEach(function(card, idx) {
            var sizeClass = card.classList.contains("widget-size-large") ? 4
                          : card.classList.contains("widget-size-small") ? 1 : 2;
            layout.push({
                widget_key: card.dataset.widgetKey,
                position_x: 0,
                position_y: idx,
                width: sizeClass,
                height: 1,
                visible: card.style.display !== "none" ? 1 : 0
            });
        });

        fetch("ajax_dashboard.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
                action: "save_layout",
                csrf_token: window.DASHBOARD_CSRF,
                layout: layout
            })
        }).then(function(r) { return r.json(); }).then(function(data) {
            if (data.success) {
                showToast("Layout sauvegarde", "success");
            }
        }).catch(function() {});
    }

    // =====================================================================
    //  Customize Modal
    // =====================================================================
    var btnCustomize = document.getElementById("btnPersonnaliser");
    var modal = document.getElementById("modalCustomize");
    var customizeList = document.getElementById("customizeWidgetList");

    if (btnCustomize) {
        btnCustomize.addEventListener("click", function() {
            openCustomizeModal();
        });
    }

    function openCustomizeModal() {
        modal.style.display = "flex";

        var currentKeys = {};
        window.DASHBOARD_CONFIG.forEach(function(w) {
            currentKeys[w.widget_key] = w;
        });

        var orderedWidgets = [];
        window.DASHBOARD_CONFIG.forEach(function(w) {
            orderedWidgets.push({
                widget_key: w.widget_key,
                label: w.label,
                icon: w.icon,
                type: w.type,
                visible: w.visible,
                description: ""
            });
        });

        window.DASHBOARD_AVAILABLE.forEach(function(aw) {
            if (!currentKeys[aw.widget_key]) {
                orderedWidgets.push({
                    widget_key: aw.widget_key,
                    label: aw.label,
                    icon: aw.icon,
                    type: aw.type,
                    visible: 0,
                    description: aw.description || ""
                });
            } else {
                for (var i = 0; i < orderedWidgets.length; i++) {
                    if (orderedWidgets[i].widget_key === aw.widget_key) {
                        orderedWidgets[i].description = aw.description || "";
                        break;
                    }
                }
            }
        });

        customizeList.innerHTML = "";
        orderedWidgets.forEach(function(w, idx) {
            var div = document.createElement("div");
            div.className = "customize-widget-item" + (w.visible ? " customize-active" : "");
            div.setAttribute("draggable", "true");
            div.dataset.widgetKey = w.widget_key;
            div.dataset.index = idx;

            div.innerHTML =
                \'<div class="customize-drag-handle"><i class="fas fa-grip-vertical"></i></div>\' +
                \'<div class="customize-widget-icon"><i class="\' + escapeHtml(w.icon) + \'"></i></div>\' +
                \'<div class="customize-widget-info">\' +
                \'  <div class="customize-widget-name">\' + escapeHtml(w.label) + \'</div>\' +
                \'  <div class="customize-widget-desc">\' + escapeHtml(w.description) + \'</div>\' +
                \'</div>\' +
                \'<label class="customize-toggle">\' +
                \'  <input type="checkbox" \' + (w.visible ? "checked" : "") + \' data-key="\' + escapeHtml(w.widget_key) + \'">\' +
                \'  <span class="customize-toggle-slider"></span>\' +
                \'</label>\';

            customizeList.appendChild(div);
        });

        initCustomizeDrag();
    }

    window.closeCustomizeModal = function() {
        modal.style.display = "none";
    };

    modal.addEventListener("click", function(e) {
        if (e.target === modal) closeCustomizeModal();
    });

    window.saveCustomization = function() {
        var items = customizeList.querySelectorAll(".customize-widget-item");
        var layout = [];
        items.forEach(function(item, idx) {
            var checkbox = item.querySelector("input[type=checkbox]");
            var key = item.dataset.widgetKey;
            var origWidget = null;
            for (var i = 0; i < window.DASHBOARD_CONFIG.length; i++) {
                if (window.DASHBOARD_CONFIG[i].widget_key === key) {
                    origWidget = window.DASHBOARD_CONFIG[i];
                    break;
                }
            }
            layout.push({
                widget_key: key,
                position_x: 0,
                position_y: idx,
                width: origWidget ? origWidget.width : 2,
                height: 1,
                visible: checkbox.checked ? 1 : 0
            });
        });

        fetch("ajax_dashboard.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
                action: "save_layout",
                csrf_token: window.DASHBOARD_CSRF,
                layout: layout
            })
        }).then(function(r) { return r.json(); }).then(function(data) {
            if (data.success) {
                showToast("Personnalisation sauvegardee", "success");
                closeCustomizeModal();
                setTimeout(function() { window.location.reload(); }, 600);
            } else {
                showToast("Erreur : " + (data.message || "Echec"), "error");
            }
        }).catch(function() {
            showToast("Erreur reseau", "error");
        });
    };

    // =====================================================================
    //  Customize Modal — drag reorder
    // =====================================================================
    var dragCustomizeSrc = null;

    function initCustomizeDrag() {
        var items = customizeList.querySelectorAll(".customize-widget-item");
        items.forEach(function(item) {
            item.addEventListener("dragstart", function(e) {
                dragCustomizeSrc = this;
                this.classList.add("customize-dragging");
                e.dataTransfer.effectAllowed = "move";
                e.dataTransfer.setData("text/plain", this.dataset.widgetKey);
            });
            item.addEventListener("dragover", function(e) {
                e.preventDefault();
                e.dataTransfer.dropEffect = "move";
                this.classList.add("customize-drag-over");
            });
            item.addEventListener("dragleave", function(e) {
                this.classList.remove("customize-drag-over");
            });
            item.addEventListener("drop", function(e) {
                e.stopPropagation();
                e.preventDefault();
                this.classList.remove("customize-drag-over");
                if (dragCustomizeSrc && dragCustomizeSrc !== this) {
                    var allItems = Array.from(customizeList.querySelectorAll(".customize-widget-item"));
                    var fromIdx = allItems.indexOf(dragCustomizeSrc);
                    var toIdx = allItems.indexOf(this);
                    if (fromIdx < toIdx) {
                        customizeList.insertBefore(dragCustomizeSrc, this.nextSibling);
                    } else {
                        customizeList.insertBefore(dragCustomizeSrc, this);
                    }
                }
            });
            item.addEventListener("dragend", function(e) {
                this.classList.remove("customize-dragging");
                customizeList.querySelectorAll(".customize-widget-item").forEach(function(i) {
                    i.classList.remove("customize-drag-over");
                });
            });
        });
    }

    // =====================================================================
    //  Helpers
    // =====================================================================
    function escapeHtml(str) {
        if (!str) return "";
        var div = document.createElement("div");
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    function showToast(message, type) {
        var toast = document.createElement("div");
        toast.className = "dashboard-toast toast-" + (type || "info");
        toast.textContent = message;
        document.body.appendChild(toast);
        setTimeout(function() { toast.classList.add("toast-visible"); }, 10);
        setTimeout(function() {
            toast.classList.remove("toast-visible");
            setTimeout(function() { toast.remove(); }, 300);
        }, 2500);
    }

    // =====================================================================
    //  Init
    // =====================================================================
    document.addEventListener("DOMContentLoaded", function() {
        initDragAndDrop();
    });
})();
</script>';

include __DIR__ . '/../templates/shared_footer.php';
ob_end_flush();
?>
