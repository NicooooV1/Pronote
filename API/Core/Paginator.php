<?php
declare(strict_types=1);

namespace API\Core;

use PDO;

/**
 * Paginator — Utility for standardized pagination across all services.
 */
class Paginator
{
    /**
     * Execute a paginated query and return results with metadata.
     *
     * @return array{data: array, meta: array{page: int, per_page: int, total: int, total_pages: int}}
     */
    public static function paginate(PDO $pdo, string $sql, array $params, int $page = 1, int $perPage = 20): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        // Count total results
        $countSql = "SELECT COUNT(*) FROM ({$sql}) AS _paginated_count";
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        // Fetch current page
        $dataSql = "{$sql} LIMIT :_pg_limit OFFSET :_pg_offset";
        $dataStmt = $pdo->prepare($dataSql);
        foreach ($params as $key => $value) {
            $dataStmt->bindValue($key, $value);
        }
        $dataStmt->bindValue(':_pg_limit', $perPage, PDO::PARAM_INT);
        $dataStmt->bindValue(':_pg_offset', $offset, PDO::PARAM_INT);
        $dataStmt->execute();
        $data = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'data' => $data,
            'meta' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => (int) ceil($total / $perPage),
            ],
        ];
    }

    /**
     * Simple offset/limit helper for manual pagination.
     */
    public static function getOffsetLimit(int $page, int $perPage = 20): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        return [($page - 1) * $perPage, $perPage];
    }
}
