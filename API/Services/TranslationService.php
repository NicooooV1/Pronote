<?php
/**
 * Service d'internationalisation (i18n) pour Fronote
 *
 * Gère le chargement des fichiers de traduction JSON, la résolution de locale,
 * l'interpolation de paramètres et la pluralisation.
 *
 * Structure des fichiers :
 *   /lang/{locale}/common.json     — Chaînes partagées (boutons, erreurs, navigation)
 *   /lang/{locale}/auth.json       — Login, 2FA, reset password
 *   /lang/{locale}/admin.json      — Panel administration
 *   /lang/{locale}/modules/{key}.json — Traductions par module
 *
 * Format JSON (clés en dot-notation) :
 *   { "btn.save": "Enregistrer", "greeting.morning": "Bonjour" }
 *
 * Usage :
 *   __('btn.save')                          → "Enregistrer"
 *   __('greeting.hello', ['name' => 'Jean'])→ "Bonjour, Jean"
 *   _n('items.count', 0)                    → "Aucun élément"
 *   _n('items.count', 1)                    → "1 élément"
 *   _n('items.count', 5)                    → "5 éléments"
 */

namespace API\Services;

class TranslationService
{
    private string $locale;
    private string $fallbackLocale;
    private string $langPath;

    /** @var array<string, array<string, string>> Cache des traductions chargées [locale.domain => translations] */
    private array $loaded = [];

    /** @var string[] Locales supportées */
    private array $supportedLocales = ['fr', 'en'];

    public function __construct(string $langPath, string $defaultLocale = 'fr', string $fallbackLocale = 'fr')
    {
        $this->langPath = rtrim($langPath, '/\\');
        $this->fallbackLocale = $fallbackLocale;
        $this->locale = $this->resolveLocale($defaultLocale);
    }

    /**
     * Résout la locale active selon la chaîne de priorité :
     * 1. Paramètre URL ?lang=xx (stocké en session)
     * 2. Préférence utilisateur (user_settings.locale)
     * 3. Accept-Language du navigateur
     * 4. Défaut établissement
     * 5. Fallback
     */
    private function resolveLocale(string $default): string
    {
        // 1. Paramètre URL (sticky via session)
        if (!empty($_GET['lang']) && in_array($_GET['lang'], $this->supportedLocales, true)) {
            $_SESSION['locale'] = $_GET['lang'];
            return $_GET['lang'];
        }

        // Session déjà définie
        if (!empty($_SESSION['locale']) && in_array($_SESSION['locale'], $this->supportedLocales, true)) {
            return $_SESSION['locale'];
        }

        // 2. Préférence utilisateur en base
        if (!empty($_SESSION['user']['id']) && !empty($_SESSION['user']['type'])) {
            try {
                $pdo = \getPDO();
                $stmt = $pdo->prepare("SELECT langue FROM user_settings WHERE user_id = ? AND user_type = ? LIMIT 1");
                $stmt->execute([$_SESSION['user']['id'], $_SESSION['user']['type']]);
                $dbLocale = $stmt->fetchColumn();
                if ($dbLocale && in_array($dbLocale, $this->supportedLocales, true)) {
                    $_SESSION['locale'] = $dbLocale;
                    return $dbLocale;
                }
            } catch (\Throwable $e) {
                // Silencieux — colonne peut ne pas encore exister
            }
        }

        // 3. Accept-Language du navigateur
        $browserLocale = $this->parseAcceptLanguage();
        if ($browserLocale) {
            return $browserLocale;
        }

        // 4. Défaut établissement
        try {
            $pdo = \getPDO();
            $stmt = $pdo->query("SELECT default_locale FROM etablissement_info LIMIT 1");
            $etabLocale = $stmt->fetchColumn();
            if ($etabLocale && in_array($etabLocale, $this->supportedLocales, true)) {
                return $etabLocale;
            }
        } catch (\Throwable $e) {
            // Silencieux — colonne peut ne pas encore exister
        }

        // 5. Fallback
        return $default;
    }

    /**
     * Parse le header Accept-Language pour trouver une locale supportée
     */
    private function parseAcceptLanguage(): ?string
    {
        $header = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
        if (empty($header)) {
            return null;
        }

        // Extraire les langues avec leur poids (q)
        preg_match_all('/([a-z]{1,8}(?:-[a-z]{1,8})?)\s*(?:;\s*q\s*=\s*([01](?:\.\d{0,3})?))?/i', $header, $matches);

        if (empty($matches[1])) {
            return null;
        }

        $langs = [];
        foreach ($matches[1] as $i => $lang) {
            $q = isset($matches[2][$i]) && $matches[2][$i] !== '' ? (float) $matches[2][$i] : 1.0;
            $shortLang = strtolower(substr($lang, 0, 2));
            if (!isset($langs[$shortLang]) || $q > $langs[$shortLang]) {
                $langs[$shortLang] = $q;
            }
        }

        arsort($langs);

        foreach (array_keys($langs) as $lang) {
            if (in_array($lang, $this->supportedLocales, true)) {
                return $lang;
            }
        }

        return null;
    }

    /**
     * Récupère la locale active
     */
    public function locale(): string
    {
        return $this->locale;
    }

    /**
     * Change la locale active
     */
    public function setLocale(string $locale): void
    {
        if (in_array($locale, $this->supportedLocales, true)) {
            $this->locale = $locale;
            $_SESSION['locale'] = $locale;
        }
    }

