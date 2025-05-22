<?php

namespace WPORM;

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

    public function where($column, $operator = '=', $value = null) {
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }
        $this->wheres[] = "$column $operator %s";
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
        $sql = "SELECT " . implode(", ", $this->selects) . " FROM {$this->table}";

        if (!empty($this->wheres)) {
            $sql .= " WHERE " . implode(" AND ", $this->wheres);
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
