<?php
namespace API\Database;

use PDO;

/**
 * Query Builder pour simplifier les requêtes SQL
 */
class QueryBuilder
{
    protected $pdo;
    protected $table;
    protected $wheres = [];
    protected $bindings = [];
    protected $selects = ['*'];
    protected $orders = [];
    protected $limit;
    protected $offset;

    /** Regex pour valider les noms de colonnes/tables (lettres, chiffres, underscores, points pour alias) */
    private const IDENTIFIER_PATTERN = '/^[a-zA-Z_][a-zA-Z0-9_.]*$/';

    public function __construct(PDO $pdo, $table)
    {
        $this->assertValidIdentifier($table);
        $this->pdo = $pdo;
        $this->table = $table;
    }

    /**
     * Valide qu'un identifiant SQL (colonne, table) est sûr.
     * Empêche l'injection SQL via les noms de colonnes.
     */
    private function assertValidIdentifier(string $identifier): void
    {
        if (!preg_match(self::IDENTIFIER_PATTERN, $identifier)) {
            throw new \InvalidArgumentException(
                "Identifiant SQL invalide : " . substr($identifier, 0, 50)
            );
        }
    }

    /**
     * Sélectionne les colonnes
     */
    public function select($columns = ['*'])
    {
        $cols = is_array($columns) ? $columns : func_get_args();
        foreach ($cols as $col) {
            if ($col !== '*') {
                $this->assertValidIdentifier($col);
            }
        }
        $this->selects = $cols;
        return $this;
    }

    /**
     * Ajoute une clause WHERE
     */
    private const VALID_OPERATORS = ['=', '!=', '<', '>', '<=', '>=', '<>', 'LIKE', 'NOT LIKE', 'IS', 'IS NOT'];

    public function where($column, $operator, $value = null)
    {
        $this->assertValidIdentifier($column);
        if (is_null($value)) {
            $value = $operator;
            $operator = '=';
        }
        if (!in_array(strtoupper($operator), self::VALID_OPERATORS, true)) {
            throw new \InvalidArgumentException("Opérateur SQL invalide : " . $operator);
        }

        $this->wheres[] = [
            'type' => 'basic',
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => 'and'
        ];

        $this->bindings[] = $value;

        return $this;
    }

    /**
     * Ajoute une clause WHERE IN
     */
    public function whereIn($column, array $values)
    {
        $this->assertValidIdentifier($column);
        if (empty($values)) {
            return $this;
        }

        $this->wheres[] = [
            'type' => 'in',
            'column' => $column,
            'values' => $values,
            'boolean' => 'and'
        ];

        $this->bindings = array_merge($this->bindings, $values);

        return $this;
    }

    /**
     * Ajoute une clause WHERE NULL
     */
    public function whereNull($column)
    {
        $this->assertValidIdentifier($column);
        $this->wheres[] = [
            'type' => 'null',
            'column' => $column,
            'boolean' => 'and'
        ];

        return $this;
    }

    /**
     * Ajoute une clause WHERE NOT NULL
     */
    public function whereNotNull($column)
    {
        $this->assertValidIdentifier($column);
        $this->wheres[] = [
            'type' => 'not_null',
            'column' => $column,
            'boolean' => 'and'
        ];

        return $this;
    }

    /**
     * Ajoute un ORDER BY
     */
    public function orderBy($column, $direction = 'asc')
    {
        $this->assertValidIdentifier($column);
        $dir = strtoupper($direction);
        if (!in_array($dir, ['ASC', 'DESC'], true)) {
            throw new \InvalidArgumentException("Direction de tri invalide : " . $direction);
        }
        $this->orders[] = ['column' => $column, 'direction' => $dir];
        return $this;
    }

    /**
     * Ajoute une limite
     */
    public function limit($value)
    {
        $this->limit = $value;
        return $this;
    }

    /**
     * Ajoute un offset
     */
    public function offset($value)
    {
        $this->offset = $value;
        return $this;
    }

    /**
     * Exécute la requête et retourne tous les résultats
     */
    public function get()
    {
        $sql = $this->toSql();
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($this->bindings);
        return $stmt->fetchAll();
    }

    /**
     * Retourne le premier résultat
     */
    public function first()
    {
        $this->limit(1);
        $results = $this->get();
        return !empty($results) ? $results[0] : null;
    }

    /**
     * Compte les résultats
     */
    public function count()
    {
        $sql = 'SELECT COUNT(*) as count FROM ' . $this->table;

        if (!empty($this->wheres)) {
            $sql .= ' WHERE ' . $this->buildWhereClause();
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($this->bindings);
        $result = $stmt->fetch();

        return (int)($result['count'] ?? 0);
    }

    /**
     * Construit la clause WHERE
     */
    protected function buildWhereClause()
    {
        $conditions = [];

        foreach ($this->wheres as $where) {
            switch ($where['type']) {
                case 'basic':
                    $conditions[] = $where['column'] . ' ' . $where['operator'] . ' ?';
                    break;
                case 'in':
                    $placeholders = implode(', ', array_fill(0, count($where['values']), '?'));
                    $conditions[] = $where['column'] . ' IN (' . $placeholders . ')';
                    break;
                case 'null':
                    $conditions[] = $where['column'] . ' IS NULL';
                    break;
                case 'not_null':
                    $conditions[] = $where['column'] . ' IS NOT NULL';
                    break;
            }
        }

        return implode(' AND ', $conditions);
    }

    /**
     * Construit la requête SQL (amélioration)
     */
    public function toSql()
    {
        $sql = 'SELECT ' . implode(', ', $this->selects);
        $sql .= ' FROM ' . $this->table;

        if (!empty($this->wheres)) {
            $sql .= ' WHERE ' . $this->buildWhereClause();
        }

        if (!empty($this->orders)) {
            $sql .= ' ORDER BY ';
            $orders = [];
            
            foreach ($this->orders as $order) {
                $orders[] = $order['column'] . ' ' . strtoupper($order['direction']);
            }
            
            $sql .= implode(', ', $orders);
        }

        if ($this->limit) {
            $sql .= ' LIMIT ' . $this->limit;
        }

        if ($this->offset) {
            $sql .= ' OFFSET ' . $this->offset;
        }

        return $sql;
    }

    /**
     * Insère des données
     */
    public function insert(array $data)
    {
        foreach (array_keys($data) as $col) {
            $this->assertValidIdentifier($col);
        }
        $columns = array_map(fn($c) => "`$c`", array_keys($data));
        $placeholders = array_fill(0, count($data), '?');

        $sql = sprintf(
            'INSERT INTO `%s` (%s) VALUES (%s)',
            $this->table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute(array_values($data));
    }

    /**
     * Met à jour des données
     */
    public function update(array $data)
    {
        $sets = [];
        $bindings = [];

        foreach ($data as $column => $value) {
            $this->assertValidIdentifier($column);
            $sets[] = "`$column` = ?";
            $bindings[] = $value;
        }

        $bindings = array_merge($bindings, $this->bindings);

        $sql = 'UPDATE `' . $this->table . '` SET ' . implode(', ', $sets);

        if (!empty($this->wheres)) {
            $sql .= ' WHERE ' . $this->buildWhereClause();
        }

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($bindings);
    }

    /**
     * Supprime des données
     */
    public function delete()
    {
        $sql = 'DELETE FROM `' . $this->table . '`';

        if (!empty($this->wheres)) {
            $sql .= ' WHERE ' . $this->buildWhereClause();
        }

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($this->bindings);
    }
}
