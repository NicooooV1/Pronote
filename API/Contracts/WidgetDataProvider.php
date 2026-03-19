<?php
/**
 * Interface pour les fournisseurs de données de widgets
 *
 * Chaque module qui déclare un widget dans son module.json doit fournir
 * une classe implémentant cette interface. Le DashboardService l'utilise
 * pour récupérer les données du widget sans connaître la logique métier du module.
 *
 * Usage dans module.json :
 *   "widgets": [{
 *       "key": "dernieres_notes",
 *       "data_provider": "includes/NoteWidgetProvider.php",
 *       "template": "widgets/dernieres_notes.php",
 *       ...
 *   }]
 *
 * Le fichier data_provider doit contenir une classe implémentant cette interface.
 * Le nom de la classe doit correspondre au nom du fichier (PSR-4 simplifié).
 */

namespace API\Contracts;

interface WidgetDataProvider
{
    /**
     * Retourne les données du widget pour l'utilisateur donné.
     *
     * @param int         $userId    ID de l'utilisateur
     * @param string      $userType  Type de l'utilisateur (eleve, professeur, etc.)
     * @param array|null  $config    Configuration personnalisée du widget (depuis user_dashboard_config.config)
     * @return array Données à passer au template du widget
     */
    public function getData(int $userId, string $userType, ?array $config = null): array;

    /**
     * Intervalle de rafraîchissement automatique en secondes.
     * Retourner 0 pour désactiver le rafraîchissement auto.
     *
     * @return int Secondes entre chaque rafraîchissement (0 = désactivé)
     */
    public function getRefreshInterval(): int;
}
