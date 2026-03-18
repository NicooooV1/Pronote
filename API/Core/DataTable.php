<?php
declare(strict_types=1);

namespace API\Core;

use PDO;

/**
 * DataTable – Composant générique pour pagination, tri et filtrage.
 *
 * Usage côté PHP :
 *   $dt = new DataTable($pdo, 'absences');
 *   $dt->setColumns(['id', 'eleve_id', 'motif', 'date_debut', 'statut']);
 *   $dt->setSearchable(['motif']);
 *   $dt->setSortable(['date_debut', 'statut']);
 *   $dt->setFilters(['statut' => 'validée']);
 *   $dt->setJoins("LEFT JOIN eleves e ON e.id = absences.eleve_id");
 *   $result = $dt->fetch($_GET);
 *   // $result = ['data' => [...], 'total' => 150, 'page' => 1, 'perPage' => 20, 'totalPages' => 8, 'sort' => ...]
 *
 * Usage côté HTML :
 *   <?= DataTable::renderPagination($result) ?> 
 */
class DataTable
{
    private PDO $pdo;
    private string $table;
    private string $selectColumns = '*';
    private array $columns = [];
    private array $searchable = [];
    private array $sortable = [];
    private array $filters = [];
    private array $whereRaw = [];
    private array $bindings = [];
    private string $joins = '';
    private int $defaultPerPage = 20;
    private int $maxPerPage = 100;

    public function __construct(PDO $pdo, string $table)
    {
        $this->pdo = $pdo;
        $this->table = $table;
    }

    public function setColumns(array $columns): self
    {
        $this->columns = $columns;
        return $this;
    }

    public function setSelect(string $select): self
    {
        $this->selectColumns = $select;
        return $this;
    }

    public function setSearchable(array $fields): self
    {
        $this->searchable = $fields;
        return $this;
    }

    public function setSortable(array $fields): self
    {
        $this->sortable = $fields;
        return $this;
    }

    public function setFilters(array $filters): self
    {
        $this->filters = $filters;
        return $this;
    }

    public function setJoins(string $joins): self
    {
        $this->joins = $joins;
        return $this;
    }

    public function addWhere(string $clause, array $params = []): self
    {
        $this->whereRaw[] = $clause;
        $this->bindings = array_merge($this->bindings, $params);
        return $this;
    }

    public function setDefaultPerPage(int $perPage): self
    {
        $this->defaultPerPage = $perPage;
        return $this;
    }

