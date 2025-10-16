<?php
/**
 * Query Builder - Fluent SQL Query Builder
 * Pattern : Fluent Interface / Method Chaining
 */

namespace Pronote\Database;

class QueryBuilder {
    protected $pdo;
    protected $table;
    protected $selects = ['*'];
    protected $wheres = [];
    protected $joins = [];
    protected $orderBys = [];
    protected $groupBys = [];
    protected $havings = [];
    protected $limit;
    protected $offset;
    protected $bindings = [];
    protected $operators = ['=', '<', '>', '<=', '>=', '<>', '!=', 'LIKE', 'NOT LIKE', 'IN', 'NOT IN'];
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Définit la table
     */
    public function table($table) {
        $this->table = $this->sanitizeIdentifier($table);
        return $this;
    }
    
    /**
     * Alias de table()
     */
    public function from($table) {
        return $this->table($table);
    }
    
    /**
     * Définit les colonnes à sélectionner
     */
    public function select(...$columns) {
        if (empty($columns)) {
            $columns = ['*'];
        }
        
        $this->selects = [];
        foreach ($columns as $column) {
            // Si c'est un alias (ex: "notes.id as note_id")
            if (stripos($column, ' as ') !== false) {
                $this->selects[] = $column;
            } else {
                $this->selects[] = $this->sanitizeIdentifier($column);
            }
        }
        
        return $this;
    }
    
    /**
     * Ajoute une clause WHERE
     */
    public function where($column, $operator = null, $value = null) {
        // Si 2 paramètres : where('id', 1)
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }
        
        // Valider l'opérateur
        if (!in_array(strtoupper($operator), $this->operators)) {
            throw new \Exception("Invalid operator: {$operator}");
        }
        
        $this->wheres[] = [
            'type' => 'basic',
            'column' => $this->sanitizeIdentifier($column),
            'operator' => $operator,
            'value' => $value,
            'boolean' => 'AND'
        ];
        
        $this->bindings[] = $value;
        
