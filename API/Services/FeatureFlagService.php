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
            $stmt = $this->pdo->query("SELECT type FROM etablissements LIMIT 1");
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

    // ─── Write operations ────────────────────────────────────────

    /**
     * Enable or disable a feature flag.
     */
    public function setEnabled(string $flagKey, bool $enabled): bool
    {
        try {
            $stmt = $this->pdo->prepare("UPDATE feature_flags SET enabled = ? WHERE flag_key = ?");
            $result = $stmt->execute([(int)$enabled, $flagKey]);
            $this->clearCache();
            return $result && $stmt->rowCount() > 0;
        } catch (\Throwable $e) {
            error_log("FeatureFlagService::setEnabled error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Create a new feature flag.
     */
    public function create(string $flagKey, string $description, bool $enabled = false, ?array $establishmentTypes = null, ?array $config = null): bool
    {
        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO feature_flags (flag_key, description, enabled, establishment_types, config)
                 VALUES (?, ?, ?, ?, ?)"
            );
            $result = $stmt->execute([
                $flagKey,
                $description,
                (int)$enabled,
                $establishmentTypes !== null ? json_encode($establishmentTypes) : null,
                $config !== null ? json_encode($config, JSON_UNESCAPED_UNICODE) : null,
            ]);
            $this->clearCache();
            return $result;
        } catch (\Throwable $e) {
            error_log("FeatureFlagService::create error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update a feature flag's metadata.
     */
    public function update(string $flagKey, array $data): bool
    {
        try {
            $sets = [];
            $params = [];

            if (array_key_exists('description', $data)) {
                $sets[] = 'description = ?';
                $params[] = $data['description'];
            }
            if (array_key_exists('enabled', $data)) {
                $sets[] = 'enabled = ?';
                $params[] = (int)$data['enabled'];
            }
            if (array_key_exists('establishment_types', $data)) {
                $sets[] = 'establishment_types = ?';
                $params[] = $data['establishment_types'] !== null ? json_encode($data['establishment_types']) : null;
            }
            if (array_key_exists('config', $data)) {
                $sets[] = 'config = ?';
                $params[] = $data['config'] !== null ? json_encode($data['config'], JSON_UNESCAPED_UNICODE) : null;
            }

            if (empty($sets)) return false;

            $params[] = $flagKey;
            $sql = "UPDATE feature_flags SET " . implode(', ', $sets) . " WHERE flag_key = ?";
            $stmt = $this->pdo->prepare($sql);
            $result = $stmt->execute($params);
            $this->clearCache();
            return $result && $stmt->rowCount() > 0;
        } catch (\Throwable $e) {
            error_log("FeatureFlagService::update error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete a feature flag.
     */
    public function delete(string $flagKey): bool
    {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM feature_flags WHERE flag_key = ?");
            $result = $stmt->execute([$flagKey]);
            $this->clearCache();
            return $result && $stmt->rowCount() > 0;
        } catch (\Throwable $e) {
            error_log("FeatureFlagService::delete error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update only the config JSON of a flag.
     */
    public function updateConfig(string $flagKey, array $config): bool
    {
        return $this->update($flagKey, ['config' => $config]);
    }

    /**
     * Update the establishment types a flag applies to.
     * Pass null to apply to all types.
     */
    public function updateEstablishmentTypes(string $flagKey, ?array $types): bool
    {
        return $this->update($flagKey, ['establishment_types' => $types]);
    }

    /**
     * Batch enable/disable flags.
     * @param array $states ['flag_key' => bool, ...]
     * @return int Number of flags updated
     */
    public function batchSetEnabled(array $states): int
    {
        $count = 0;
        foreach ($states as $key => $enabled) {
            if ($this->setEnabled($key, (bool)$enabled)) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Get flags grouped by module prefix (e.g. 'stages.enabled' → module 'stages').
     */
    public function getGroupedByModule(): array
    {
        $flags = $this->loadFlags();
        $grouped = [];
        foreach ($flags as $key => $flag) {
            $parts = explode('.', $key, 2);
            $module = $parts[0];
            $grouped[$module][] = $flag;
        }
        ksort($grouped);
        return $grouped;
    }
}
