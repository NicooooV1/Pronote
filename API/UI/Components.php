<?php
declare(strict_types=1);

/**
 * Fronote UI Component Library
 *
 * Fonctions globales retournant du HTML. Toutes les classes CSS sont
 * definies dans assets/css/components.css et utilisent les tokens de tokens.css.
 *
 * Usage : <?= ui_card('Titre', '<p>Contenu</p>') ?>
 */

// ─── Card ──────────────────────────────────────────────────────────────────

function ui_card(string $title = '', string $body = '', array $options = []): string
{
    $icon     = $options['icon'] ?? '';
    $footer   = $options['footer'] ?? '';
    $class    = $options['class'] ?? '';
    $collapse = $options['collapsible'] ?? false;
    $id       = $options['id'] ?? '';
    $idAttr   = $id ? ' id="' . e($id) . '"' : '';

    $html = '<div class="ui-card ' . e($class) . '"' . $idAttr . '>';

    if ($title) {
        $html .= '<div class="ui-card__header">';
        if ($icon) $html .= '<i class="' . e($icon) . ' ui-card__icon"></i>';
        $html .= '<h3 class="ui-card__title">' . $title . '</h3>';
        if ($collapse) $html .= '<button type="button" class="ui-card__toggle" aria-label="Toggle"><i class="fas fa-chevron-down"></i></button>';
        $html .= '</div>';
    }

    $html .= '<div class="ui-card__body">' . $body . '</div>';

    if ($footer) {
        $html .= '<div class="ui-card__footer">' . $footer . '</div>';
    }

    $html .= '</div>';
    return $html;
}

// ─── Table ─────────────────────────────────────────────────────────────────

function ui_table(array $columns, array $rows, array $options = []): string
{
    $class    = $options['class'] ?? '';
    $striped  = $options['striped'] ?? true;
    $hoverable = $options['hoverable'] ?? true;
    $empty    = $options['empty_message'] ?? 'Aucune donnee.';
    $id       = $options['id'] ?? '';

    $cls = 'ui-table';
    if ($striped) $cls .= ' ui-table--striped';
    if ($hoverable) $cls .= ' ui-table--hoverable';
    if ($class) $cls .= ' ' . $class;

    $idAttr = $id ? ' id="' . e($id) . '"' : '';

    $html = '<div class="ui-table-wrapper"' . $idAttr . '>';
    $html .= '<table class="' . e($cls) . '">';

    // Header
    $html .= '<thead><tr>';
    foreach ($columns as $col) {
        $label = is_array($col) ? ($col['label'] ?? '') : $col;
        $width = is_array($col) && isset($col['width']) ? ' style="width:' . e($col['width']) . '"' : '';
        $align = is_array($col) && isset($col['align']) ? ' class="text-' . e($col['align']) . '"' : '';
        $html .= '<th' . $width . $align . '>' . $label . '</th>';
    }
    $html .= '</tr></thead>';

    // Body
    $html .= '<tbody>';
    if (empty($rows)) {
        $html .= '<tr><td colspan="' . count($columns) . '" class="ui-table__empty">' . e($empty) . '</td></tr>';
    } else {
        foreach ($rows as $row) {
            $rowClass = '';
            if (is_array($row) && isset($row['_class'])) {
                $rowClass = ' class="' . e($row['_class']) . '"';
                unset($row['_class']);
            }
            $html .= '<tr' . $rowClass . '>';
            $cells = is_array($row) ? array_values($row) : [$row];
            foreach ($cells as $cell) {
                $html .= '<td>' . $cell . '</td>';
            }
            $html .= '</tr>';
        }
    }
    $html .= '</tbody></table></div>';

    return $html;
}

// ─── Modal ─────────────────────────────────────────────────────────────────