    /**
     * Retourne les locales supportées
     */
    public function getSupportedLocales(): array
    {
        return $this->supportedLocales;
    }

    /**
     * Traduit une clé avec interpolation de paramètres.
     *
     * @param string $key     Clé de traduction (ex: 'btn.save', 'modules/notes.title')
     * @param array  $params  Paramètres d'interpolation (ex: ['name' => 'Jean'])
     * @param string|null $locale  Forcer une locale spécifique
     * @return string Texte traduit ou la clé si non trouvée
     */
    public function get(string $key, array $params = [], ?string $locale = null): string
    {
        $locale = $locale ?? $this->locale;

        // Déterminer le domaine depuis la clé
        // Format: "domain.key" ou "modules/notes.key"
        $domain = 'common';
        $translationKey = $key;

        // Si la clé contient un slash, c'est un domaine de module
        if (str_contains($key, '/')) {
            $parts = explode('.', $key, 2);
            $domain = $parts[0]; // ex: "modules/notes"
            $translationKey = $parts[1] ?? $key;
        } else {
            // Détecter le domaine par le préfixe de la clé
            $dotPos = strpos($key, '.');
            if ($dotPos !== false) {
                $prefix = substr($key, 0, $dotPos);
                // Vérifier si un fichier de domaine existe
                if ($this->domainFileExists($prefix, $locale)) {
                    $domain = $prefix;
                    $translationKey = substr($key, $dotPos + 1);
                }
            }
        }

        // Chercher dans la locale demandée
        $translations = $this->loadDomain($domain, $locale);
        $value = $translations[$translationKey] ?? $translations[$key] ?? null;

        // Fallback vers la locale par défaut
        if ($value === null && $locale !== $this->fallbackLocale) {
            $translations = $this->loadDomain($domain, $this->fallbackLocale);
            $value = $translations[$translationKey] ?? $translations[$key] ?? null;
        }

        // Si toujours pas trouvé, essayer dans common
        if ($value === null && $domain !== 'common') {
            $translations = $this->loadDomain('common', $locale);
            $value = $translations[$key] ?? null;
            if ($value === null && $locale !== $this->fallbackLocale) {
                $translations = $this->loadDomain('common', $this->fallbackLocale);
                $value = $translations[$key] ?? null;
            }
        }

        // Clé non trouvée — retourner la clé elle-même
        if ($value === null) {
            return $key;
        }

        // Interpolation des paramètres (:name → valeur)
        if (!empty($params)) {
            foreach ($params as $paramKey => $paramValue) {
                $value = str_replace(':' . $paramKey, (string) $paramValue, $value);
            }
        }

        return $value;
    }

    /**
     * Pluralisation simple.
     *
     * Le fichier de traduction doit contenir des variantes séparées par |
     *   "items.count": "Aucun élément|:count élément|:count éléments"
     *
     * Règles :
     *   count == 0 → variante[0] (si existe, sinon variante[last])
     *   count == 1 → variante[1] (si existe, sinon variante[0])
     *   count > 1  → variante[last]
     *
     * @param string $key    Clé de traduction
     * @param int    $count  Nombre pour la pluralisation
     * @param array  $params Paramètres supplémentaires
     * @param string|null $locale Locale forcée
     * @return string
     */
    public function choice(string $key, int $count, array $params = [], ?string $locale = null): string
    {
        $params['count'] = $count;
        $raw = $this->get($key, [], $locale);

        // Si pas de pipe, c'est une traduction simple
        if (!str_contains($raw, '|')) {
            return $this->interpolate($raw, $params);
        }

        $variants = array_map('trim', explode('|', $raw));
        $variantCount = count($variants);

        if ($count === 0 && $variantCount >= 1) {
            $chosen = $variants[0];
        } elseif ($count === 1 && $variantCount >= 2) {
            $chosen = $variants[1];
        } else {
            $chosen = $variants[$variantCount - 1];
        }

        return $this->interpolate($chosen, $params);
    }

    /**
     * Interpole les paramètres dans une chaîne
     */
    private function interpolate(string $text, array $params): string
    {
        foreach ($params as $key => $value) {
            $text = str_replace(':' . $key, (string) $value, $text);
        }
        return $text;
    }

    /**
     * Vérifie si un fichier de domaine existe pour une locale
     */
    private function domainFileExists(string $domain, string $locale): bool
    {
        $path = $this->langPath . '/' . $locale . '/' . $domain . '.json';
        return file_exists($path);
    }

    /**
     * Charge un fichier de traduction (avec cache)
     *
     * @return array<string, string>
     */
    private function loadDomain(string $domain, string $locale): array
    {
        $cacheKey = $locale . '.' . $domain;

        if (isset($this->loaded[$cacheKey])) {
            return $this->loaded[$cacheKey];
        }

        $path = $this->langPath . '/' . $locale . '/' . $domain . '.json';

        if (!file_exists($path)) {
            $this->loaded[$cacheKey] = [];
            return [];
        }

        $content = file_get_contents($path);
        $translations = json_decode($content, true);

        if (!is_array($translations)) {
            error_log("TranslationService: Invalid JSON in {$path}");
            $this->loaded[$cacheKey] = [];
            return [];
        }

        $this->loaded[$cacheKey] = $translations;
        return $translations;
    }

    /**
     * Vide le cache des traductions (utile après modification des fichiers)
     */
    public function clearCache(): void
    {
        $this->loaded = [];
    }
}
