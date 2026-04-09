# Translation Guide

## Overview

Fronote supports 8 locales: **FR** (French), **EN** (English), **ES** (Spanish), **DE** (German), **RU** (Russian), **NL** (Dutch), **AR** (Arabic), **TH** (Thai).

Translations are managed by `API/Services/TranslationService.php` and stored as JSON files in the `lang/` directory.

## File Structure

```
lang/
├── fr/
│   ├── common.json          ← Shared strings (buttons, labels, dates)
│   ├── auth.json            ← Login, registration, password reset
│   ├── admin.json           ← Admin panel strings
│   ├── errors.json          ← Error messages
│   └── modules/
│       ├── notes.json       ← Notes module strings
│       ├── absences.json    ← Absences module strings
│       └── ...              ← One file per module (48 total)
├── en/
│   └── (same structure)
├── es/
├── de/
├── ru/
├── nl/
├── ar/
└── th/
```

## Translation File Format

Each JSON file contains flat key-value pairs:

```json
{
  "title": "Notes",
  "add_grade": "Ajouter une note",
  "average": "Moyenne",
  "student_count": "Aucun eleve|:count eleve|:count eleves",
  "welcome_message": "Bienvenue :name"
}
```

### Conventions

- Keys are `snake_case` in English
- Values contain the translated text
- Parameter placeholders use `:param` syntax
- Pluralization uses `|` separator (zero|one|many)

## Using Translations in PHP

### Simple translation

```php
echo __('modules/notes.title');
// Output: "Notes"
```

### With parameters

```php
echo __('modules/notes.welcome_message', ['name' => $userName]);
// Output: "Bienvenue Jean"
```

### Pluralization

```php
echo _n('modules/notes.student_count', $count, ['count' => $count]);
// $count = 0 → "Aucun eleve"
// $count = 1 → "1 eleve"
// $count = 5 → "5 eleves"
```

### Domain resolution

The `__()` helper resolves keys in this order:
1. `modules/{module}.{key}` — module-specific
2. `common.{key}` — shared strings
3. Falls back to the key itself if not found

## Adding a New Language

### Step 1: Create the directory

```bash
mkdir -p lang/pt/modules
```

### Step 2: Register the locale

Edit `API/Services/TranslationService.php`, add to `$supportedLocales`:

```php
private array $supportedLocales = ['fr', 'en', 'es', 'de', 'ru', 'nl', 'ar', 'th', 'pt'];
```

### Step 3: Copy and translate system files

```bash
cp lang/en/common.json lang/pt/common.json
cp lang/en/auth.json lang/pt/auth.json
cp lang/en/admin.json lang/pt/admin.json
cp lang/en/errors.json lang/pt/errors.json
```

Edit each file and translate the values (keep the keys unchanged).

### Step 4: Copy and translate module files

```bash
cp -r lang/en/modules/ lang/pt/modules/
```

Translate each file. Prioritize high-traffic modules first: `accueil`, `notes`, `absences`, `messagerie`, `emploi_du_temps`.

### Step 5: Add to login selector

The language selector on the login page (`login/index.php`) automatically detects available locales from the `$supportedLocales` array. Add a flag/label for the new locale in the `$localeFlags` array.

### Step 6: Test

1. Visit the login page and select the new locale
2. Navigate through modules and verify translated strings appear
3. Check for untranslated keys (they appear as raw key names)

## RTL Support (Arabic)

When the locale is `ar`, the system automatically:
- Sets `<html dir="rtl" lang="ar">`
- Loads `assets/css/rtl.css`
- Mirrors the sidebar to the right side
- Reverses flex direction where needed

If adding another RTL language, add it to the RTL check in `templates/shared_header.php`:

```php
$isRtl = in_array($currentLocale, ['ar']);
```

## Date and Number Formatting

The TranslationService provides locale-aware formatting:

```php
$translator = app('translator');

// Date formatting (uses IntlDateFormatter)
echo $translator->formatDate($timestamp);       // "6 avril 2026" (FR)
echo $translator->formatDate($timestamp, 'short'); // "06/04/2026" (FR)

// Number formatting (uses NumberFormatter)
echo $translator->formatNumber(1234.56);         // "1 234,56" (FR) / "1,234.56" (EN)

// Currency
echo $translator->formatCurrency(99.99, 'EUR');  // "99,99 EUR" (FR) / "EUR 99.99" (EN)
```

Requires the PHP `intl` extension. Falls back to basic formatting if unavailable.

## Admin Translation Management

Administrators can manage translations at `admin/systeme/translations.php`:

- **Coverage matrix**: shows percentage completion per locale per module
- **Inline editor**: select a locale and module, edit translation values directly
- **Missing keys report**: lists keys present in FR but missing in other locales
- **Custom overrides**: stored in `custom_translations` DB table, take priority over JSON files

## Translation Coverage Report

The daily maintenance cron (`cron/daily_maintenance.php`) generates a translation coverage report in the logs, showing completion percentage per locale.

## Best Practices

1. **Never hardcode text** — always use `__()` for user-facing strings
2. **Use descriptive keys** — `grade_saved_success` not `msg1`
3. **FR is the source of truth** — always add FR first, then translate
4. **Keep files sorted** — alphabetical key order for easier diffs
5. **Test with RTL** — verify your changes don't break Arabic layout
6. **Include context for translators** — if a string is ambiguous, add a comment key: `"_comment_key": "This appears in the sidebar header"`
