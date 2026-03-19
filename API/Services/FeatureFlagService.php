<?php
/**
 * Service de Feature Flags pour le système multi-établissement
 *
 * Permet d'activer/désactiver des fonctionnalités selon le type d'établissement
 * (collège, lycée, études supérieures) sans modifier le code des modules.
 *
 * Usage :
 *   if (app('features')->isEnabled('stages.enabled')) { ... }
 *   if (app('features')->isEnabledForType('alternance.enabled', 'superieur')) { ... }
 */

namespace API\Services;

class FeatureFlagService
{
    private \PDO $pdo;

    /** @var array|null Cache des flags */
    private ?array $flags = null;

    /** @var string|null Type d'établissement courant */
    private ?string $currentType = null;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Charge et met en cache tous les feature flags.
     *
     * @return array<string, array>
     */
    private function loadFlags(): array
    {
        if ($this->flags !== null) {
            return $this->flags;
        }

        $this->flags = [];

        try {
            $stmt = $this->pdo->query("SELECT * FROM feature_flags");
            if ($stmt === false) {
                return $this->flags;
            }

            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $row['establishment_types'] = !empty($row['establishment_types'])
                    ? json_decode($row['establishment_types'], true)
                    : null;
                $row['config'] = !empty($row['config'])
                    ? json_decode($row['config'], true)
                    : null;
                $this->flags[$row['flag_key']] = $row;
            }
        } catch (\Throwable $e) {
            // Table peut ne pas encore exister
            error_log("FeatureFlagService: " . $e->getMessage());
        }

        return $this->flags;
    }

    /**
     * Récupère le type d'établissement courant.
     */
    private function getEstablishmentType(): string
    {
        if ($this->currentType !== null) {
            return $this->currentType;
        }

        try {
            $stmt = $this->pdo->query("SELECT type FROM etablissement_info LIMIT 1");
            $this->currentType = $stmt->fetchColumn() ?: 'college';
        } catch (\Throwable $e) {
            $this->currentType = 'college';
        }

        return $this->currentType;
    }

    /**
     * Vérifie si un feature flag est actif pour l'établissement courant.
     *
     * Un flag est considéré actif si :
     * 1. Il existe et enabled = 1
     * 2. Son establishment_types est null (tous types) OU contient le type courant
     *
     * Si le flag n'existe pas en base, retourne false.
     */
    public function isEnabled(string $flagKey): bool
    {
        return $this->isEnabledForType($flagKey, $this->getEstablishmentType());
    }

    /**
     * Vérifie si un feature flag est actif pour un type d'établissement donné.
     */
    public function isEnabledForType(string $flagKey, string $establishmentType): bool
    {
        $flags = $this->loadFlags();
        $flag = $flags[$flagKey] ?? null;

        if ($flag === null) {
            return false;
        }

        if (empty($flag['enabled'])) {
            return false;
        }

        // null = tous types
        if ($flag['establishment_types'] === null) {
            return true;
        }

        return in_array($establishmentType, $flag['establishment_types'], true);
    }

    /**
     * Retourne tous les feature flags.
     *
     * @return array<string, array>
     */
    public function getAll(): array
    {
        return $this->loadFlags();
    }

    /**
     * Retourne tous les flags actifs pour le type d'établissement courant.
     *
     * @return array<string, array>
     */
    public function getEnabled(): array
    {
        $type = $this->getEstablishmentType();
        $flags = $this->loadFlags();

        return array_filter($flags, function ($flag) use ($type) {
            if (empty($flag['enabled'])) {
                return false;
            }
            if ($flag['establishment_types'] === null) {
                return true;
            }
            return in_array($type, $flag['establishment_types'], true);
        });
    }

    /**
     * Récupère la configuration d'un flag.
     *
     * @return mixed|null
     */
    public function getConfig(string $flagKey, string $configKey = null)
    {
        $flags = $this->loadFlags();
        $flag = $flags[$flagKey] ?? null;

        if ($flag === null || $flag['config'] === null) {
            return null;
        }

        if ($configKey === null) {
            return $flag['config'];
        }

        return $flag['config'][$configKey] ?? null;
    }

    /**
     * Vide le cache (après modification en base).
     */
    public function clearCache(): void
    {
        $this->flags = null;
        $this->currentType = null;
    }
}
