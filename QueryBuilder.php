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
