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

    public function __construct(PDO $pdo, $table)
    {
        $this->pdo = $pdo;
        $this->table = $table;
    }

    /**
     * Sélectionne les colonnes
     */
    public function select($columns = ['*'])
    {
        $this->selects = is_array($columns) ? $columns : func_get_args();
        return $this;
    }

    /**
     * Ajoute une clause WHERE
     */
    public function where($column, $operator, $value = null)
    {
        if (is_null($value)) {
            $value = $operator;
            $operator = '=';
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
     * Ajoute un ORDER BY
     */
    public function orderBy($column, $direction = 'asc')
    {
        $this->orders[] = ['column' => $column, 'direction' => $direction];
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
     * Construit la requête SQL
     */
    protected function toSql()
    {
        $sql = 'SELECT ' . implode(', ', $this->selects);
        $sql .= ' FROM ' . $this->table;

        if (!empty($this->wheres)) {
            $sql .= ' WHERE ';
            $conditions = [];
            
            foreach ($this->wheres as $where) {
                $conditions[] = $where['column'] . ' ' . $where['operator'] . ' ?';
            }
            
            $sql .= implode(' AND ', $conditions);
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
        $columns = array_keys($data);
        $placeholders = array_fill(0, count($data), '?');

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
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
            $sets[] = "$column = ?";
            $bindings[] = $value;
        }

        $bindings = array_merge($bindings, $this->bindings);

        $sql = 'UPDATE ' . $this->table . ' SET ' . implode(', ', $sets);

        if (!empty($this->wheres)) {
            $sql .= ' WHERE ';
            $conditions = [];
            
            foreach ($this->wheres as $where) {
                $conditions[] = $where['column'] . ' ' . $where['operator'] . ' ?';
            }
            
            $sql .= implode(' AND ', $conditions);
        }

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($bindings);
    }

    /**
     * Supprime des données
     */
    public function delete()
    {
        $sql = 'DELETE FROM ' . $this->table;

        if (!empty($this->wheres)) {
            $sql .= ' WHERE ';
            $conditions = [];
            
            foreach ($this->wheres as $where) {
                $conditions[] = $where['column'] . ' ' . $where['operator'] . ' ?';
            }
            
            $sql .= implode(' AND ', $conditions);
        }

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($this->bindings);
    }
}