function ui_modal(string $id, string $title, string $body, array $options = []): string
{
    $footer = $options['footer'] ?? '';
    $size   = $options['size'] ?? 'md'; // sm, md, lg
    $class  = $options['class'] ?? '';

    $html = '<div class="ui-modal-overlay" id="' . e($id) . '" role="dialog" aria-modal="true" aria-labelledby="' . e($id) . '-title">';
    $html .= '<div class="ui-modal ui-modal--' . e($size) . ' ' . e($class) . '">';
    $html .= '<div class="ui-modal__header">';
    $html .= '<h3 class="ui-modal__title" id="' . e($id) . '-title">' . $title . '</h3>';
    $html .= '<button type="button" class="ui-modal__close" data-dismiss="modal" aria-label="Fermer">&times;</button>';
    $html .= '</div>';
    $html .= '<div class="ui-modal__body">' . $body . '</div>';
    if ($footer) {
        $html .= '<div class="ui-modal__footer">' . $footer . '</div>';
    }
    $html .= '</div></div>';

    return $html;
}

// ─── Form Group ────────────────────────────────────────────────────────────

function ui_form_group(string $label, string $input, array $options = []): string
{
    $hint    = $options['hint'] ?? '';
    $error   = $options['error'] ?? '';
    $required = $options['required'] ?? false;
    $class   = $options['class'] ?? '';

    $html = '<div class="ui-form-group ' . e($class) . ($error ? ' ui-form-group--error' : '') . '">';
    $html .= '<label class="ui-form-group__label">' . $label;
    if ($required) $html .= ' <span class="ui-form-group__required">*</span>';
    $html .= '</label>';
    $html .= $input;
    if ($hint && !$error) $html .= '<span class="ui-form-group__hint">' . e($hint) . '</span>';
    if ($error) $html .= '<span class="ui-form-group__error">' . e($error) . '</span>';
    $html .= '</div>';

    return $html;
}

// ─── Tabs ──────────────────────────────────────────────────────────────────

function ui_tabs(array $tabs, string $activeKey = ''): string
{
    if (empty($activeKey) && !empty($tabs)) {
        $activeKey = array_key_first($tabs);
    }

    $html = '<div class="ui-tabs">';
    $html .= '<div class="ui-tabs__nav" role="tablist">';
    foreach ($tabs as $key => $tab) {
        $label = is_array($tab) ? ($tab['label'] ?? $key) : $tab;
        $icon  = is_array($tab) && isset($tab['icon']) ? '<i class="' . e($tab['icon']) . '"></i> ' : '';
        $active = $key === $activeKey ? ' ui-tabs__tab--active' : '';
        $html .= '<button class="ui-tabs__tab' . $active . '" data-tab="' . e($key) . '" role="tab">' . $icon . $label . '</button>';
    }
    $html .= '</div>';

    foreach ($tabs as $key => $tab) {
        $content = is_array($tab) ? ($tab['content'] ?? '') : '';
        $active = $key === $activeKey ? ' ui-tabs__panel--active' : '';
        $html .= '<div class="ui-tabs__panel' . $active . '" data-tab-panel="' . e($key) . '" role="tabpanel">' . $content . '</div>';
    }

    $html .= '</div>';
    return $html;
}

// ─── Badge ─────────────────────────────────────────────────────────────────

function ui_badge(string $text, string $variant = 'default'): string
{
    return '<span class="ui-badge ui-badge--' . e($variant) . '">' . e($text) . '</span>';
}

// ─── Toast Container ───────────────────────────────────────────────────────

function ui_toast_container(): string
{
    return '<div id="ui-toast-container" class="ui-toast-container" aria-live="polite"></div>';
}

// ─── Skeleton ──────────────────────────────────────────────────────────────

function ui_skeleton(string $type = 'text', array $options = []): string
{
    $count = $options['count'] ?? 1;
    $html = '';
    for ($i = 0; $i < $count; $i++) {
        $html .= '<div class="ui-skeleton ui-skeleton--' . e($type) . '"></div>';
    }
    return $html;
}

// ─── Dropdown ──────────────────────────────────────────────────────────────

