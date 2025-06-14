<?php

namespace MJ\WPORM;

use wpdb;

class QueryBuilder {
    protected $table;
    protected $model;
    protected $wpdb;
    protected $selects = ['*'];
    protected $wheres = [];
    protected $bindings = [];
    protected $orders = [];
    protected $limit;
    protected $offset;
    protected $joins = [];
    protected $groups = [];
    protected $havings = [];
    protected $with = [];

    public function __construct($model) {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->model = $model;
        $this->table = $model->getTable();
    }

    public function select($columns = ['*']) {
        $this->selects = is_array($columns) ? $columns : func_get_args();
        return $this;
    }

    public function where($column, $operator = null, $value = null) {
        // Support associative array: ['col' => 'val', ...]
        if (is_array($column)) {
            $isAssoc = array_keys($column) !== range(0, count($column) - 1);
            if ($isAssoc) {
                foreach ($column as $key => $val) {
                    $this->where($key, '=', $val);
                }
            } else {
                // Array of arrays: [['col', 'op', 'val']] or [['col', 'val']]
                foreach ($column as $cond) {
                    if (is_array($cond)) {
                        if (count($cond) === 3) {
                            $this->where($cond[0], $cond[1], $cond[2]);
                        } elseif (count($cond) === 2) {
                            $this->where($cond[0], '=', $cond[1]);
                        }
                    }
                }
            }
            return $this;
        }
        if (is_callable($column)) {
            // Nested group
            $nested = new self($this->model);
            $column($nested);
            $group = $nested->wheres;
            $bindings = $nested->bindings;
            if (!empty($group)) {
                $this->wheres[] = '(' . implode(' AND ', $group) . ')';
                $this->bindings = array_merge($this->bindings, $bindings);
            }
            return $this;
        }
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }
        // Cast DateTime for casted columns (handle nulls too)
        if (isset($this->model->casts[$column])) {
            $cast = $this->model->casts[$column];
            if (($cast === 'datetime' || $cast === 'timestamp') && $value instanceof \DateTime) {
                if ($cast === 'datetime') {
                    $value = $value->format('Y-m-d H:i:s');
                } else {
                    $value = $value->getTimestamp();
                }
            } elseif ($cast === 'datetime' && is_string($value) && strtotime($value) !== false) {
                // If a string is passed, normalize to Y-m-d H:i:s
                $value = date('Y-m-d H:i:s', strtotime($value));
            }
        }
        // Always convert DateTime to string for SQL
        if ($value instanceof \DateTime) {
            $value = $value->format('Y-m-d H:i:s');
        }
        $this->wheres[] = "$column $operator %s";
        $this->bindings[] = $value;
        return $this;
    }

    public function orWhere($column, $operator = null, $value = null) {
        if (is_callable($column)) {
            $nested = new self($this->model);
            $column($nested);
            $group = $nested->wheres;
            $bindings = $nested->bindings;
            if (!empty($group)) {
                $this->wheres[] = 'OR (' . implode(' AND ', $group) . ')';
                $this->bindings = array_merge($this->bindings, $bindings);
            }
            return $this;
        }
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }
        $this->wheres[] = 'OR ' . "$column $operator %s";
        $this->bindings[] = $value;
        return $this;
    }

    public function whereIn($column, array $values) {
        if (empty($values)) {
            // Always false
            $this->wheres[] = '0=1';
            return $this;
        }
        $placeholders = implode(', ', array_fill(0, count($values), '%s'));
        $this->wheres[] = "$column IN ($placeholders)";
        foreach ($values as $v) {
            $this->bindings[] = $v;
        }
        return $this;
    }

    public function whereNotIn($column, array $values) {
        if (empty($values)) {
            // Always true
            return $this;
        }
        $placeholders = implode(', ', array_fill(0, count($values), '%s'));
        $this->wheres[] = "$column NOT IN ($placeholders)";
        foreach ($values as $v) {
            $this->bindings[] = $v;
        }
        return $this;
    }

    public function orWhereIn($column, array $values) {
        if (empty($values)) {
            $this->wheres[] = 'OR 0=1';
            return $this;
        }
        $placeholders = implode(', ', array_fill(0, count($values), '%s'));
        $this->wheres[] = "OR $column IN ($placeholders)";
        foreach ($values as $v) {
            $this->bindings[] = $v;
        }
        return $this;
    }

    public function orWhereNotIn($column, array $values) {
        if (empty($values)) {
            return $this;
        }
        $placeholders = implode(', ', array_fill(0, count($values), '%s'));
        $this->wheres[] = "OR $column NOT IN ($placeholders)";
        foreach ($values as $v) {
            $this->bindings[] = $v;
        }
        return $this;
    }

    public function whereLike($column, $value) {
        $this->wheres[] = "$column LIKE %s";
        $this->bindings[] = $value;
        return $this;
    }

    public function orWhereLike($column, $value) {
        $this->wheres[] = "OR $column LIKE %s";
        $this->bindings[] = $value;
        return $this;
    }

    public function whereNotLike($column, $value) {
        $this->wheres[] = "$column NOT LIKE %s";
        $this->bindings[] = $value;
        return $this;
    }

    public function orWhereNotLike($column, $value) {
        $this->wheres[] = "OR $column NOT LIKE %s";
        $this->bindings[] = $value;
        return $this;
    }

    public function whereNot($column, $operator = null, $value = null) {
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }
        $this->wheres[] = "$column NOT $operator %s";
        $this->bindings[] = $value;
        return $this;
    }

    public function orWhereNot($column, $operator = null, $value = null) {
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }
        $this->wheres[] = "OR $column NOT $operator %s";
        $this->bindings[] = $value;
        return $this;
    }

    public function whereAny(array $conditions) {
        $orGroup = [];
        $bindings = [];
        foreach ($conditions as $cond) {
            if (is_array($cond)) {
                if (count($cond) === 3) {
                    $orGroup[] = "$cond[0] $cond[1] %s";
                    $bindings[] = $cond[2];
                } elseif (count($cond) === 2) {
                    $orGroup[] = "$cond[0] = %s";
                    $bindings[] = $cond[1];
                }
            } elseif (is_string($cond)) {
                $orGroup[] = $cond;
            }
        }
        if ($orGroup) {
            $this->wheres[] = '(' . implode(' OR ', $orGroup) . ')';
            foreach ($bindings as $b) {
                $this->bindings[] = $b;
            }
        }
        return $this;
    }

    public function orWhereAny(array $conditions) {
        $orGroup = [];
        $bindings = [];
        foreach ($conditions as $cond) {
            if (is_array($cond)) {
                if (count($cond) === 3) {
                    $orGroup[] = "$cond[0] $cond[1] %s";
                    $bindings[] = $cond[2];
                } elseif (count($cond) === 2) {
                    $orGroup[] = "$cond[0] = %s";
                    $bindings[] = $cond[1];
                }
            } elseif (is_string($cond)) {
                $orGroup[] = $cond;
            }
        }
        if ($orGroup) {
            $this->wheres[] = 'OR (' . implode(' OR ', $orGroup) . ')';
            foreach ($bindings as $b) {
                $this->bindings[] = $b;
            }
        }
        return $this;
    }

    public function whereAll(array $conditions) {
        $andGroup = [];
        $bindings = [];
        foreach ($conditions as $cond) {
            if (is_array($cond)) {
                if (count($cond) === 3) {
                    $andGroup[] = "$cond[0] $cond[1] %s";
                    $bindings[] = $cond[2];
                } elseif (count($cond) === 2) {
                    $andGroup[] = "$cond[0] = %s";
                    $bindings[] = $cond[1];
                }
            } elseif (is_string($cond)) {
                $andGroup[] = $cond;
            }
        }
        if ($andGroup) {
            $this->wheres[] = '(' . implode(' AND ', $andGroup) . ')';
            foreach ($bindings as $b) {
                $this->bindings[] = $b;
            }
        }
        return $this;
    }

    public function orWhereAll(array $conditions) {
        $andGroup = [];
        $bindings = [];
        foreach ($conditions as $cond) {
            if (is_array($cond)) {
                if (count($cond) === 3) {
                    $andGroup[] = "$cond[0] $cond[1] %s";
                    $bindings[] = $cond[2];
                } elseif (count($cond) === 2) {
                    $andGroup[] = "$cond[0] = %s";
                    $bindings[] = $cond[1];
                }
            } elseif (is_string($cond)) {
                $andGroup[] = $cond;
            }
        }
        if ($andGroup) {
            $this->wheres[] = 'OR (' . implode(' AND ', $andGroup) . ')';
            foreach ($bindings as $b) {
                $this->bindings[] = $b;
            }
        }
        return $this;
    }

    public function whereNone(array $conditions) {
        $notGroup = [];
        $bindings = [];
        foreach ($conditions as $cond) {
            if (is_array($cond)) {
                if (count($cond) === 3) {
                    $notGroup[] = "$cond[0] $cond[1] %s";
                    $bindings[] = $cond[2];
                } elseif (count($cond) === 2) {
                    $notGroup[] = "$cond[0] = %s";
                    $bindings[] = $cond[1];
                }
            } elseif (is_string($cond)) {
                $notGroup[] = $cond;
            }
        }
        if ($notGroup) {
            $this->wheres[] = 'NOT (' . implode(' OR ', $notGroup) . ')';
            foreach ($bindings as $b) {
                $this->bindings[] = $b;
            }
        }
        return $this;
    }

    public function orWhereNone(array $conditions) {
        $notGroup = [];
        $bindings = [];
        foreach ($conditions as $cond) {
            if (is_array($cond)) {
                if (count($cond) === 3) {
                    $notGroup[] = "$cond[0] $cond[1] %s";
                    $bindings[] = $cond[2];
                } elseif (count($cond) === 2) {
                    $notGroup[] = "$cond[0] = %s";
                    $bindings[] = $cond[1];
                }
            } elseif (is_string($cond)) {
                $notGroup[] = $cond;
            }
        }
        if ($notGroup) {
            $this->wheres[] = 'OR NOT (' . implode(' OR ', $notGroup) . ')';
            foreach ($bindings as $b) {
                $this->bindings[] = $b;
            }
        }
        return $this;
    }

    public function whereBetween($column, array $values) {
        if (count($values) !== 2) {
            throw new \InvalidArgumentException('whereBetween expects exactly 2 values.');
        }
        $this->wheres[] = "$column BETWEEN %s AND %s";
        $this->bindings[] = $values[0];
        $this->bindings[] = $values[1];
        return $this;
    }

    public function orWhereBetween($column, array $values) {
        if (count($values) !== 2) {
            throw new \InvalidArgumentException('orWhereBetween expects exactly 2 values.');
        }
        $this->wheres[] = "OR $column BETWEEN %s AND %s";
        $this->bindings[] = $values[0];
        $this->bindings[] = $values[1];
        return $this;
    }

    public function whereNotBetween($column, array $values) {
        if (count($values) !== 2) {
            throw new \InvalidArgumentException('whereNotBetween expects exactly 2 values.');
        }
        $this->wheres[] = "$column NOT BETWEEN %s AND %s";
        $this->bindings[] = $values[0];
        $this->bindings[] = $values[1];
        return $this;
    }

    public function orWhereNotBetween($column, array $values) {
        if (count($values) !== 2) {
            throw new \InvalidArgumentException('orWhereNotBetween expects exactly 2 values.');
        }
        $this->wheres[] = "OR $column NOT BETWEEN %s AND %s";
        $this->bindings[] = $values[0];
        $this->bindings[] = $values[1];
        return $this;
    }

    // WHERE BETWEEN COLUMNS
    public function whereBetweenColumns($column, array $columns) {
        if (count($columns) !== 2) {
            throw new \InvalidArgumentException('whereBetweenColumns expects exactly 2 columns.');
        }
        $this->wheres[] = "$column BETWEEN {$columns[0]} AND {$columns[1]}";
        return $this;
    }

    public function orWhereBetweenColumns($column, array $columns) {
        if (count($columns) !== 2) {
            throw new \InvalidArgumentException('orWhereBetweenColumns expects exactly 2 columns.');
        }
        $this->wheres[] = "OR $column BETWEEN {$columns[0]} AND {$columns[1]}";
        return $this;
    }

    public function whereNotBetweenColumns($column, array $columns) {
        if (count($columns) !== 2) {
            throw new \InvalidArgumentException('whereNotBetweenColumns expects exactly 2 columns.');
        }
        $this->wheres[] = "$column NOT BETWEEN {$columns[0]} AND {$columns[1]}";
        return $this;
    }

    public function orWhereNotBetweenColumns($column, array $columns) {
        if (count($columns) !== 2) {
            throw new \InvalidArgumentException('orWhereNotBetweenColumns expects exactly 2 columns.');
        }
        $this->wheres[] = "OR $column NOT BETWEEN {$columns[0]} AND {$columns[1]}";
        return $this;
    }

    // WHERE NULL / NOT NULL
    public function whereNull($column) {
        $this->wheres[] = "$column IS NULL";
        return $this;
    }

    public function orWhereNull($column) {
        $this->wheres[] = "OR $column IS NULL";
        return $this;
    }

    public function whereNotNull($column) {
        $this->wheres[] = "$column IS NOT NULL";
        return $this;
    }

    public function orWhereNotNull($column) {
        $this->wheres[] = "OR $column IS NOT NULL";
        return $this;
    }

    // WHERE DATE/TIME PARTS
    public function whereDate($column, $value) {
        $this->wheres[] = "DATE($column) = %s";
        $this->bindings[] = $value;
        return $this;
    }
    public function whereMonth($column, $value) {
        $this->wheres[] = "MONTH($column) = %s";
        $this->bindings[] = $value;
        return $this;
    }
    public function whereDay($column, $value) {
        $this->wheres[] = "DAY($column) = %s";
        $this->bindings[] = $value;
        return $this;
    }
    public function whereYear($column, $value) {
        $this->wheres[] = "YEAR($column) = %s";
        $this->bindings[] = $value;
        return $this;
    }
    public function whereTime($column, $value) {
        $this->wheres[] = "TIME($column) = %s";
        $this->bindings[] = $value;
        return $this;
    }

    // WHERE PAST/FUTURE/TODAY
    public function wherePast($column) {
        $this->wheres[] = "$column < CURDATE()";
        return $this;
    }
    public function whereFuture($column) {
        $this->wheres[] = "$column > CURDATE()";
        return $this;
    }
    public function whereToday($column) {
        $this->wheres[] = "DATE($column) = CURDATE()";
        return $this;
    }
    public function whereBeforeToday($column) {
        $this->wheres[] = "DATE($column) < CURDATE()";
        return $this;
    }
    public function whereAfterToday($column) {
        $this->wheres[] = "DATE($column) > CURDATE()";
        return $this;
    }

    // WHERE COLUMN COMPARISON
    public function whereColumn($first, $operator, $second = null) {
        if ($second === null) {
            $second = $operator;
            $operator = '=';
        }
        $this->wheres[] = "$first $operator $second";
        return $this;
    }
    public function orWhereColumn($first, $operator, $second = null) {
        if ($second === null) {
            $second = $operator;
            $operator = '=';
        }
        $this->wheres[] = "OR $first $operator $second";
        return $this;
    }

    public function orderBy($column, $direction = 'asc') {
        $this->orders[] = "$column $direction";
        return $this;
    }

    /**
     * Order by latest (descending, default column 'created_at')
     */
    public function latest($column = 'created_at') {
        return $this->orderBy($column, 'desc');
    }

    /**
     * Order by oldest (ascending, default column 'created_at')
     */
    public function oldest($column = 'created_at') {
        return $this->orderBy($column, 'asc');
    }

    /**
     * Order randomly
     */
    public function inRandomOrder() {
        $this->orders[] = 'RAND()';
        return $this;
    }

    /**
     * Remove all order by clauses
     */
    public function reorder() {
        $this->orders = [];
        return $this;
    }

    public function limit($limit) {
        $this->limit = (int) $limit;
        return $this;
    }

    public function offset($offset) {
        $this->offset = (int) $offset;
        return $this;
    }

    /**
     * Eager load relation with constraints (closure).
     * Usage: ->with(['history' => function($query) { $query->where(...); }])
     */
    public function with($relations) {
        if (!is_array($relations)) {
            $relations = func_get_args();
        }
        foreach ($relations as $key => $value) {
            if (is_int($key)) {
                $this->with[] = $value;
            } else {
                $this->with[$key] = $value;
            }
        }
        return $this;
    }

    public function get() {
        $sql = $this->buildSelectQuery();
        if (!empty($this->bindings)) {
            $sql = $this->wpdb->prepare($sql, ...$this->bindings);
        }
        $results = $this->wpdb->get_results($sql, ARRAY_A);
        if (!$results) return [];
        $modelClass = get_class($this->model);
        $models = array_map(function ($row) use ($modelClass) {
            $instance = (new $modelClass)->newFromBuilder($row);
            return $instance;
        }, $results);
        // Eager load relations if requested
        if (!empty($this->with) && !empty($models)) {
            foreach ($this->with as $relation => $constraint) {
                if (is_int($relation)) {
                    $relation = $constraint;
                    $constraint = null;
                }
                $this->eagerLoadRelation($models, $relation, $constraint);
            }
        }
        return $models;
    }

    public function first() {
        $this->limit(1);
        $results = $this->get();
        return $results[0] ?? null;
    }

    public function count() {
        $sql = $this->buildCountQuery();
        return (int) $this->wpdb->get_var($this->wpdb->prepare($sql, ...$this->bindings));
    }

    public function delete() {
        $sql = $this->buildDeleteQuery();
        return $this->wpdb->query($this->wpdb->prepare($sql, ...$this->bindings));
    }

    public function beginTransaction() {
        $this->wpdb->query('START TRANSACTION');
    }

    public function commit() {
        $this->wpdb->query('COMMIT');
    }

    public function rollBack() {
        $this->wpdb->query('ROLLBACK');
    }

    public function __call($method, $parameters) {
        $scopeMethod = 'scope' . ucfirst($method);
        if (method_exists($this->model, $scopeMethod)) {
            array_unshift($parameters, $this);
            return call_user_func_array([$this->model, $scopeMethod], $parameters);
        }
        throw new \BadMethodCallException("Method {$method} does not exist.");
    }

    // JSON WHERE CLAUSES
    public function whereJson($column, $operator = null, $value = null) {
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }
        $jsonPath = $this->parseJsonPath($column);
        $this->wheres[] = "JSON_UNQUOTE(JSON_EXTRACT($jsonPath)) $operator %s";
        $this->bindings[] = $value;
        return $this;
    }

    public function orWhereJson($column, $operator = null, $value = null) {
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }
        $jsonPath = $this->parseJsonPath($column);
        $this->wheres[] = "OR JSON_UNQUOTE(JSON_EXTRACT($jsonPath)) $operator %s";
        $this->bindings[] = $value;
        return $this;
    }

    public function whereJsonContains($column, $value) {
        $jsonPath = $this->parseJsonPath($column);
        $this->wheres[] = "JSON_CONTAINS(JSON_EXTRACT($jsonPath), %s)";
        $this->bindings[] = is_array($value) ? json_encode($value) : json_encode([$value]);
        return $this;
    }

    public function orWhereJsonContains($column, $value) {
        $jsonPath = $this->parseJsonPath($column);
        $this->wheres[] = "OR JSON_CONTAINS(JSON_EXTRACT($jsonPath), %s)";
        $this->bindings[] = is_array($value) ? json_encode($value) : json_encode([$value]);
        return $this;
    }

    public function whereJsonLength($column, $operator = null, $value = null) {
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }
        $jsonPath = $this->parseJsonPath($column);
        $this->wheres[] = "JSON_LENGTH(JSON_EXTRACT($jsonPath)) $operator %s";
        $this->bindings[] = $value;
        return $this;
    }

    public function orWhereJsonLength($column, $operator = null, $value = null) {
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }
        $jsonPath = $this->parseJsonPath($column);
        $this->wheres[] = "OR JSON_LENGTH(JSON_EXTRACT($jsonPath)) $operator %s";
        $this->bindings[] = $value;
        return $this;
    }

    /**
     * Add an INNER JOIN clause to the query.
     * Usage: ->join('table', 'table.col', '=', 'other.col')
     */
    public function join($table, $first = null, $operator = null, $second = null, $type = 'INNER') {
        if (is_callable($first)) {
            // Support closure for advanced join conditions
            $join = new static($this->model);
            $first($join);
            $this->joins[] = [
                'type' => $type,
                'table' => $table,
                'clause' => $join->wheres ? '(' . implode(' AND ', $join->wheres) . ')' : '1=1',
                'bindings' => $join->bindings,
            ];
        } elseif ($first && $operator && $second) {
            $this->joins[] = [
                'type' => $type,
                'table' => $table,
                'clause' => "$first $operator $second",
                'bindings' => [],
            ];
        } else {
            $this->joins[] = [
                'type' => $type,
                'table' => $table,
                'clause' => null,
                'bindings' => [],
            ];
        }
        return $this;
    }

    /**
     * Add a LEFT JOIN clause to the query.
     */
    public function leftJoin($table, $first = null, $operator = null, $second = null) {
        return $this->join($table, $first, $operator, $second, 'LEFT');
    }

    /**
     * Add a RIGHT JOIN clause to the query.
     */
    public function rightJoin($table, $first = null, $operator = null, $second = null) {
        return $this->join($table, $first, $operator, $second, 'RIGHT');
    }

    /**
     * Add a CROSS JOIN clause to the query.
     * Usage: ->crossJoin('table')
     */
    public function crossJoin($table) {
        $this->joins[] = [
            'type' => 'CROSS',
            'table' => $table,
            'clause' => null,
            'bindings' => [],
        ];
        return $this;
    }

    /**
     * Add GROUP BY clause(s) to the query.
     */
    public function groupBy($columns) {
        if (!is_array($columns)) {
            $columns = func_get_args();
        }
        foreach ($columns as $col) {
            $this->groups[] = $col;
        }
        return $this;
    }

    /**
     * Add a HAVING clause to the query.
     */
    public function having($column, $operator = null, $value = null) {
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }
        $this->havings[] = ["$column $operator %s", [$value]];
        return $this;
    }

    /**
     * Add a HAVING BETWEEN ... AND ... clause to the query.
     */
    public function havingBetween($column, array $values) {
        if (count($values) !== 2) {
            throw new \InvalidArgumentException('havingBetween expects exactly 2 values.');
        }
        $this->havings[] = ["$column BETWEEN %s AND %s", $values];
        return $this;
    }

    // Helper to convert 'col->foo->bar' to JSON_EXTRACT(col, '$.foo.bar')
    protected function parseJsonPath($column) {
        if (strpos($column, '->') === false && strpos($column, '=>') === false) {
            return $column;
        }
        $col = str_replace('=>', '->', $column);
        $parts = explode('->', $col);
        $field = array_shift($parts);
        $path = '$';
        foreach ($parts as $p) {
            $p = trim($p, "'\"");
            $path .= "." . $p;
        }
        return "$field, '$path'";
    }

    protected function buildSelectQuery() {
        if (empty($this->wheres)) {
            $where = '';
        } else {
            // Rebuild where clause to support OR and AND logic
            $where = '';
            foreach ($this->wheres as $i => $clause) {
                if ($i === 0) {
                    $where .= $clause;
                } else {
                    if (strpos(trim($clause), 'OR ') === 0) {
                        $where .= ' ' . $clause;
                    } else {
                        $where .= ' AND ' . $clause;
                    }
                }
            }
        }
        $sql = "SELECT " . implode(", ", $this->selects) . " FROM {$this->table}";
        // Add JOIN clauses
        if (!empty($this->joins)) {
            foreach ($this->joins as $join) {
                $type = $join['type'];
                $table = $join['table'];
                if ($type === 'CROSS') {
                    $sql .= " CROSS JOIN $table";
                } elseif ($join['clause']) {
                    $sql .= " $type JOIN $table ON {$join['clause']}";
                } else {
                    $sql .= " $type JOIN $table";
                }
            }
        }
        if (!empty($where)) {
            $sql .= " WHERE $where";
        }
        // GROUP BY
        if (!empty($this->groups)) {
            $sql .= " GROUP BY " . implode(", ", $this->groups);
        }
        // HAVING
        if (!empty($this->havings)) {
            $havingParts = [];
            foreach ($this->havings as [$expr, $vals]) {
                $havingParts[] = $expr;
                foreach ($vals as $v) {
                    $this->bindings[] = $v;
                }
            }
            $sql .= " HAVING " . implode(' AND ', $havingParts);
        }
        if (!empty($this->orders)) {
            $sql .= " ORDER BY " . implode(", ", $this->orders);
        }
        if (isset($this->limit)) {
            $sql .= " LIMIT {$this->limit}";
        }
        if (isset($this->offset)) {
            $sql .= " OFFSET {$this->offset}";
        }
        return $sql;
    }

    protected function buildCountQuery() {
        $sql = "SELECT COUNT(*) FROM {$this->table}";
        if (!empty($this->wheres)) {
            $sql .= " WHERE " . implode(" AND ", $this->wheres);
        }
        return $sql;
    }

    protected function buildDeleteQuery() {
        $sql = "DELETE FROM {$this->table}";
        if (!empty($this->wheres)) {
            $sql .= " WHERE " . implode(" AND ", $this->wheres);
        }
        return $sql;
    }

    protected function eagerLoadRelation(array &$models, $relation, $constraint = null) {
        if (empty($models)) return;
        $model = $models[0];
        if (!method_exists($model, $relation)) return;
        $related = $model->$relation();
        // hasMany
        if (is_array($related)) {
            $foreignKey = null;
            $localKey = $model->primaryKey;
            $ref = new \ReflectionMethod($model, $relation);
            $params = $ref->getParameters();
            if (isset($params[1])) {
                $foreignKey = $params[1]->getDefaultValue();
            }
            $ids = array_map(fn($m) => $m->$localKey, $models);
            $relatedModel = $related ? get_class($related[0]) : null;
            if ($relatedModel && $foreignKey) {
                $query = $relatedModel::query()->whereIn($foreignKey, $ids);
                if ($constraint) {
                    $constraint($query);
                }
                $allRelated = $query->get();
                $grouped = [];
                foreach ($allRelated as $rel) {
                    $grouped[$rel->$foreignKey][] = $rel;
                }
                foreach ($models as $m) {
                    $m->_eagerLoaded[$relation] = $grouped[$m->$localKey] ?? [];
                }
            }
        }
        // belongsTo
        elseif ($related instanceof \MJ\WPORM\Model) {
            $foreignKey = null;
            $ownerKey = $related->primaryKey;
            $ref = new \ReflectionMethod($model, $relation);
            $params = $ref->getParameters();
            if (isset($params[1])) {
                $foreignKey = $params[1]->getDefaultValue();
            }
            $ids = array_map(fn($m) => $m->$foreignKey, $models);
            $relatedModel = get_class($related);
            $query = $relatedModel::query()->whereIn($ownerKey, $ids);
            if ($constraint) {
                $constraint($query);
            }
            $allRelated = $query->get();
            $map = [];
            foreach ($allRelated as $rel) {
                $map[$rel->$ownerKey] = $rel;
            }
            foreach ($models as $m) {
                $m->_eagerLoaded[$relation] = $map[$m->$foreignKey] ?? null;
            }
        }
    }

    // WHERE EXISTS / NOT EXISTS
    public function whereExists($callback) {
        $sub = new self($this->model);
        $callback($sub);
        $sql = $sub->buildSelectQuery();
        // Remove SELECT ... FROM ... to just the subquery
        $sql = preg_replace('/^SELECT .* FROM /i', 'SELECT 1 FROM ', $sql);
        $this->wheres[] = "EXISTS ($sql)";
        $this->bindings = array_merge($this->bindings, $sub->bindings);
        return $this;
    }
    public function orWhereExists($callback) {
        $sub = new self($this->model);
        $callback($sub);
        $sql = $sub->buildSelectQuery();
        $sql = preg_replace('/^SELECT .* FROM /i', 'SELECT 1 FROM ', $sql);
        $this->wheres[] = "OR EXISTS ($sql)";
        $this->bindings = array_merge($this->bindings, $sub->bindings);
        return $this;
    }
    public function whereNotExists($callback) {
        $sub = new self($this->model);
        $callback($sub);
        $sql = $sub->buildSelectQuery();
        $sql = preg_replace('/^SELECT .* FROM /i', 'SELECT 1 FROM ', $sql);
        $this->wheres[] = "NOT EXISTS ($sql)";
        $this->bindings = array_merge($this->bindings, $sub->bindings);
        return $this;
    }
    public function orWhereNotExists($callback) {
        $sub = new self($this->model);
        $callback($sub);
        $sql = $sub->buildSelectQuery();
        $sql = preg_replace('/^SELECT .* FROM /i', 'SELECT 1 FROM ', $sql);
        $this->wheres[] = "OR NOT EXISTS ($sql)";
        $this->bindings = array_merge($this->bindings, $sub->bindings);
        return $this;
    }
}
