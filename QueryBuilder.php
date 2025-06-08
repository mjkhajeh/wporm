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

    public function orderBy($column, $direction = 'asc') {
        $this->orders[] = "$column $direction";
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

    public function get() {
        $sql = $this->buildSelectQuery();
        if( !empty( $this->bindings ) ) {
            $sql = $this->wpdb->prepare($sql, ...$this->bindings);
        }
        $results = $this->wpdb->get_results($sql, ARRAY_A);
        if (!$results) return [];
        $modelClass = get_class($this->model);
        return array_map(function ($row) use ($modelClass) {
            $instance = new $modelClass;
            $instance->fill($row);
            $instance->original = $row;
            $instance->exists = true;
            return $instance;
        }, $results);
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
        if (!empty($where)) {
            $sql .= " WHERE $where";
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
}
