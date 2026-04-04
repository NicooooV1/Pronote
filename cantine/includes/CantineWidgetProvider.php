<?php

declare(strict_types=1);

namespace Cantine\Widgets;

use API\Contracts\WidgetDataProvider;

class CantineWidgetProvider implements WidgetDataProvider
{
    public function getData(int $userId, string $userType, ?array $config = null): array
    {
        $pdo = app('db')?->getConnection();
        if (!$pdo) {
            return ['menu' => null, 'date' => null];
        }

        // Menu du jour ou du prochain jour ouvré
        $stmt = $pdo->prepare(
            "SELECT date_menu, entree, plat_principal, accompagnement, dessert, remarques
             FROM menus_cantine
             WHERE date_menu >= CURDATE()
             ORDER BY date_menu ASC
             LIMIT 1"
        );
        $stmt->execute();
        $menu = $stmt->fetch(\PDO::FETCH_ASSOC);

        return [
            'menu' => $menu,
            'date' => $menu['date_menu'] ?? null,
        ];
    }

    public function getRefreshInterval(): int
    {
        return 3600;
    }
}