        return $this;
    }
    
    /**
     * WHERE avec OR
     */
    public function orWhere($column, $operator = null, $value = null) {
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }
        
        $this->wheres[] = [
            'type' => 'basic',
            'column' => $this->sanitizeIdentifier($column),
            'operator' => $operator,
            'value' => $value,
            'boolean' => 'OR'
        ];
        
        $this->bindings[] = $value;
        
        return $this;
    }
    
    /**
     * WHERE IN
     */
    public function whereIn($column, array $values) {
        $this->wheres[] = [
            'type' => 'in',
            'column' => $this->sanitizeIdentifier($column),
            'values' => $values,
            'boolean' => 'AND'
        ];
        
        foreach ($values as $value) {
            $this->bindings[] = $value;
        }
        
        return $this;
    }
    
    /**
     * WHERE NULL
     */
    public function whereNull($column) {
        $this->wheres[] = [
            'type' => 'null',
            'column' => $this->sanitizeIdentifier($column),
            'boolean' => 'AND'
        ];
        
        return $this;
    }
    
    /**
     * WHERE NOT NULL
     */
    public function whereNotNull($column) {
        $this->wheres[] = [
            'type' => 'not_null',
            'column' => $this->sanitizeIdentifier($column),
            'boolean' => 'AND'
        ];
        
        return $this;
    }
    
    /**
     * WHERE BETWEEN
     */
    public function whereBetween($column, $min, $max) {
        $this->wheres[] = [
            'type' => 'between',
            'column' => $this->sanitizeIdentifier($column),
            'min' => $min,
            'max' => $max,
            'boolean' => 'AND'
        ];
        
        $this->bindings[] = $min;
        $this->bindings[] = $max;
        
        return $this;
    }
    
    /**
     * JOIN
     */
    public function join($table, $first, $operator, $second, $type = 'INNER') {
        $this->joins[] = [
            'type' => strtoupper($type),
            'table' => $this->sanitizeIdentifier($table),
            'first' => $this->sanitizeIdentifier($first),
            'operator' => $operator,
            'second' => $this->sanitizeIdentifier($second)
        ];
        
        return $this;
    }
    
    /**
     * LEFT JOIN
     */
    public function leftJoin($table, $first, $operator, $second) {
        return $this->join($table, $first, $operator, $second, 'LEFT');
    }
    
    /**
     * RIGHT JOIN
     */
    public function rightJoin($table, $first, $operator, $second) {
        return $this->join($table, $first, $operator, $second, 'RIGHT');
    }
    
    /**
     * ORDER BY
     */
    public function orderBy($column, $direction = 'ASC') {
        $direction = strtoupper($direction);
        
        if (!in_array($direction, ['ASC', 'DESC'])) {
            throw new \Exception("Invalid order direction: {$direction}");
        }
        
        $this->orderBys[] = [
            'column' => $this->sanitizeIdentifier($column),
            'direction' => $direction
        ];
        
        return $this;
    }
    
    /**
     * GROUP BY
     */
    public function groupBy(...$columns) {
        foreach ($columns as $column) {
            $this->groupBys[] = $this->sanitizeIdentifier($column);
        }
        
        return $this;
    }
    
    /**
     * HAVING
     */
    public function having($column, $operator, $value) {
        $this->havings[] = [
            'column' => $this->sanitizeIdentifier($column),
            'operator' => $operator,
            'value' => $value
        ];
        
        $this->bindings[] = $value;
        
        return $this;
    }
    
    /**
     * LIMIT
     */
    public function limit($limit) {
        $this->limit = (int)$limit;
        return $this;
    }
    
    /**
     * Alias de limit(1)
     */
    public function take($limit) {
        return $this->limit($limit);
    }
    
    /**
     * OFFSET
     */
    public function offset($offset) {
        $this->offset = (int)$offset;
        return $this;
    }
    
    /**
     * Alias de offset()
     */
    public function skip($offset) {
        return $this->offset($offset);
    }
    
    /**
     * Exécute et retourne tous les résultats
     */
    public function get() {
        $sql = $this->toSql();
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($this->bindings);
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Retourne le premier résultat
     */
    public function first() {
        $this->limit(1);
        $results = $this->get();
        
        return !empty($results) ? $results[0] : null;
    }
    
    /**
     * Trouve par ID
     */
    public function find($id, $column = 'id') {
        return $this->where($column, $id)->first();
    }
    
    /**
     * Compte les résultats
     */
    public function count($column = '*') {
        $sql = $this->toCountSql($column);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($this->bindings);
        
        return (int)$stmt->fetchColumn();
    }
    
    /**
     * Vérifie si des résultats existent
     */
    public function exists() {
        return $this->count() > 0;
    }
    
    /**
     * INSERT
     */
    public function insert(array $data) {
        if (empty($data)) {
            throw new \Exception("Insert data cannot be empty");
        }
        
        // Si tableau de tableaux (bulk insert)
        if (is_array(reset($data))) {
            return $this->bulkInsert($data);
        }
        
        $columns = array_keys($data);
        $values = array_values($data);
        
        $columnsSql = implode(', ', array_map([$this, 'sanitizeIdentifier'], $columns));
        $placeholders = implode(', ', array_fill(0, count($values), '?'));
        
        $sql = "INSERT INTO {$this->table} ({$columnsSql}) VALUES ({$placeholders})";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($values);
        
        return $this->pdo->lastInsertId();
    }
    
    /**
     * Bulk INSERT
     */
    protected function bulkInsert(array $data) {
        if (empty($data)) {
            return 0;
        }
        
        $columns = array_keys(reset($data));
        $columnsSql = implode(', ', array_map([$this, 'sanitizeIdentifier'], $columns));
        
        $placeholders = '(' . implode(', ', array_fill(0, count($columns), '?')) . ')';
        $allPlaceholders = implode(', ', array_fill(0, count($data), $placeholders));
        
        $sql = "INSERT INTO {$this->table} ({$columnsSql}) VALUES {$allPlaceholders}";
        
        $values = [];
        foreach ($data as $row) {
            foreach ($columns as $column) {
                $values[] = $row[$column];
            }
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($values);
        
        return $stmt->rowCount();
    }
    
    /**
     * UPDATE
     */
    public function update(array $data) {
        if (empty($data)) {
            throw new \Exception("Update data cannot be empty");
        }
        
        if (empty($this->wheres)) {
            throw new \Exception("UPDATE requires WHERE clause for safety");
        }
        
        $sets = [];
        $values = [];
        
        foreach ($data as $column => $value) {
            $sets[] = $this->sanitizeIdentifier($column) . ' = ?';
            $values[] = $value;
        }
        
        $setSql = implode(', ', $sets);
        $whereSql = $this->compileWheres();
        
        $sql = "UPDATE {$this->table} SET {$setSql}";
        
        if ($whereSql) {
            $sql .= " WHERE {$whereSql}";
        }
        
        // Combiner les bindings des SET et WHERE
        $allBindings = array_merge($values, $this->bindings);
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($allBindings);
        
        return $stmt->rowCount();
    }
    
    /**
     * DELETE
     */
    public function delete() {
        if (empty($this->wheres)) {
            throw new \Exception("DELETE requires WHERE clause for safety");
        }
        
        $whereSql = $this->compileWheres();
        $sql = "DELETE FROM {$this->table}";
        
        if ($whereSql) {
            $sql .= " WHERE {$whereSql}";
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($this->bindings);
        
        return $stmt->rowCount();
    }
    
    /**
     * Compile en SQL
     */
    public function toSql() {
        $sql = 'SELECT ' . implode(', ', $this->selects);
        $sql .= ' FROM ' . $this->table;
        
        // JOINs
        if (!empty($this->joins)) {
            foreach ($this->joins as $join) {
                $sql .= " {$join['type']} JOIN {$join['table']}";
                $sql .= " ON {$join['first']} {$join['operator']} {$join['second']}";
            }
        }
        
        // WHERE
        $whereSql = $this->compileWheres();
        if ($whereSql) {
            $sql .= ' WHERE ' . $whereSql;
        }
        
        // GROUP BY
        if (!empty($this->groupBys)) {
            $sql .= ' GROUP BY ' . implode(', ', $this->groupBys);
        }
        
        // HAVING
        if (!empty($this->havings)) {
            $havingSql = [];
            foreach ($this->havings as $having) {
                $havingSql[] = "{$having['column']} {$having['operator']} ?";
            }
            $sql .= ' HAVING ' . implode(' AND ', $havingSql);
        }
        
        // ORDER BY
        if (!empty($this->orderBys)) {
            $orderSql = [];
            foreach ($this->orderBys as $order) {
                $orderSql[] = "{$order['column']} {$order['direction']}";
            }
            $sql .= ' ORDER BY ' . implode(', ', $orderSql);
        }
        
        // LIMIT
        if ($this->limit) {
            $sql .= ' LIMIT ' . $this->limit;
        }
        
        // OFFSET
        if ($this->offset) {
            $sql .= ' OFFSET ' . $this->offset;
        }
        
        return $sql;
    }
    
    /**
     * Compile COUNT SQL
     */
    protected function toCountSql($column = '*') {
        $sql = "SELECT COUNT({$column}) FROM {$this->table}";
        
        // JOINs
        if (!empty($this->joins)) {
            foreach ($this->joins as $join) {
                $sql .= " {$join['type']} JOIN {$join['table']}";
                $sql .= " ON {$join['first']} {$join['operator']} {$join['second']}";
            }
        }
        
        // WHERE
        $whereSql = $this->compileWheres();
        if ($whereSql) {
            $sql .= ' WHERE ' . $whereSql;
        }
        
        return $sql;
    }
    
    /**
     * Compile les WHERE
     */
    protected function compileWheres() {
        if (empty($this->wheres)) {
            return '';
        }
        
        $sql = [];
        
        foreach ($this->wheres as $i => $where) {
            $boolean = $i === 0 ? '' : " {$where['boolean']} ";
            
            switch ($where['type']) {
                case 'basic':
                    $sql[] = $boolean . "{$where['column']} {$where['operator']} ?";
                    break;
                    
                case 'in':
                    $placeholders = implode(', ', array_fill(0, count($where['values']), '?'));
                    $sql[] = $boolean . "{$where['column']} IN ({$placeholders})";
                    break;
                    
                case 'null':
                    $sql[] = $boolean . "{$where['column']} IS NULL";
                    break;
                    
                case 'not_null':
                    $sql[] = $boolean . "{$where['column']} IS NOT NULL";
                    break;
                    
                case 'between':
                    $sql[] = $boolean . "{$where['column']} BETWEEN ? AND ?";
                    break;
            }
        }
        
        return implode('', $sql);
    }
    
    /**
     * Sanitize les identifiants (tables/colonnes)
     */
    protected function sanitizeIdentifier($identifier) {
        // Si contient un point (ex: table.column)
        if (strpos($identifier, '.') !== false) {
            $parts = explode('.', $identifier);
            return implode('.', array_map(function($part) {
                return $part === '*' ? $part : '`' . str_replace('`', '', $part) . '`';
            }, $parts));
        }
        
        // Si c'est une étoile
        if ($identifier === '*') {
            return '*';
        }
        
        // Identifier simple
        return '`' . str_replace('`', '', $identifier) . '`';
    }
    
    /**
     * Clone pour permettre la réutilisation
     */
    public function newQuery() {
        return new static($this->pdo);
    }
    
    /**
     * Debug : affiche la requête SQL
     */
    public function dd() {
        echo "SQL: " . $this->toSql() . "\n";
        echo "Bindings: " . json_encode($this->bindings) . "\n";
        die();
    }
}