    /**
     * Exécute la requête paginée/triée/filtrée
     * @param array $params GET parameters
     */
    public function fetch(array $params = []): array
    {
        $page    = max(1, (int)($params['page'] ?? 1));
        $perPage = min($this->maxPerPage, max(1, (int)($params['per_page'] ?? $this->defaultPerPage)));
        $sort    = $params['sort'] ?? null;
        $order   = strtoupper($params['order'] ?? 'ASC');
        $search  = trim($params['search'] ?? '');

        // ── Build WHERE ──
        $where = [];
        $bind  = $this->bindings;

        // Filtres exacts
        foreach ($this->filters as $col => $val) {
            if ($val !== null && $val !== '') {
                $where[] = "`{$col}` = ?";
                $bind[]  = $val;
            }
        }

        // Filtres dynamiques à partir des GET params (colonne:valeur)
        foreach ($params as $key => $val) {
            if ($val !== '' && $val !== null && in_array($key, $this->columns, true)) {
                $where[] = "`{$key}` = ?";
                $bind[]  = $val;
            }
        }

        // Recherche textuelle
        if ($search !== '' && !empty($this->searchable)) {
            $searchClauses = [];
            foreach ($this->searchable as $field) {
                $searchClauses[] = "`{$field}` LIKE ?";
                $bind[] = "%{$search}%";
            }
            $where[] = '(' . implode(' OR ', $searchClauses) . ')';
        }

        // Clauses WHERE brutes
        foreach ($this->whereRaw as $raw) {
            $where[] = $raw;
        }

        $whereSQL = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        // ── ORDER BY ──
        $orderSQL = '';
        if ($sort && in_array($sort, $this->sortable, true)) {
            $order = in_array($order, ['ASC', 'DESC'], true) ? $order : 'ASC';
            $orderSQL = "ORDER BY `{$sort}` {$order}";
        }

        // ── COUNT total ──
        $countSQL = "SELECT COUNT(*) FROM `{$this->table}` {$this->joins} {$whereSQL}";
        $stmt = $this->pdo->prepare($countSQL);
        $stmt->execute($bind);
        $total = (int) $stmt->fetchColumn();

        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $perPage;

        // ── SELECT data ──
        $dataSQL = "SELECT {$this->selectColumns} FROM `{$this->table}` {$this->joins} {$whereSQL} {$orderSQL} LIMIT {$perPage} OFFSET {$offset}";
        $stmt = $this->pdo->prepare($dataSQL);
        $stmt->execute($bind);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'data'       => $data,
            'total'      => $total,
            'page'       => $page,
            'perPage'    => $perPage,
            'totalPages' => $totalPages,
            'sort'       => $sort,
            'order'      => $order,
            'search'     => $search,
        ];
    }

    // ═══════════════ RENDU HTML ═══════════════

    /**
     * Rendu HTML de la pagination
     */
    public static function renderPagination(array $result, string $baseUrl = ''): string
    {
        $page = $result['page'];
        $totalPages = $result['totalPages'];
        $total = $result['total'];

        if ($totalPages <= 1) {
            return '<div class="dt-pagination-info">Affichage de ' . count($result['data']) . " résultat(s)</div>";
        }

        // Construire les params existants
        $params = $_GET;
        unset($params['page']);
        $qsBase = $baseUrl ?: strtok($_SERVER['REQUEST_URI'] ?? '', '?');

        $buildUrl = function (int $p) use ($qsBase, $params): string {
            $params['page'] = $p;
            return htmlspecialchars($qsBase . '?' . http_build_query($params));
        };

        $html = '<nav class="dt-pagination" aria-label="Pagination">';
        $html .= '<div class="dt-pagination-info">';
        $start = (($page - 1) * $result['perPage']) + 1;
        $end   = min($page * $result['perPage'], $total);
        $html .= "Affichage de {$start}-{$end} sur {$total} résultats";
        $html .= '</div>';
        $html .= '<ul class="dt-pagination-list">';

        // Bouton précédent
        if ($page > 1) {
            $html .= '<li><a href="' . $buildUrl($page - 1) . '" class="dt-page-link">&laquo; Précédent</a></li>';
        }

        // Pages
        $range = self::paginationRange($page, $totalPages, 5);
        foreach ($range as $p) {
            if ($p === '...') {
                $html .= '<li class="dt-page-ellipsis">…</li>';
            } elseif ($p === $page) {
                $html .= '<li><span class="dt-page-link dt-page-active">' . $p . '</span></li>';
            } else {
                $html .= '<li><a href="' . $buildUrl((int)$p) . '" class="dt-page-link">' . $p . '</a></li>';
            }
        }

        // Bouton suivant
        if ($page < $totalPages) {
            $html .= '<li><a href="' . $buildUrl($page + 1) . '" class="dt-page-link">Suivant &raquo;</a></li>';
        }

        $html .= '</ul></nav>';
        return $html;
    }

    /**
     * Rendu HTML d'un en-tête de colonne triable
     */
    public static function renderSortHeader(string $label, string $column, array $result): string
    {
        $params = $_GET;
        $currentSort  = $result['sort'] ?? '';
        $currentOrder = $result['order'] ?? 'ASC';

        $newOrder = ($currentSort === $column && $currentOrder === 'ASC') ? 'DESC' : 'ASC';
        $params['sort']  = $column;
        $params['order'] = $newOrder;

        $url = htmlspecialchars(strtok($_SERVER['REQUEST_URI'] ?? '', '?') . '?' . http_build_query($params));

        $icon = '';
        if ($currentSort === $column) {
            $icon = $currentOrder === 'ASC'
                ? ' <i class="fas fa-sort-up"></i>'
                : ' <i class="fas fa-sort-down"></i>';
        } else {
            $icon = ' <i class="fas fa-sort" style="opacity:.3"></i>';
        }

        return '<a href="' . $url . '" class="dt-sort-link">' . htmlspecialchars($label) . $icon . '</a>';
    }

    /**
     * Rendu HTML du formulaire de recherche
     */
    public static function renderSearchBar(array $result, string $placeholder = 'Rechercher…'): string
    {
        $search = htmlspecialchars($result['search'] ?? '');
        $action = htmlspecialchars(strtok($_SERVER['REQUEST_URI'] ?? '', '?'));

        $html = '<form method="GET" action="' . $action . '" class="dt-search-form">';
        // Conserver les params existants sauf search et page
        foreach ($_GET as $k => $v) {
            if ($k !== 'search' && $k !== 'page') {
                $html .= '<input type="hidden" name="' . htmlspecialchars($k) . '" value="' . htmlspecialchars($v) . '">';
            }
        }
        $html .= '<div class="dt-search-group">';
        $html .= '<i class="fas fa-search dt-search-icon"></i>';
        $html .= '<input type="text" name="search" value="' . $search . '" placeholder="' . htmlspecialchars($placeholder) . '" class="dt-search-input">';
        if ($search) {
            $html .= '<a href="' . $action . '" class="dt-search-clear"><i class="fas fa-times"></i></a>';
        }
        $html .= '</div>';
        $html .= '<button type="submit" class="btn btn-secondary dt-search-btn">Rechercher</button>';
        $html .= '</form>';
        return $html;
    }

    /**
     * Rendu du sélecteur "par page"
     */
    public static function renderPerPageSelector(array $result, array $options = [10, 20, 50, 100]): string
    {
        $current = $result['perPage'];
        $params = $_GET;
        $base = strtok($_SERVER['REQUEST_URI'] ?? '', '?');

        $html = '<div class="dt-per-page">';
        $html .= '<label>Afficher ';
        $html .= '<select onchange="window.location.href=this.value" class="dt-per-page-select">';
        foreach ($options as $opt) {
            $params['per_page'] = $opt;
            $params['page'] = 1;
            $url = htmlspecialchars($base . '?' . http_build_query($params));
            $sel = ($opt == $current) ? ' selected' : '';
            $html .= "<option value=\"{$url}\"{$sel}>{$opt}</option>";
        }
        $html .= '</select>';
        $html .= ' résultats</label></div>';
        return $html;
    }

    /**
     * Calcul de la plage de pagination
     */
    private static function paginationRange(int $current, int $total, int $window = 5): array
    {
        if ($total <= $window + 4) {
            return range(1, $total);
        }

        $pages = [1];
        $start = max(2, $current - (int)floor($window / 2));
        $end   = min($total - 1, $current + (int)floor($window / 2));

        if ($start > 2) $pages[] = '...';
        for ($i = $start; $i <= $end; $i++) $pages[] = $i;
        if ($end < $total - 1) $pages[] = '...';
        $pages[] = $total;

        return $pages;
    }
}