function ui_dropdown(string $trigger, array $items, array $options = []): string
{
    $align = $options['align'] ?? 'left';
    $id    = $options['id'] ?? 'dd-' . bin2hex(random_bytes(4));

    $html = '<div class="ui-dropdown" id="' . e($id) . '">';
    $html .= '<button type="button" class="ui-dropdown__trigger" data-dropdown="' . e($id) . '">' . $trigger . '</button>';
    $html .= '<div class="ui-dropdown__menu ui-dropdown__menu--' . e($align) . '">';
    foreach ($items as $item) {
        if ($item === '-') {
            $html .= '<div class="ui-dropdown__divider"></div>';
            continue;
        }
        $label = is_array($item) ? ($item['label'] ?? '') : $item;
        $href  = is_array($item) ? ($item['href'] ?? '#') : '#';
        $icon  = is_array($item) && isset($item['icon']) ? '<i class="' . e($item['icon']) . '"></i> ' : '';
        $cls   = is_array($item) && isset($item['class']) ? ' ' . e($item['class']) : '';
        $html .= '<a href="' . e($href) . '" class="ui-dropdown__item' . $cls . '">' . $icon . $label . '</a>';
    }
    $html .= '</div></div>';

    return $html;
}

// ─── Button ────────────────────────────────────────────────────────────────

function ui_button(string $label, array $options = []): string
{
    $variant  = $options['variant'] ?? 'primary';
    $size     = $options['size'] ?? 'md';
    $icon     = $options['icon'] ?? '';
    $href     = $options['href'] ?? '';
    $type     = $options['type'] ?? 'button';
    $class    = $options['class'] ?? '';
    $disabled = $options['disabled'] ?? false;
    $attrs    = $options['attrs'] ?? '';

    $cls = 'ui-btn ui-btn--' . e($variant) . ' ui-btn--' . e($size);
    if ($class) $cls .= ' ' . $class;

    $content = '';
    if ($icon) $content .= '<i class="' . e($icon) . '"></i> ';
    $content .= $label;

    $disAttr = $disabled ? ' disabled' : '';

    if ($href) {
        return '<a href="' . e($href) . '" class="' . e($cls) . '" ' . $attrs . '>' . $content . '</a>';
    }

    return '<button type="' . e($type) . '" class="' . e($cls) . '"' . $disAttr . ' ' . $attrs . '>' . $content . '</button>';
}

// ─── Alert ─────────────────────────────────────────────────────────────────

function ui_alert(string $message, string $type = 'info', array $options = []): string
{
    $dismissible = $options['dismissible'] ?? true;
    $icon = $options['icon'] ?? '';
    $icons = ['success' => 'fas fa-check-circle', 'error' => 'fas fa-exclamation-circle', 'warning' => 'fas fa-exclamation-triangle', 'info' => 'fas fa-info-circle'];
    if (!$icon && isset($icons[$type])) $icon = $icons[$type];

    $html = '<div class="ui-alert ui-alert--' . e($type) . '" role="alert">';
    if ($icon) $html .= '<i class="' . e($icon) . ' ui-alert__icon"></i>';
    $html .= '<span class="ui-alert__message">' . $message . '</span>';
    if ($dismissible) $html .= '<button type="button" class="ui-alert__close" aria-label="Fermer">&times;</button>';
    $html .= '</div>';

    return $html;
}

// ─── Pagination ────────────────────────────────────────────────────────────

function ui_pagination(int $current, int $total, int $perPage, string $baseUrl = '?'): string
{
    $totalPages = max(1, (int) ceil($total / $perPage));
    if ($totalPages <= 1) return '';

    $separator = strpos($baseUrl, '?') !== false ? '&' : '?';

    $html = '<nav class="ui-pagination">';

    // Previous
    if ($current > 1) {
        $html .= '<a href="' . e($baseUrl . $separator . 'page=' . ($current - 1)) . '" class="ui-pagination__link">&laquo;</a>';
    }

    // Pages
    $start = max(1, $current - 2);
    $end = min($totalPages, $current + 2);

    if ($start > 1) {
        $html .= '<a href="' . e($baseUrl . $separator . 'page=1') . '" class="ui-pagination__link">1</a>';
        if ($start > 2) $html .= '<span class="ui-pagination__dots">...</span>';
    }

    for ($i = $start; $i <= $end; $i++) {
        $active = $i === $current ? ' ui-pagination__link--active' : '';
        $html .= '<a href="' . e($baseUrl . $separator . 'page=' . $i) . '" class="ui-pagination__link' . $active . '">' . $i . '</a>';
    }

    if ($end < $totalPages) {
        if ($end < $totalPages - 1) $html .= '<span class="ui-pagination__dots">...</span>';
        $html .= '<a href="' . e($baseUrl . $separator . 'page=' . $totalPages) . '" class="ui-pagination__link">' . $totalPages . '</a>';
    }

    // Next
    if ($current < $totalPages) {
        $html .= '<a href="' . e($baseUrl . $separator . 'page=' . ($current + 1)) . '" class="ui-pagination__link">&raquo;</a>';
    }

    $html .= '</nav>';
    return $html;
}

