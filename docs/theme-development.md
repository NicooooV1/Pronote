# Theme Development Guide

## Overview

Fronote uses a CSS token-based theming system. Themes are CSS files that override design tokens defined in `assets/css/tokens.css`. Two built-in themes are provided: **classic** (default) and **glass** (glassmorphism overlay).

## Architecture

```
assets/css/
├── tokens.css          ← Design tokens (source of truth)
├── base.css            ← Reset, utilities, layout
├── components.css      ← UI component styles (BEM)
├── theme-classic.css   ← Default theme (always loaded)
├── theme-glass.css     ← Glass overlay (optional, additive)
├── rtl.css             ← RTL overrides for Arabic
└── themes/
    └── my-theme.css    ← Custom themes go here
```

### Loading Order

```
tokens.css → base.css → components.css → theme-classic.css → [theme-glass.css]
```

The glass theme is **additive** — it layers on top of classic, never loaded alone.

## Design Tokens

All customizable values are CSS custom properties in `tokens.css`:

### Colors

```css
:root {
    --primary: #4f46e5;
    --primary-light: #818cf8;
    --primary-dark: #3730a3;
    --success: #10b981;
    --warning: #f59e0b;
    --danger: #ef4444;
    --info: #3b82f6;

    --text: #1e293b;
    --text-light: #64748b;
    --text-muted: #94a3b8;

    --bg: #f8fafc;
    --bg-light: #f1f5f9;
    --bg-card: #ffffff;
    --bg-sidebar: #1e293b;

    --border: #e2e8f0;
    --border-light: #f1f5f9;
}
```

### Spacing (4px grid)

```css
:root {
    --space-xs: 4px;
    --space-sm: 8px;
    --space-md: 16px;
    --space-lg: 24px;
    --space-xl: 32px;
    --space-2xl: 48px;
}
```

### Typography

```css
:root {
    --font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
    --font-mono: 'JetBrains Mono', monospace;
    --fs-xs: 0.75rem;
    --fs-sm: 0.875rem;
    --fs-md: 1rem;
    --fs-lg: 1.125rem;
    --fs-xl: 1.25rem;
    --fs-2xl: 1.5rem;
}
```

### Shadows & Borders

```css
:root {
    --shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
    --shadow-md: 0 4px 6px rgba(0,0,0,0.07);
    --shadow-lg: 0 10px 15px rgba(0,0,0,0.1);
    --radius-sm: 6px;
    --radius-md: 8px;
    --radius-lg: 12px;
    --radius-full: 9999px;
}
```

## Creating a Custom Theme

### 1. Create the CSS file

```css
/* assets/css/themes/ocean.css */

:root {
    --primary: #0077b6;
    --primary-light: #48cae4;
    --primary-dark: #023e8a;
    --bg: #f0f8ff;
    --bg-card: #ffffff;
    --bg-sidebar: #023e8a;
    --border: #caf0f8;
}

/* Optional: override specific component styles */
.ui-card {
    border: 1px solid var(--primary-light);
}
```

### 2. Register the theme

Add the theme to the database:

```sql
INSERT INTO themes (name, label, css_file, is_default, created_at)
VALUES ('ocean', 'Ocean', 'assets/css/themes/ocean.css', 0, NOW());
```

### 3. Theme selection

Users select themes in `parametres/` (Settings). The selection is stored in `user_settings.theme` and loaded by `templates/shared_header.php`.

## Dark Mode

Dark mode is handled by adding a `[data-theme="dark"]` selector:

```css
[data-theme="dark"] {
    --text: #e2e8f0;
    --text-light: #94a3b8;
    --bg: #0f172a;
    --bg-light: #1e293b;
    --bg-card: #1e293b;
    --border: #334155;
}
```

The theme toggle is managed by `assets/js/pronote-theme.js`.

## RTL Support

When the locale is Arabic (`ar`), `<html dir="rtl">` is set and `assets/css/rtl.css` is loaded.

### Guidelines for RTL-compatible CSS

1. **Use logical properties** instead of physical ones:
   ```css
   /* Bad */
   margin-left: 16px;
   padding-right: 8px;

   /* Good */
   margin-inline-start: 16px;
   padding-inline-end: 8px;
   ```

2. **Flexbox direction** is auto-reversed by `dir="rtl"` — no changes needed for simple flex layouts.

3. **Icons with direction** (arrows, chevrons) need RTL overrides:
   ```css
   [dir="rtl"] .icon-arrow-right {
       transform: scaleX(-1);
   }
   ```

4. **Text alignment** — avoid `text-align: left`, use `text-align: start` instead.

5. **Absolute positioning** — replace `left`/`right` with `inset-inline-start`/`inset-inline-end` where supported.

### Testing RTL

1. Set locale to Arabic in the login page language selector
2. Verify sidebar appears on the right
3. Check text alignment and spacing in all components
4. Verify form labels and inputs are properly aligned
5. Check that directional icons (arrows, chevrons) are flipped

## BEM Naming Convention

All component CSS follows BEM:

```css
/* Block */
.ui-card { }

/* Element */
.ui-card__header { }
.ui-card__body { }
.ui-card__footer { }

/* Modifier */
.ui-card--collapsed { }
.ui-card__header--sticky { }
```

## Testing Your Theme

1. Apply the theme via Settings (`parametres/`)
2. Navigate through several modules to check consistency
3. Verify dark mode toggle works
4. Check RTL layout if your theme modifies spacing or positioning
5. Test on mobile viewports (sidebar collapse, card stacking)
6. Verify contrast ratios meet WCAG AA (4.5:1 for text, 3:1 for large text)
