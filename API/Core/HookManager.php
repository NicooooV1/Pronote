<?php
/**
 * Système de hooks pour Fronote
 *
 * Permet aux modules d'enregistrer des callbacks sur des événements système
 * et d'étendre le comportement du core sans le modifier.
 *
 * Usage :
 *   // Enregistrer un hook
 *   app('hooks')->register('user.login', function($user) {
 *       // Logger la connexion
 *   }, priority: 10);
 *
 *   // Déclencher un hook
 *   app('hooks')->fire('user.login', $user);
 *
 *   // Filtrer une valeur
 *   $title = app('hooks')->filter('page.title', $defaultTitle, $context);
 */

namespace API\Core;

class HookManager
{
    /** @var array<string, array<int, callable[]>> [event => [priority => [callbacks]]] */
    private array $hooks = [];

    /**
     * Enregistre un callback pour un événement.
     *
     * @param string   $event    Nom de l'événement (ex: 'user.login', 'module.install')
     * @param callable $callback Fonction à exécuter
     * @param int      $priority Priorité (plus bas = exécuté en premier, défaut 10)
     */
    public function register(string $event, callable $callback, int $priority = 10): void
    {
        $this->hooks[$event][$priority][] = $callback;
    }

    /**
     * Déclenche un événement et exécute tous les callbacks enregistrés.
     *
     * @param string $event Nom de l'événement
     * @param mixed  ...$args Arguments passés aux callbacks
     */
    public function fire(string $event, mixed ...$args): void
    {
        if (!isset($this->hooks[$event])) {
            return;
        }

        $hooks = $this->hooks[$event];
        ksort($hooks); // Trier par priorité

        foreach ($hooks as $callbacks) {
            foreach ($callbacks as $callback) {
                try {
                    call_user_func_array($callback, $args);
                } catch (\Throwable $e) {
                    error_log("HookManager: Error in hook '{$event}': " . $e->getMessage());
                }
            }
        }
    }

    /**
     * Filtre une valeur à travers tous les callbacks enregistrés.
     * Chaque callback reçoit la valeur courante et doit retourner la valeur modifiée.
     *
     * @param string $event Nom du filtre
     * @param mixed  $value Valeur initiale
     * @param mixed  ...$args Arguments additionnels passés aux callbacks
     * @return mixed Valeur filtrée
     */
    public function filter(string $event, mixed $value, mixed ...$args): mixed
    {
        if (!isset($this->hooks[$event])) {
            return $value;
        }

        $hooks = $this->hooks[$event];
        ksort($hooks);

        foreach ($hooks as $callbacks) {
            foreach ($callbacks as $callback) {
                try {
                    $value = call_user_func($callback, $value, ...$args);
                } catch (\Throwable $e) {
                    error_log("HookManager: Error in filter '{$event}': " . $e->getMessage());
                }
            }
        }

        return $value;
    }

    /**
     * Vérifie si un événement a des hooks enregistrés.
     */
    public function has(string $event): bool
    {
        return !empty($this->hooks[$event]);
    }

    /**
     * Supprime tous les hooks pour un événement.
     */
    public function clear(string $event): void
    {
        unset($this->hooks[$event]);
    }

    /**
     * Supprime tous les hooks enregistrés.
     */
    public function clearAll(): void
    {
        $this->hooks = [];
    }
}