// ─── Breadcrumb ────────────────────────────────────────────────────────────

function ui_breadcrumb(array $items): string
{
    $html = '<nav class="ui-breadcrumb" aria-label="Breadcrumb"><ol>';
    $last = count($items) - 1;
    foreach (array_values($items) as $i => $item) {
        $label = is_array($item) ? ($item['label'] ?? '') : $item;
        $href  = is_array($item) ? ($item['href'] ?? '') : '';
        $html .= '<li class="ui-breadcrumb__item">';
        if ($href && $i < $last) {
            $html .= '<a href="' . e($href) . '">' . e($label) . '</a>';
        } else {
            $html .= '<span aria-current="page">' . e($label) . '</span>';
        }
        $html .= '</li>';
    }
    $html .= '</ol></nav>';
    return $html;
}

// ─── Avatar ────────────────────────────────────────────────────────────────

function ui_avatar(string $initials, array $options = []): string
{
    $size  = $options['size'] ?? 'md'; // sm, md, lg
    $src   = $options['src'] ?? '';
    $class = $options['class'] ?? '';

    if ($src) {
        return '<img src="' . e($src) . '" alt="' . e($initials) . '" class="ui-avatar ui-avatar--' . e($size) . ' ' . e($class) . '">';
    }

    return '<div class="ui-avatar ui-avatar--' . e($size) . ' ' . e($class) . '">' . e($initials) . '</div>';
}

// ─── Stat Card ─────────────────────────────────────────────────────────────

function ui_stat_card(string $label, string $value, array $options = []): string
{
    $icon  = $options['icon'] ?? '';
    $trend = $options['trend'] ?? '';
    $color = $options['color'] ?? 'primary';
    $class = $options['class'] ?? '';

    $html = '<div class="ui-stat-card ui-stat-card--' . e($color) . ' ' . e($class) . '">';
    if ($icon) $html .= '<div class="ui-stat-card__icon"><i class="' . e($icon) . '"></i></div>';
    $html .= '<div class="ui-stat-card__content">';
    $html .= '<div class="ui-stat-card__value">' . e($value) . '</div>';
    $html .= '<div class="ui-stat-card__label">' . e($label) . '</div>';
    if ($trend) {
        $trendClass = str_starts_with($trend, '+') ? 'up' : (str_starts_with($trend, '-') ? 'down' : 'neutral');
        $html .= '<div class="ui-stat-card__trend ui-stat-card__trend--' . $trendClass . '">' . e($trend) . '</div>';
    }
    $html .= '</div></div>';

    return $html;
}

// ─── Empty State ───────────────────────────────────────────────────────────

function ui_empty_state(string $message, array $options = []): string
{
    $icon   = $options['icon'] ?? 'fas fa-inbox';
    $action = $options['action_label'] ?? '';
    $href   = $options['action_href'] ?? '#';
    $class  = $options['class'] ?? '';

    $html = '<div class="ui-empty-state ' . e($class) . '">';
    $html .= '<div class="ui-empty-state__icon"><i class="' . e($icon) . '"></i></div>';
    $html .= '<p class="ui-empty-state__message">' . e($message) . '</p>';
    if ($action) {
        $html .= '<a href="' . e($href) . '" class="ui-btn ui-btn--primary ui-btn--sm">' . e($action) . '</a>';
    }
    $html .= '</div>';

    return $html;
}
