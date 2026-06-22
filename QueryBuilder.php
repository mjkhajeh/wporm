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
    protected $applyGlobalScopes = true;
    protected $relationContext = [];

    /**
     * If true, SQL and bindings will be logged before execution.
     * Set via QueryBuilder::setDebug(true) or $query->debug = true
     */
    public $debug = false;

    /**
     * Set debug mode for this query instance.
     */
    public function setDebug($debug = true) {
        $this->debug = (bool)$debug;
        return $this;
    }

    public function setRelationContext($type, array $metadata = []) {
        $this->relationContext = array_merge(['type' => $type], $metadata);
        return $this;
    }

    public function getRelationContext() {
        return $this->relationContext;
    }

    public function getWhereCount() {
        return count($this->wheres);
    }

    public function __construct($model, $applyGlobalScopes = true) {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->model = $model;
        $this->table = $model->getTable();
        $this->applyGlobalScopes = $applyGlobalScopes;
        // Apply global scopes if enabled
        if ($this->applyGlobalScopes && method_exists($model, 'applyGlobalScopes')) {
            $model::applyGlobalScopes($this);
        }
    }

    // Helper to quote identifiers (table/column names) with backticks

    public function select($columns = ['*']) {
        $this->selects = is_array($columns) ? $columns : func_get_args();
        return $this;
    }

    /**
     * Add a raw SQL expression to the SELECT clause, with optional bindings.
     * Can be combined with select()/multiple selectRaw() calls — all are
     * concatenated together in the final SELECT list.
     *
     * Usage:
     *   ->selectRaw('COUNT(*) as total')
     *   ->selectRaw('price * %s as adjusted_price', [1.1])
     *   ->select('name')->selectRaw('price * %s as adjusted_price', [1.1])
     *
     * @param string $sql
     * @param array $bindings
     * @return $this
     */
    public function selectRaw($sql, array $bindings = []) {
        $this->selects[] = ['raw' => $sql, 'bindings' => $bindings];
        return $this;
    }

    public function from($table) {
        $this->table = $table;
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
        if ($column instanceof \Closure) {
            // Nested group: build SQL preserving explicit OR prefixes
            $nested = new self($this->model);
            $column($nested);
            $group = $nested->wheres;
            $bindings = $nested->bindings;
            if (!empty($group)) {
                $groupSql = '';
                foreach ($group as $i => $g) {
                    if ($i === 0) {
                        // If the first element begins with an OR, strip it
                        $groupSql .= preg_replace('/^\s*OR\s+/i', '', $g);
                    } else {
                        if (preg_match('/^\s*OR\s+/i', $g)) {
                            // Preserve OR by appending as-is (with a space)
                            $groupSql .= ' ' . preg_replace('/^\s*/', '', $g);
                        } else {
                            $groupSql .= ' AND ' . $g;
                        }
                    }
                }
                $this->wheres[] = '(' . $groupSql . ')';
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
        $this->wheres[] = Helpers::quoteIdentifier($column) . " $operator %s";
        $this->bindings[] = $value;
        return $this;
    }

    public function orWhere($column, $operator = null, $value = null) {
        if ($column instanceof \Closure) {
            $nested = new self($this->model);
            $column($nested);
            $group = $nested->wheres;
            $bindings = $nested->bindings;
            if (!empty($group)) {
                $groupSql = '';
                foreach ($group as $i => $g) {
                    if ($i === 0) {
                        $groupSql .= preg_replace('/^\s*OR\s+/i', '', $g);
                    } else {
                        if (preg_match('/^\s*OR\s+/i', $g)) {
                            $groupSql .= ' ' . preg_replace('/^\s*/', '', $g);
                        } else {
                            $groupSql .= ' AND ' . $g;
                        }
                    }
                }
                $this->wheres[] = 'OR (' . $groupSql . ')';
                $this->bindings = array_merge($this->bindings, $bindings);
            }
            return $this;
        }
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }
        $this->wheres[] = 'OR ' . Helpers::quoteIdentifier($column) . " $operator %s";
        $this->bindings[] = $value;
        return $this;
    }

    /**
     * Add a raw SQL WHERE clause, with optional bindings (%s-style placeholders,
     * matching the rest of WPORM — they're passed straight to $wpdb->prepare).
     * Usage: ->whereRaw('price > %s', [100])
     *        ->whereRaw('YEAR(created_at) = %s AND MONTH(created_at) = %s', [2025, 6])
     *
     * @param string $sql
     * @param array $bindings
     * @return $this
     */
    public function whereRaw($sql, array $bindings = []) {
        $this->wheres[] = $sql;
        foreach ($bindings as $b) {
            $this->bindings[] = $b;
        }
        return $this;
    }

    /**
     * Add a raw SQL OR WHERE clause, with optional bindings.
     * Usage: ->orWhereRaw('price > %s', [100])
     *
     * @param string $sql
     * @param array $bindings
     * @return $this
     */
    public function orWhereRaw($sql, array $bindings = []) {
        $this->wheres[] = 'OR ' . $sql;
        foreach ($bindings as $b) {
            $this->bindings[] = $b;
        }
        return $this;
    }

    public function whereIn($column, array $values) {
        if (empty($values)) {
            // Always false
            $this->wheres[] = '0=1';
            return $this;
        }
        $placeholders = implode(', ', array_fill(0, count($values), '%s'));
        $this->wheres[] = Helpers::quoteIdentifier($column) . " IN ($placeholders)";
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
        $this->wheres[] = Helpers::quoteIdentifier($column) . " NOT IN ($placeholders)";
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
        $this->wheres[] = "OR " . Helpers::quoteIdentifier($column) . " IN ($placeholders)";
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
        $this->wheres[] = "OR " . Helpers::quoteIdentifier($column) . " NOT IN ($placeholders)";
        foreach ($values as $v) {
            $this->bindings[] = $v;
        }
        return $this;
    }

    public function whereLike($column, $value) {
        $this->wheres[] = Helpers::quoteIdentifier($column) . " LIKE %s";
        $this->bindings[] = $value;
        return $this;
    }

    public function orWhereLike($column, $value) {
        $this->wheres[] = "OR " . Helpers::quoteIdentifier($column) . " LIKE %s";
        $this->bindings[] = $value;
        return $this;
    }

    public function whereNotLike($column, $value) {
        $this->wheres[] = Helpers::quoteIdentifier($column) . " NOT LIKE %s";
        $this->bindings[] = $value;
        return $this;
    }

    public function orWhereNotLike($column, $value) {
        $this->wheres[] = "OR " . Helpers::quoteIdentifier($column) . " NOT LIKE %s";
        $this->bindings[] = $value;
        return $this;
    }

    public function whereNot($column, $operator = null, $value = null) {
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }
        // Use '!=' instead of 'NOT =' for equality
        if ($operator === '=') {
            $this->wheres[] = Helpers::quoteIdentifier($column) . " != %s";
        } else {
            $this->wheres[] = Helpers::quoteIdentifier($column) . " NOT $operator %s";
        }
        $this->bindings[] = $value;
        return $this;
    }

    public function orWhereNot($column, $operator = null, $value = null) {
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }
        // Use '!=' instead of 'NOT =' for equality
        if ($operator === '=') {
            $this->wheres[] = "OR " . Helpers::quoteIdentifier($column) . " != %s";
        } else {
            $this->wheres[] = "OR " . Helpers::quoteIdentifier($column) . " NOT $operator %s";
        }
        $this->bindings[] = $value;
        return $this;
    }

    public function whereAny(array $conditions) {
        $orGroup = [];
        $bindings = [];
        foreach ($conditions as $cond) {
            if (is_array($cond)) {
                if (count($cond) === 3) {
                    $orGroup[] = Helpers::quoteIdentifier($cond[0]) . " $cond[1] %s";
                    $bindings[] = $cond[2];
                } elseif (count($cond) === 2) {
                    $orGroup[] = Helpers::quoteIdentifier($cond[0]) . " = %s";
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
                    $orGroup[] = Helpers::quoteIdentifier($cond[0]) . " $cond[1] %s";
                    $bindings[] = $cond[2];
                } elseif (count($cond) === 2) {
                    $orGroup[] = Helpers::quoteIdentifier($cond[0]) . " = %s";
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
                    $andGroup[] = Helpers::quoteIdentifier($cond[0]) . " $cond[1] %s";
                    $bindings[] = $cond[2];
                } elseif (count($cond) === 2) {
                    $andGroup[] = Helpers::quoteIdentifier($cond[0]) . " = %s";
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
                    $andGroup[] = Helpers::quoteIdentifier($cond[0]) . " $cond[1] %s";
                    $bindings[] = $cond[2];
                } elseif (count($cond) === 2) {
                    $andGroup[] = Helpers::quoteIdentifier($cond[0]) . " = %s";
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
                    $notGroup[] = Helpers::quoteIdentifier($cond[0]) . " $cond[1] %s";
                    $bindings[] = $cond[2];
                } elseif (count($cond) === 2) {
                    $notGroup[] = Helpers::quoteIdentifier($cond[0]) . " = %s";
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
                    $notGroup[] = Helpers::quoteIdentifier($cond[0]) . " $cond[1] %s";
                    $bindings[] = $cond[2];
                } elseif (count($cond) === 2) {
                    $notGroup[] = Helpers::quoteIdentifier($cond[0]) . " = %s";
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
        $this->wheres[] = Helpers::quoteIdentifier($column) . " BETWEEN %s AND %s";
        $this->bindings[] = $values[0];
        $this->bindings[] = $values[1];
        return $this;
    }

    public function orWhereBetween($column, array $values) {
        if (count($values) !== 2) {
            throw new \InvalidArgumentException('orWhereBetween expects exactly 2 values.');
        }
        $this->wheres[] = "OR " . Helpers::quoteIdentifier($column) . " BETWEEN %s AND %s";
        $this->bindings[] = $values[0];
        $this->bindings[] = $values[1];
        return $this;
    }

    public function whereNotBetween($column, array $values) {
        if (count($values) !== 2) {
            throw new \InvalidArgumentException('whereNotBetween expects exactly 2 values.');
        }
        $this->wheres[] = Helpers::quoteIdentifier($column) . " NOT BETWEEN %s AND %s";
        $this->bindings[] = $values[0];
        $this->bindings[] = $values[1];
        return $this;
    }

    public function orWhereNotBetween($column, array $values) {
        if (count($values) !== 2) {
            throw new \InvalidArgumentException('orWhereNotBetween expects exactly 2 values.');
        }
        $this->wheres[] = "OR " . Helpers::quoteIdentifier($column) . " NOT BETWEEN %s AND %s";
        $this->bindings[] = $values[0];
        $this->bindings[] = $values[1];
        return $this;
    }

    // WHERE BETWEEN COLUMNS
    public function whereBetweenColumns($column, array $columns) {
        if (count($columns) !== 2) {
            throw new \InvalidArgumentException('whereBetweenColumns expects exactly 2 columns.');
        }
        $this->wheres[] = Helpers::quoteIdentifier($column) . " BETWEEN " . Helpers::quoteIdentifier($columns[0]) . " AND " . Helpers::quoteIdentifier($columns[1]);
        return $this;
    }

    public function orWhereBetweenColumns($column, array $columns) {
        if (count($columns) !== 2) {
            throw new \InvalidArgumentException('orWhereBetweenColumns expects exactly 2 columns.');
        }
        $this->wheres[] = "OR " . Helpers::quoteIdentifier($column) . " BETWEEN " . Helpers::quoteIdentifier($columns[0]) . " AND " . Helpers::quoteIdentifier($columns[1]);
        return $this;
    }

    public function whereNotBetweenColumns($column, array $columns) {
        if (count($columns) !== 2) {
            throw new \InvalidArgumentException('whereNotBetweenColumns expects exactly 2 columns.');
        }
        $this->wheres[] = Helpers::quoteIdentifier($column) . " NOT BETWEEN " . Helpers::quoteIdentifier($columns[0]) . " AND " . Helpers::quoteIdentifier($columns[1]);
        return $this;
    }

    public function orWhereNotBetweenColumns($column, array $columns) {
        if (count($columns) !== 2) {
            throw new \InvalidArgumentException('orWhereNotBetweenColumns expects exactly 2 columns.');
        }
        $this->wheres[] = "OR " . Helpers::quoteIdentifier($column) . " NOT BETWEEN " . Helpers::quoteIdentifier($columns[0]) . " AND " . Helpers::quoteIdentifier($columns[1]);
        return $this;
    }

    // WHERE NULL / NOT NULL
    public function whereNull($column) {
        $this->wheres[] = Helpers::quoteIdentifier($column) . " IS NULL";
        return $this;
    }

    public function orWhereNull($column) {
        $this->wheres[] = "OR " . Helpers::quoteIdentifier($column) . " IS NULL";
        return $this;
    }

    public function whereNotNull($column) {
        $this->wheres[] = Helpers::quoteIdentifier($column) . " IS NOT NULL";
        return $this;
    }

    public function orWhereNotNull($column) {
        $this->wheres[] = "OR " . Helpers::quoteIdentifier($column) . " IS NOT NULL";
        return $this;
    }

    // WHERE DATE/TIME PARTS
    public function whereDate($column, $value) {
        $this->wheres[] = "DATE(" . Helpers::quoteIdentifier($column) . ") = %s";
        $this->bindings[] = $value;
        return $this;
    }
    public function whereMonth($column, $value) {
        $this->wheres[] = "MONTH(" . Helpers::quoteIdentifier($column) . ") = %s";
        $this->bindings[] = $value;
        return $this;
    }
    public function whereDay($column, $value) {
        $this->wheres[] = "DAY(" . Helpers::quoteIdentifier($column) . ") = %s";
        $this->bindings[] = $value;
        return $this;
    }
    public function whereYear($column, $value) {
        $this->wheres[] = "YEAR(" . Helpers::quoteIdentifier($column) . ") = %s";
        $this->bindings[] = $value;
        return $this;
    }
    public function whereTime($column, $value) {
        $this->wheres[] = "TIME(" . Helpers::quoteIdentifier($column) . ") = %s";
        $this->bindings[] = $value;
        return $this;
    }

    // WHERE PAST/FUTURE/TODAY
    public function wherePast($column) {
        $this->wheres[] = Helpers::quoteIdentifier($column) . " < CURDATE()";
        return $this;
    }
    public function whereFuture($column) {
        $this->wheres[] = Helpers::quoteIdentifier($column) . " > CURDATE()";
        return $this;
    }
    public function whereToday($column) {
        $this->wheres[] = "DATE(" . Helpers::quoteIdentifier($column) . ") = CURDATE()";
        return $this;
    }
    public function whereBeforeToday($column) {
        $this->wheres[] = "DATE(" . Helpers::quoteIdentifier($column) . ") < CURDATE()";
        return $this;
    }
    public function whereAfterToday($column) {
        $this->wheres[] = "DATE(" . Helpers::quoteIdentifier($column) . ") > CURDATE()";
        return $this;
    }

    // WHERE COLUMN COMPARISON
    public function whereColumn($first, $operator, $second = null) {
        if ($second === null) {
            $second = $operator;
            $operator = '=';
        }
    $this->wheres[] = Helpers::quoteIdentifier($first) . " $operator " . Helpers::quoteIdentifier($second);
        return $this;
    }
    public function orWhereColumn($first, $operator, $second = null) {
        if ($second === null) {
            $second = $operator;
            $operator = '=';
        }
    $this->wheres[] = "OR " . Helpers::quoteIdentifier($first) . " $operator " . Helpers::quoteIdentifier($second);
        return $this;
    }

    public function orderBy($column, $direction = 'asc') {
      $this->orders[] = Helpers::quoteIdentifier($column) . ' ' . $direction;
        return $this;
    }

    /**
     * Add a raw ORDER BY clause. Bindings (if any) will be appended to the query bindings
     * and the raw SQL will be used as-is in the ORDER BY clause.
     * Usage: ->orderByRaw('FIELD(status, ?, ?)', ['active','pending'])
     */
    public function orderByRaw($sql, array $bindings = []) {
        $this->orders[] = ['raw' => $sql, 'bindings' => $bindings];
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

    /**
     * Include soft-deleted records in results.
     */
    public $withTrashed = false;

    /**
     * Only return soft-deleted records.
     */
    public $onlyTrashed = false;

    /**
     * Tracks whether the automatic soft-delete constraint has already been applied.
     */
    protected $softDeleteScopeApplied = false;

    /**
     * Apply the model's soft-delete constraint once for read/count queries.
     */
    protected function applySoftDeleteScope() {
        if ($this->softDeleteScopeApplied) {
            return;
        }

        if (!isset($this->model->softDeletes) || !$this->model->softDeletes) {
            $this->softDeleteScopeApplied = true;
            return;
        }

        $deletedAt = $this->model->deletedAtColumn;
        $softDeleteType = isset($this->model->softDeleteType) ? $this->model->softDeleteType : 'timestamp';

        if ($softDeleteType === 'boolean') {
            if ($this->onlyTrashed) {
                $this->where($deletedAt, 1);
            } elseif (!$this->withTrashed) {
                $this->where($deletedAt, 0);
            }
        } else {
            if ($this->onlyTrashed) {
                $this->whereNotNull($deletedAt);
            } elseif (!$this->withTrashed) {
                $this->whereNull($deletedAt);
            }
        }

        $this->softDeleteScopeApplied = true;
    }

    /**
     * Restore soft-deleted records matching the query.
     * Supports both timestamp and boolean-flag soft deletes via SoftDeletes trait.
     */
    public function restore() {
        // Support both timestamp and boolean-flag soft deletes
        if (isset($this->model->softDeletes) && $this->model->softDeletes) {
            $deletedAt = $this->model->deletedAtColumn;
            // If using boolean flag (e.g., deleted = 1/0)
            if (isset($this->model->softDeleteType) && $this->model->softDeleteType === 'boolean') {
                return $this->update([$deletedAt => 0]);
            }
            // Default: timestamp (e.g., deleted_at)
            return $this->update([$deletedAt => null]);
        }
        return false;
    }

    public function get() {
        $this->applySoftDeleteScope();
        $sql = $this->buildSelectQuery();
        $bindings = $this->getBindings();
        if (!empty($bindings)) {
            $sql = $this->wpdb->prepare($sql, ...$bindings);
        }
        // If bindings are empty, do not call prepare
        if ($this->debug) {
            error_log('[WPORM][get] SQL: ' . $sql);
            error_log('[WPORM][get] Bindings: ' . print_r($bindings, true));
        }
        $results = $this->wpdb->get_results($sql, ARRAY_A);
        if (!$results) return new \MJ\WPORM\Collection([]);
        $modelClass = get_class($this->model);
        $models = array_map(function ($row) use ($modelClass) {
            $instance = (new $modelClass)->newFromBuilder($row);
            if (method_exists($instance, 'retrieved')) {
                $instance->retrieved();
            }
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
        return new \MJ\WPORM\Collection($models);
    }

    public function first() {
        $this->limit(1);
        // get() already handles eager loading and retrieved() for each model.
        $results = $this->get();
        $model = $results[0] ?? null;
        if ($this->debug) {
            error_log('[WPORM][first] Results: ' . print_r($results, true));
        }
        return $model;
    }

    public function count() {
        $this->applySoftDeleteScope();
        $sql = $this->buildCountQuery();
        if ($this->debug) {
            error_log('[WPORM][count] SQL: ' . $sql);
            error_log('[WPORM][count] Bindings: ' . print_r($this->bindings, true));
        }
        if (!empty($this->bindings)) {
            return (int) $this->wpdb->get_var($this->wpdb->prepare($sql, ...$this->bindings));
        } else {
            return (int) $this->wpdb->get_var($sql);
        }
    }

    public function delete() {
        $sql = $this->buildDeleteQuery();
        if ($this->debug) {
            error_log('[WPORM][delete] SQL: ' . $sql);
            error_log('[WPORM][delete] Bindings: ' . print_r($this->bindings, true));
        }
        if (!empty($this->bindings)) {
            return $this->wpdb->query($this->wpdb->prepare($sql, ...$this->bindings));
        } else {
            return $this->wpdb->query($sql);
        }
    }

    /**
     * Truncate the model's table.
     * Usage: Model::query()->truncate();
     * This executes a TRUNCATE TABLE statement for the current model table.
     */
    public function truncate() {
        $sql = "TRUNCATE TABLE {$this->table}";
        if ($this->debug) {
            error_log('[WPORM][truncate] SQL: ' . $sql);
        }
        return $this->wpdb->query($sql);
    }

    // -------------------------------------------------------------------------
    // Transactions
    // -------------------------------------------------------------------------

    /**
     * Execute a Closure within a database transaction (Eloquent-style).
     *
     * Automatically commits when the callback returns without throwing, and
     * rolls back + re-throws on any exception. The optional $attempts
     * parameter retries the whole callback up to that many times when a
     * deadlock or lock-wait-timeout is detected (MySQL error codes 1213 and
     * 1205), mirroring Laravel's DB::transaction() retry behaviour.
     *
     * The return value of the callback is forwarded to the caller so the
     * method is useful both for side-effect-only work and for transactional
     * reads that need to return data:
     *
     *   $user = User::query()->transaction(function() {
     *       $u = User::create(['name' => 'Alice']);
     *       Profile::create(['user_id' => $u->id]);
     *       return $u;
     *   });
     *
     * Nested calls are safe: the method tracks an internal depth counter and
     * only issues START TRANSACTION / COMMIT / ROLLBACK at the outermost
     * level, so inner calls participate in the outer transaction transparently
     * (analogous to Eloquent's transaction nesting via savepoints, but
     * without requiring SAVEPOINT support).
     *
     * @param \Closure $callback Receives no arguments.
     * @param int $attempts Max number of tries before propagating the exception.
     * @return mixed Whatever the callback returns.
     * @throws \Throwable Re-throws the last exception after all attempts fail.
     */
    public function transaction(\Closure $callback, int $attempts = 1) {
        return static::runTransaction($this->wpdb, $callback, $attempts);
    }

    /**
     * Manually begin a database transaction.
     *
     * Prefer transaction() for most use cases — it commits and rolls back
     * automatically. Use these lower-level methods only when you need
     * explicit control over the transaction boundary across multiple
     * statements that cannot be wrapped in a single closure.
     *
     * @return void
     */
    public function beginTransaction() {
        $this->wpdb->query('START TRANSACTION');
    }

    /**
     * Commit the current database transaction.
     * @return void
     */
    public function commit() {
        $this->wpdb->query('COMMIT');
    }

    /**
     * Roll back the current database transaction.
     * @return void
     */
    public function rollBack() {
        $this->wpdb->query('ROLLBACK');
    }

    /**
     * Shared implementation used by both QueryBuilder::transaction() and
     * DB::transaction(). Isolated here so DB can reuse the exact same logic
     * without duplicating it or having to instantiate a QueryBuilder.
     *
     * @param \wpdb $wpdb
     * @param \Closure $callback
     * @param int $attempts
     * @return mixed
     * @throws \Throwable
     */
    public static function runTransaction($wpdb, \Closure $callback, int $attempts = 1) {
        $attempts = max(1, $attempts);

        for ($currentAttempt = 1; $currentAttempt <= $attempts; $currentAttempt++) {
            $wpdb->query('START TRANSACTION');

            try {
                $result = $callback();
                $wpdb->query('COMMIT');
                return $result;
            } catch (\Throwable $e) {
                $wpdb->query('ROLLBACK');

                // Retry only on deadlock (1213) or lock-wait timeout (1205).
                if ($currentAttempt < $attempts && static::causedByDeadlock($e)) {
                    continue;
                }

                throw $e;
            }
        }
    }

    /**
     * Determine whether a Throwable was caused by a MySQL deadlock or
     * lock-wait timeout, which are safe to retry.
     *
     * MySQL error 1213 = "Deadlock found when trying to get lock"
     * MySQL error 1205 = "Lock wait timeout exceeded"
     *
     * @param \Throwable $e
     * @return bool
     */
    protected static function causedByDeadlock(\Throwable $e): bool {
        $message = $e->getMessage();
        return strpos($message, '1213') !== false
            || stripos($message, 'Deadlock') !== false
            || strpos($message, '1205') !== false
            || stripos($message, 'Lock wait timeout') !== false;
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
        if ($first instanceof \Closure) {
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
                'clause' => Helpers::quoteIdentifier($first) . " $operator " . Helpers::quoteIdentifier($second),
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
            $this->groups[] = Helpers::quoteIdentifier($col);
        }
        return $this;
    }

    /**
     * Add a raw SQL GROUP BY expression, with optional bindings.
     * Usage: ->groupByRaw('DATE(created_at)')
     *        ->groupByRaw('YEAR(created_at), MONTH(created_at)')
     *
     * @param string $sql
     * @param array $bindings
     * @return $this
     */
    public function groupByRaw($sql, array $bindings = []) {
        $this->groups[] = ['raw' => $sql, 'bindings' => $bindings];
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

    /**
     * Add an OR HAVING clause to the query.
     * Example: ->orHaving('count', '>', 5)
     */
    public function orHaving($column, $operator = null, $value = null) {
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }
        $this->havings[] = ["OR $column $operator %s", [$value]];
        return $this;
    }

    /**
     * Add an OR HAVING BETWEEN ... AND ... clause to the query.
     * Example: ->orHavingBetween('score', [10, 20])
     */
    public function orHavingBetween($column, array $values) {
        if (count($values) !== 2) {
            throw new \InvalidArgumentException('orHavingBetween expects exactly 2 values.');
        }
        $this->havings[] = ["OR $column BETWEEN %s AND %s", $values];
        return $this;
    }

    /**
     * Add a raw SQL HAVING clause, with optional bindings.
     * Usage: ->havingRaw('COUNT(*) > %s', [5])
     *        ->havingRaw('SUM(total) > %s', [1000])
     *
     * @param string $sql
     * @param array $bindings
     * @return $this
     */
    public function havingRaw($sql, array $bindings = []) {
        $this->havings[] = [$sql, $bindings];
        return $this;
    }

    /**
     * Add a raw SQL OR HAVING clause, with optional bindings.
     * Usage: ->orHavingRaw('SUM(total) > %s', [1000])
     *
     * @param string $sql
     * @param array $bindings
     * @return $this
     */
    public function orHavingRaw($sql, array $bindings = []) {
        $this->havings[] = ["OR $sql", $bindings];
        return $this;
    }

    /**
     * Disable global scopes for this query.
     * Usage: Model::query()->withoutGlobalScopes()
     */
    public function withoutGlobalScopes() {
        $this->applyGlobalScopes = false;
        return $this;
    }

    /**
     * Return the raw SQL with placeholders (for debugging).
     */
    public function toSql() {
        return $this->buildSelectQuery();
    }

    /**
     * Return the current bindings array (for debugging).
     *
     * Bindings must be returned in the same left-to-right order their
     * placeholders appear in the final SQL string (SELECT, then WHERE,
     * then GROUP BY, then HAVING, then ORDER BY) so that $wpdb->prepare()
     * substitutes them correctly.
     */
    public function getBindings() {
        return array_merge(
            $this->getSelectBindings(),
            $this->bindings,
            $this->getGroupByBindings(),
            $this->getHavingBindings(),
            $this->getOrderByBindings()
        );
    }

    /**
     * Bindings from selectRaw() entries, in SELECT-list order.
     */
    protected function getSelectBindings() {
        $bindings = [];
        foreach ($this->selects as $select) {
            if (is_array($select) && isset($select['raw']) && !empty($select['bindings'])) {
                foreach ($select['bindings'] as $value) {
                    $bindings[] = $value;
                }
            }
        }
        return $bindings;
    }

    /**
     * Bindings from groupByRaw() entries, in GROUP BY-list order.
     */
    protected function getGroupByBindings() {
        $bindings = [];
        foreach ($this->groups as $group) {
            if (is_array($group) && isset($group['raw']) && !empty($group['bindings'])) {
                foreach ($group['bindings'] as $value) {
                    $bindings[] = $value;
                }
            }
        }
        return $bindings;
    }

    /**
     * Bindings from having()/havingBetween()/havingRaw() (and OR variants) entries.
     */
    protected function getHavingBindings() {
        $bindings = [];
        foreach ($this->havings as [$expr, $values]) {
            foreach ($values as $value) {
                $bindings[] = $value;
            }
        }
        return $bindings;
    }

    /**
     * Bindings from orderByRaw() entries.
     */
    protected function getOrderByBindings() {
        $bindings = [];
        foreach ($this->orders as $order) {
            if (is_array($order) && isset($order['raw']) && !empty($order['bindings'])) {
                foreach ($order['bindings'] as $value) {
                    $bindings[] = $value;
                }
            }
        }
        return $bindings;
    }

    /**
     * Dump the SQL and bindings for debugging.
     */
    public function dumpSql() {
        $sql = $this->toSql();
        $bindings = $this->getBindings();
        if (php_sapi_name() === 'cli') {
            echo "[WPORM][dumpSql] SQL: $sql\n";
            echo "[WPORM][dumpSql] Bindings: ".print_r($bindings, true)."\n";
        } else {
            echo '<pre>[WPORM][dumpSql] SQL: ' . htmlspecialchars($sql) . "\n";
            echo '[WPORM][dumpSql] Bindings: ' . htmlspecialchars(print_r($bindings, true)) . "</pre>\n";
        }
        return $this;
    }

    /**
     * Get the raw SQL query with bindings replaced (Laravel-style toRawSQL).
     * Usage: ->toRawSQL()
     * Returns the SQL string with bindings interpolated for debugging.
     */
    public function toRawSQL() {
        $sql = $this->toSql();
        $bindings = $this->getBindings();
        if (empty($bindings)) return $sql;
        // Use wpdb->prepare to safely interpolate bindings for debugging
        return $this->wpdb->prepare($sql, ...$bindings);
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

    /**
     * Set the query to return distinct results (Eloquent-style).
     * Usage: ->distinct()
     */
    protected $isDistinct = false;

    public function distinct($value = true) {
        $this->isDistinct = (bool)$value;
        return $this;
    }

    /**
     * Conditionally add query constraints (Eloquent-style when()).
     * Usage: $query->when($condition, function($q) { ... }, function($q) { ... });
     *
     * @param mixed $value Condition value
     * @param callable $callback Callback if condition is truthy
     * @param callable|null $default Callback if condition is falsy
     * @return $this
     */
    public function when($value, callable $callback, ?callable $default = null) {
        if ($value) {
            $callback($this, $value);
        } elseif ($default) {
            $default($this, $value);
        }
        return $this;
    }

    /**
     * Build the WHERE clause string from $this->wheres, correctly handling OR prefixes.
     * Returns the clause WITHOUT the leading "WHERE" keyword, or empty string if no conditions.
     * Also quotes dot-notation identifiers (table.column).
     */
    protected function buildWhereClause() {
        if (empty($this->wheres)) {
            return '';
        }
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
        $where = preg_replace_callback('/([a-zA-Z0-9_]+\.[a-zA-Z0-9_]+)/', function($m) {
            return Helpers::quoteIdentifier($m[1]);
        }, $where);
        return $where;
    }

    protected function buildSelectQuery() {
        $where = $this->buildWhereClause();
        // Quote columns in SELECT, passing raw entries through as-is
        $selects = array_map(function($col) {
            if (is_array($col) && isset($col['raw'])) {
                return $col['raw'];
            }
            return Helpers::quoteIdentifier($col);
        }, $this->selects);
        $sql = "SELECT ";
        if ($this->isDistinct) {
            $sql .= "DISTINCT ";
        }
    $sql .= implode(", ", $selects) . " FROM " . Helpers::quoteIdentifier($this->table);
        // Add JOIN clauses
        if (!empty($this->joins)) {
            foreach ($this->joins as $join) {
                $type = $join['type'];
                $table = Helpers::quoteIdentifier($join['table']);
                if ($type === 'CROSS') {
                    $sql .= " CROSS JOIN $table";
                } elseif ($join['clause']) {
                    // Try to quote identifiers in ON clause
                    $clause = preg_replace_callback('/([a-zA-Z0-9_]+\.[a-zA-Z0-9_]+)/', function($m) {
                        return Helpers::quoteIdentifier($m[1]);
                    }, $join['clause']);
                    $sql .= " $type JOIN $table ON {$clause}";
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
            $groups = array_map(function($g) {
                if (is_array($g) && isset($g['raw'])) {
                    return $g['raw'];
                }
                return Helpers::quoteIdentifier($g);
            }, $this->groups);
            $sql .= " GROUP BY " . implode(", ", $groups);
        }
        // HAVING
        if (!empty($this->havings)) {
            $havingParts = [];
            foreach ($this->havings as [$expr, $vals]) {
                // Quote identifiers in HAVING
                $expr = preg_replace_callback('/([a-zA-Z0-9_]+\.[a-zA-Z0-9_]+)/', function($m) {
                    return Helpers::quoteIdentifier($m[1]);
                }, $expr);
                $havingParts[] = $expr;
            }
            $sql .= " HAVING " . implode(' AND ', $havingParts);
        }
        if (!empty($this->orders)) {
            $orderParts = [];
            foreach ($this->orders as $order) {
                // Support raw order entries with bindings
                if (is_array($order) && isset($order['raw'])) {
                    $orderParts[] = $order['raw'];
                    continue;
                }
                // Split by space to get column and direction
                if (preg_match('/^([a-zA-Z0-9_\.]+)\s+(asc|desc)$/i', $order, $m)) {
                    $orderParts[] = Helpers::quoteIdentifier($m[1]) . ' ' . strtoupper($m[2]);
                } elseif (preg_match('/^([a-zA-Z0-9_\.]+)$/', $order, $m)) {
                    $orderParts[] = Helpers::quoteIdentifier($m[1]);
                } else {
                    $orderParts[] = $order;
                }
            }
            $sql .= " ORDER BY " . implode(", ", $orderParts);
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
    $sql = "SELECT COUNT(*) FROM " . Helpers::quoteIdentifier($this->table);
        $where = $this->buildWhereClause();
        if (!empty($where)) {
            $sql .= " WHERE $where";
        }
        return $sql;
    }

    protected function buildDeleteQuery() {
    $sql = "DELETE FROM " . Helpers::quoteIdentifier($this->table);
        $where = $this->buildWhereClause();
        if (!empty($where)) {
            $sql .= " WHERE $where";
        }
        return $sql;
    }

    /**
     * Eager-load a single relation onto an array of already-hydrated models.
     *
     * All relationship types are driven by the relationContext metadata that
     * the relationship methods (hasOne, hasMany, belongsTo, belongsToMany,
     * hasManyThrough) embed on the returned QueryBuilder. This eliminates the
     * need for fragile reflection-based key inference.
     *
     * @param array  &$models    Array of model instances to populate (mutated in-place)
     * @param string  $relation  Name of the relation method on the model
     * @param mixed   $constraint Closure|array|null — additional query constraints or
     *                            an options array (keys: 'constraint', 'disableGlobalScopes')
     */
    protected function eagerLoadRelation(array &$models, $relation, $constraint = null) {
        if (empty($models)) return;

        $firstModel = $models[0];
        if (!method_exists($firstModel, $relation)) return;

        // ── Parse constraint / options array ──────────────────────────────────
        $disableGlobalScopes = false;
        if (is_array($constraint)) {
            if (isset($constraint['disableGlobalScopes'])) {
                $disableGlobalScopes = (bool) $constraint['disableGlobalScopes'];
            }
            if (isset($constraint['constraint']) && is_callable($constraint['constraint'])) {
                $constraint = $constraint['constraint'];
            } else {
                $found = null;
                foreach ($constraint as $c) {
                    if (is_callable($c)) { $found = $c; break; }
                }
                $constraint = $found;
            }
        }

        // ── Call the relation on a *fresh* (attribute-less) instance so that
        //    belongsTo / hasOne etc. don't embed a single concrete FK value
        //    into the query — we always rebuild using whereIn below.        ──
        $sampleQuery = $firstModel->$relation();

        // All relations must return a QueryBuilder; if somehow a plain model
        // came back (old-style) fall through to a safe no-op.
        if (!($sampleQuery instanceof \MJ\WPORM\QueryBuilder)) {
            return;
        }

        $ctx  = $sampleQuery->getRelationContext();
        $type = $ctx['type'] ?? null;

        // ══════════════════════════════════════════════════════════════════════
        // belongsTo — FK lives on *this* model, PK lives on the related model
        // ══════════════════════════════════════════════════════════════════════
        if ($type === 'belongsTo') {
            $foreignKey = $ctx['foreignKey'];
            $ownerKey   = $ctx['ownerKey'];
            $relClass   = $ctx['related'];

            $ids = array_values(array_unique(array_filter(
                array_map(fn($m) => $m->$foreignKey, $models),
                fn($id) => $id !== null
            )));

            if (empty($ids)) {
                foreach ($models as $m) $m->setEagerLoaded($relation, null);
                return;
            }

            $query = $relClass::query(!$disableGlobalScopes)->whereIn($ownerKey, $ids);
            if ($constraint) $constraint($query);

            $map = [];
            foreach ($query->get() as $rel) {
                $map[$rel->$ownerKey] = $rel;
            }
            foreach ($models as $m) {
                $m->setEagerLoaded($relation, $map[$m->$foreignKey] ?? null);
            }
            return;
        }

        // ══════════════════════════════════════════════════════════════════════
        // hasOne — FK on related table, single result per parent
        // ══════════════════════════════════════════════════════════════════════
        if ($type === 'hasOne') {
            $foreignKey = $ctx['foreignKey'];
            $localKey   = $ctx['localKey'];
            $relClass   = $ctx['related'];

            $ids = array_values(array_unique(array_map(fn($m) => $m->$localKey, $models)));

            $query = $relClass::query(!$disableGlobalScopes)->whereIn($foreignKey, $ids);
            if ($constraint) $constraint($query);

            $map = [];
            foreach ($query->get() as $rel) {
                // Keep only the first match (hasOne semantics)
                if (!isset($map[$rel->$foreignKey])) {
                    $map[$rel->$foreignKey] = $rel;
                }
            }
            foreach ($models as $m) {
                $m->setEagerLoaded($relation, $map[$m->$localKey] ?? null);
            }
            return;
        }

        // ══════════════════════════════════════════════════════════════════════
        // hasMany — FK on related table, multiple results per parent
        // ══════════════════════════════════════════════════════════════════════
        if ($type === 'hasMany') {
            $foreignKey = $ctx['foreignKey'];
            $localKey   = $ctx['localKey'];
            $relClass   = $ctx['related'];

            $ids = array_values(array_unique(array_map(fn($m) => $m->$localKey, $models)));

            $query = $relClass::query(!$disableGlobalScopes)->whereIn($foreignKey, $ids);
            if ($constraint) $constraint($query);

            $grouped = [];
            foreach ($query->get() as $rel) {
                $grouped[$rel->$foreignKey][] = $rel;
            }
            foreach ($models as $m) {
                $m->setEagerLoaded($relation, new \MJ\WPORM\Collection($grouped[$m->$localKey] ?? []));
            }
            return;
        }

        // ══════════════════════════════════════════════════════════════════════
        // belongsToMany — many-to-many via pivot table
        // ══════════════════════════════════════════════════════════════════════
        if ($type === 'belongsToMany') {
            $pivotTable      = $ctx['pivotTable'];
            $foreignPivotKey = $ctx['foreignPivotKey']; // FK for *this* model on pivot
            $relatedPivotKey = $ctx['relatedPivotKey']; // FK for related model on pivot
            $localKey        = $ctx['localKey'];
            $relatedTable    = $ctx['relatedTable'];
            $relClass        = $ctx['related'];

            $ids = array_values(array_unique(array_map(fn($m) => $m->$localKey, $models)));

            // Load related rows joining through the pivot; also SELECT the pivot FK
            // so we can group results back to each parent.
            $relatedInstance = new $relClass;
            $relatedPK       = $relatedInstance->primaryKey;

            $query = $relClass::query(!$disableGlobalScopes)
                ->select(["$relatedTable.*", "$pivotTable.$foreignPivotKey as _pivot_fk"])
                ->join($pivotTable, "$relatedTable.$relatedPK", '=', "$pivotTable.$relatedPivotKey")
                ->whereIn("$pivotTable.$foreignPivotKey", $ids);

            if ($constraint) $constraint($query);

            $grouped = [];
            foreach ($query->get() as $rel) {
                $pivotFk = $rel->_pivot_fk ?? null;
                if ($pivotFk !== null) {
                    // Remove the internal alias from the model's attributes so it
                    // does not appear in toArray() / toJson() output.
                    $rel->forgetAttribute('_pivot_fk');
                    $grouped[$pivotFk][] = $rel;
                }
            }
            foreach ($models as $m) {
                $m->setEagerLoaded($relation, new \MJ\WPORM\Collection($grouped[$m->$localKey] ?? []));
            }
            return;
        }

        // ══════════════════════════════════════════════════════════════════════
        // hasManyThrough — results reached via an intermediate (through) table
        // ══════════════════════════════════════════════════════════════════════
        if ($type === 'hasManyThrough') {
            $firstKey    = $ctx['firstKey'];    // FK on through table → this model
            $secondKey   = $ctx['secondKey'];   // FK on related table → through table
            $localKey    = $ctx['localKey'];    // PK on this model
            $relatedTable  = $ctx['relatedTable'];
            $throughTable  = $ctx['throughTable'];
            $throughPK     = $ctx['throughPK'];
            $relClass      = $ctx['related'];

            $ids = array_values(array_unique(array_map(fn($m) => $m->$localKey, $models)));

            // Fetch related rows with the through-table FK so we can group by parent.
            $query = $relClass::query(!$disableGlobalScopes)
                ->select(["$relatedTable.*", "$throughTable.$firstKey as _through_fk"])
                ->join($throughTable, "$relatedTable.$secondKey", '=', "$throughTable.$throughPK")
                ->whereIn("$throughTable.$firstKey", $ids);

            if ($constraint) $constraint($query);

            $grouped = [];
            foreach ($query->get() as $rel) {
                $throughFk = $rel->_through_fk ?? null;
                if ($throughFk !== null) {
                    // Remove internal alias from output.
                    $rel->forgetAttribute('_through_fk');
                    $grouped[$throughFk][] = $rel;
                }
            }
            foreach ($models as $m) {
                $m->setEagerLoaded($relation, new \MJ\WPORM\Collection($grouped[$m->$localKey] ?? []));
            }
            return;
        }

        // ── Unknown / unsupported relation type: no-op (fail silently) ───────
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

    /**
     * Update records matching the current query.
     * Usage:
     *   ->update(['col' => 'val', ...])
     *   ->update('col', 'val')
     * Returns number of affected rows.
     */
    public function update($data, $value = null) {
        if (!is_array($data)) {
            // Single column, value
            $data = [$data => $value];
        }
        if (empty($data)) {
            throw new \InvalidArgumentException('No data provided for update.');
        }
        $set = [];
        $bindings = [];
        foreach ($data as $col => $val) {
            $set[] = Helpers::quoteIdentifier($col) . ' = %s';
            $bindings[] = $val;
        }
        $sql = 'UPDATE ' . Helpers::quoteIdentifier($this->table) . ' SET ' . implode(', ', $set);
        $where = $this->buildWhereClause();
        if (!empty($where)) {
            $sql .= ' WHERE ' . $where;
        }
        $allBindings = array_merge($bindings, $this->bindings);
        if ($this->debug) {
            error_log('[WPORM][update] SQL: ' . $sql);
            error_log('[WPORM][update] Bindings: ' . print_r($allBindings, true));
        }
        if (!empty($allBindings)) {
            return $this->wpdb->query($this->wpdb->prepare($sql, ...$allBindings));
        } else {
            return $this->wpdb->query($sql);
        }
    }

    /**
     * Insert or update multiple records in a single query (Eloquent-style upsert).
     *
     * Uses MySQL INSERT ... ON DUPLICATE KEY UPDATE syntax.
     *
     * @param array $values Array of records to upsert (each record is an associative array).
     * @param array|string $uniqueBy Column(s) that uniquely identify records (used for ON DUPLICATE KEY).
     * @param array|null $update Columns to update on duplicate. If null, all columns except $uniqueBy are updated.
     * @return int|false Number of affected rows or false on failure.
     *
     * Usage:
     *   DB::table('users')->upsert([
     *       ['email' => 'a@test.com', 'name' => 'Alice', 'votes' => 1],
     *       ['email' => 'b@test.com', 'name' => 'Bob', 'votes' => 2],
     *   ], ['email'], ['name', 'votes']);
     */
    public function upsert(array $values, $uniqueBy, $update = null)
    {
        if (empty($values)) {
            return 0;
        }

        // Normalize to array of arrays
        if (!isset($values[0]) || !is_array($values[0])) {
            $values = [$values];
        }

        $uniqueBy = (array) $uniqueBy;

        // Determine columns from first record
        $columns = array_keys($values[0]);

        // Add timestamps if the model supports them
        $hasTimestamps = property_exists($this->model, 'timestamps') && $this->model->timestamps;
        $createdAtColumn = $this->model->createdAtColumn ?? 'created_at';
        $updatedAtColumn = $this->model->updatedAtColumn ?? 'updated_at';

        if ($hasTimestamps) {
            $now = current_time('mysql');
            if (!in_array($createdAtColumn, $columns)) {
                $columns[] = $createdAtColumn;
            }
            if (!in_array($updatedAtColumn, $columns)) {
                $columns[] = $updatedAtColumn;
            }
            foreach ($values as $index => $row) {
                if (!isset($values[$index][$createdAtColumn])) {
                    $values[$index][$createdAtColumn] = $now;
                }
                if (!isset($values[$index][$updatedAtColumn])) {
                    $values[$index][$updatedAtColumn] = $now;
                }
            }
        }

        // If update columns not specified, update all columns except the unique key columns
        if ($update === null) {
            $update = array_values(array_diff($columns, $uniqueBy));
        }

        // Resolve table name with prefix
        $tableName = method_exists($this->model, 'getTable') ? $this->model->getTable() : $this->table;

        if (empty($update)) {
            // Nothing to update on duplicate — use INSERT IGNORE
            $placeholdersRow = '(' . implode(', ', array_fill(0, count($columns), '%s')) . ')';
            $allPlaceholders = implode(', ', array_fill(0, count($values), $placeholdersRow));
            $allValues = [];
            foreach ($values as $row) {
                foreach ($columns as $col) {
                    $allValues[] = $row[$col] ?? null;
                }
            }
            $sql = sprintf('INSERT IGNORE INTO %s (%s) VALUES %s', $tableName, implode(', ', array_map([Helpers::class, 'quoteIdentifier'], $columns)), $allPlaceholders);
            return $this->wpdb->query($this->wpdb->prepare($sql, ...$allValues));
        }

        // Build placeholders
        $placeholdersRow = '(' . implode(', ', array_fill(0, count($columns), '%s')) . ')';
        $allPlaceholders = implode(', ', array_fill(0, count($values), $placeholdersRow));

        $allValues = [];
        foreach ($values as $row) {
            foreach ($columns as $col) {
                $allValues[] = $row[$col] ?? null;
            }
        }

        // Build ON DUPLICATE KEY UPDATE clause
        $updateParts = [];
        foreach ($update as $col) {
            $quoted = Helpers::quoteIdentifier($col);
            $updateParts[] = $quoted . ' = VALUES(' . $quoted . ')';
        }

        // Always update the updated_at timestamp on duplicate if timestamps are enabled
        if ($hasTimestamps && !in_array($updatedAtColumn, $update)) {
            $quoted = Helpers::quoteIdentifier($updatedAtColumn);
            $updateParts[] = $quoted . ' = VALUES(' . $quoted . ')';
        }

        $quotedColumns = array_map([Helpers::class, 'quoteIdentifier'], $columns);

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES %s ON DUPLICATE KEY UPDATE %s',
            $tableName,
            implode(', ', $quotedColumns),
            $allPlaceholders,
            implode(', ', $updateParts)
        );

        if ($this->debug) {
            error_log('[WPORM][upsert] SQL: ' . $sql);
            error_log('[WPORM][upsert] Bindings: ' . print_r($allValues, true));
        }

        return $this->wpdb->query($this->wpdb->prepare($sql, ...$allValues));
    }

    /**
     * Create and save multiple records at once inside a single transaction
     * (Eloquent-style createMany). If any save fails, the whole operation is
     * rolled back and the exception is re-thrown.
     *
     * Usage: Model::query()->createMany([['col' => 'val'], ...])
     * Returns array of created model instances.
     */
    public function createMany(array $records) {
        $modelClass = get_class($this->model);
        return $this->transaction(function() use ($records, $modelClass) {
            $created = [];
            foreach ($records as $attributes) {
                $model = new $modelClass($attributes);
                if (!$model->save()) {
                    throw new \RuntimeException('Failed to save model in createMany');
                }
                $created[] = $model;
            }
            return $created;
        });
    }

    /**
     * Save multiple model instances at once inside a single transaction
     * (Eloquent-style saveMany). If any save fails, the whole operation is
     * rolled back and the exception is re-thrown.
     *
     * Usage: Model::query()->saveMany([$model1, $model2, ...])
     * Returns array of saved model instances.
     */
    public function saveMany(array $models) {
        return $this->transaction(function() use ($models) {
            $saved = [];
            foreach ($models as $model) {
                if (!$model->save()) {
                    throw new \RuntimeException('Failed to save model in saveMany');
                }
                $saved[] = $model;
            }
            return $saved;
        });
    }

    /**
     * Paginate the results (Eloquent-style).
     * Returns an array: [
     *   'data' => Collection,
     *   'total' => int,
     *   'per_page' => int,
     *   'current_page' => int,
     *   'last_page' => int,
     *   'from' => int,
     *   'to' => int
     * ]
     * Usage: ->paginate(10, 2) // 10 per page, page 2
     */
    public function paginate($perPage = 15, $page = null) {
        $perPage = (int)$perPage;
        $page = $page ?: (isset($_GET['page']) ? (int)$_GET['page'] : 1);
        $page = max($page, 1);
        $total = $this->count();
        $this->limit($perPage)->offset(($page - 1) * $perPage);
        $results = $this->get();
        $lastPage = (int) ceil($total / $perPage);
        $from = $total ? (($page - 1) * $perPage) + 1 : 0;
        $to = $from + count($results) - 1;
        return [
            'data' => $results,
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => $lastPage,
            'from' => $from,
            'to' => $to
        ];
    }

    /**
     * Simple paginate (no total count, more efficient for large tables).
     * Returns an array: [
     *   'data' => Collection,
     *   'per_page' => int,
     *   'current_page' => int,
     *   'next_page' => int|null
     * ]
     * Usage: ->simplePaginate(10, 2)
     */
    public function simplePaginate($perPage = 15, $page = null) {
        $perPage = (int)$perPage;
        $page = $page ?: (isset($_GET['page']) ? (int)$_GET['page'] : 1);
        $page = max($page, 1);
        $this->limit($perPage + 1)->offset(($page - 1) * $perPage);
        $results = $this->get();
        $hasMore = count($results) > $perPage;
        $data = $hasMore ? $results->slice(0, $perPage) : $results;
        return [
            'data' => $data,
            'per_page' => $perPage,
            'current_page' => $page,
            'next_page' => $hasMore ? $page + 1 : null
        ];
    }

    /**
     * Process the query results in chunks, running one LIMIT/OFFSET query per
     * chunk instead of loading the whole result set into memory at once
     * (Eloquent-style chunk()).
     *
     * The callback receives a Collection of up to $count models for each
     * chunk. Returning `false` from the callback stops processing early.
     *
     * Usage:
     *   User::query()->where('active', true)->chunk(100, function($users) {
     *       foreach ($users as $user) {
     *           // ...
     *       }
     *   });
     *
     * @param int $count Number of records per chunk
     * @param callable $callback function(Collection $chunk, int $page): mixed
     * @return bool false if the callback stopped iteration early, true otherwise
     */
    public function chunk($count, callable $callback) {
        $count = max((int) $count, 1);
        $page = 1;

        while (true) {
            $this->limit($count)->offset(($page - 1) * $count);
            $results = $this->get();

            $numResults = count($results);
            if ($numResults === 0) {
                break;
            }

            if ($callback($results, $page) === false) {
                return false;
            }

            if ($numResults < $count) {
                break;
            }

            $page++;
        }

        return true;
    }

    /**
     * Process the query results one record at a time, internally fetching
     * them in chunks for memory efficiency (Eloquent-style each()).
     *
     * The callback receives each individual model plus its zero-based index
     * across the whole result set. Returning `false` from the callback stops
     * processing early.
     *
     * Usage:
     *   User::query()->where('active', true)->each(function($user, $index) {
     *       // ...
     *   });
     *   User::query()->each(function($user) { ... }, 500); // custom chunk size
     *
     * @param callable $callback function(Model $item, int $index): mixed
     * @param int $count Number of records to fetch per underlying chunk query
     * @return bool false if the callback stopped iteration early, true otherwise
     */
    public function each(callable $callback, $count = 1000) {
        $index = 0;

        return $this->chunk($count, function($results) use ($callback, &$index) {
            foreach ($results as $item) {
                if ($callback($item, $index) === false) {
                    return false;
                }
                $index++;
            }
        });
    }

    /**
     * Filter by existence of related records (Eloquent-style whereHas).
     *
     * Calls the relation method on a fresh model instance so that no
     * concrete FK value from a real row leaks into the existence subquery.
     * The relation context carried on the returned QueryBuilder drives all
     * key resolution — no reflection needed.
     *
     * Usage: ->whereHas('posts', function($q) { $q->where('published', 1); })
     */
    public function whereHas($relation, $constraint = null) {
        $this->applyRelationExistenceClause('whereExists', $relation, $constraint);
        return $this;
    }

    /**
     * OR version of whereHas.
     */
    public function orWhereHas($relation, $constraint = null) {
        $this->applyRelationExistenceClause('orWhereExists', $relation, $constraint);
        return $this;
    }

    /**
     * Shared implementation for whereHas / orWhereHas.
     *
     * @param string   $existsMethod  'whereExists' or 'orWhereExists'
     * @param string   $relation      Relation method name on the model
     * @param callable|null $constraint Additional WHERE constraints for the subquery
     */
    protected function applyRelationExistenceClause($existsMethod, $relation, $constraint = null) {
        $model = $this->model;

        if (!method_exists($model, $relation)) {
            throw new \InvalidArgumentException(
                "Relation '$relation' not defined on model " . get_class($model)
            );
        }

        // Call on a *fresh* instance so no concrete attribute values pollute the subquery.
        $relQuery = $model->$relation();

        if (!($relQuery instanceof self)) {
            throw new \InvalidArgumentException(
                "Relation '$relation' must return a QueryBuilder"
            );
        }

        $ctx  = $relQuery->getRelationContext();
        $type = $ctx['type'] ?? null;

        $outerTable = $this->table;

        // ── belongsTo ────────────────────────────────────────────────────────
        // EXISTS (SELECT 1 FROM related WHERE related.ownerKey = outer.foreignKey [AND ...])
        if ($type === 'belongsTo') {
            $foreignKey = $ctx['foreignKey'];
            $ownerKey   = $ctx['ownerKey'];
            $relatedTable = $relQuery->table;
            $baseWhereCount = $ctx['baseWhereCount'] ?? 0;

            $this->$existsMethod(function($q) use (
                $relQuery, $relatedTable, $ownerKey, $foreignKey, $outerTable,
                $baseWhereCount, $constraint
            ) {
                $q->from($relatedTable)
                  ->whereColumn("$relatedTable.$ownerKey", '=', "$outerTable.$foreignKey");
                // Append only the user-added wheres (beyond the base FK constraint)
                foreach (array_slice($relQuery->wheres,   $baseWhereCount) as $w) {
                    $q->wheres[] = $w;
                }
                foreach (array_slice($relQuery->bindings, $baseWhereCount) as $b) {
                    $q->bindings[] = $b;
                }
                if ($constraint) $constraint($q);
            });
            return;
        }

        // ── hasOne / hasMany ──────────────────────────────────────────────────
        // EXISTS (SELECT 1 FROM related WHERE related.foreignKey = outer.localKey [AND ...])
        if ($type === 'hasOne' || $type === 'hasMany') {
            $foreignKey = $ctx['foreignKey'];
            $localKey   = $ctx['localKey'];
            $relatedTable = $relQuery->table;

            $this->$existsMethod(function($q) use (
                $relQuery, $relatedTable, $foreignKey, $localKey, $outerTable, $constraint
            ) {
                $q->from($relatedTable)
                  ->whereColumn("$relatedTable.$foreignKey", '=', "$outerTable.$localKey");
                if ($constraint) $constraint($q);
            });
            return;
        }

        // ── belongsToMany ─────────────────────────────────────────────────────
        // EXISTS (SELECT 1 FROM pivot WHERE pivot.foreignPivotKey = outer.localKey [AND ...])
        if ($type === 'belongsToMany') {
            $pivotTable      = $ctx['pivotTable'];
            $foreignPivotKey = $ctx['foreignPivotKey'];
            $localKey        = $ctx['localKey'];

            $this->$existsMethod(function($q) use (
                $pivotTable, $foreignPivotKey, $localKey, $outerTable, $constraint
            ) {
                $q->from($pivotTable)
                  ->whereColumn("$pivotTable.$foreignPivotKey", '=', "$outerTable.$localKey");
                if ($constraint) $constraint($q);
            });
            return;
        }

        // ── hasManyThrough ────────────────────────────────────────────────────
        // EXISTS (SELECT 1 FROM related JOIN through ON related.secondKey = through.throughPK
        //         WHERE through.firstKey = outer.localKey [AND ...])
        if ($type === 'hasManyThrough') {
            $firstKey     = $ctx['firstKey'];
            $secondKey    = $ctx['secondKey'];
            $localKey     = $ctx['localKey'];
            $relatedTable = $ctx['relatedTable'];
            $throughTable = $ctx['throughTable'];
            $throughPK    = $ctx['throughPK'];

            $this->$existsMethod(function($q) use (
                $relatedTable, $throughTable, $throughPK,
                $firstKey, $secondKey, $localKey, $outerTable, $constraint
            ) {
                $q->from($relatedTable)
                  ->join($throughTable, "$relatedTable.$secondKey", '=', "$throughTable.$throughPK")
                  ->whereColumn("$throughTable.$firstKey", '=', "$outerTable.$localKey");
                if ($constraint) $constraint($q);
            });
            return;
        }

        // ── Unknown relation type: fall back to passing through existing wheres ──
        $this->$existsMethod(function($q) use ($relQuery, $constraint) {
            foreach ($relQuery->wheres   as $w) { $q->wheres[]   = $w; }
            foreach ($relQuery->bindings as $b) { $q->bindings[] = $b; }
            if ($constraint) $constraint($q);
        });
    }

    /**
     * Filter by existence of related records (Eloquent-style has).
     *
     * ->has('posts')           → at least 1 related record
     * ->has('posts', '>=', 5) → at least 5 related records
     * ->has('posts', '=', 2)  → exactly 2 related records
     *
     * Implemented as a WHERE (SELECT COUNT(*) FROM related WHERE ...) OPERATOR COUNT
     * correlated subquery so the count constraint is correctly enforced.
     */
    public function has($relation, $operator = '>=', $count = 1) {
        if (func_num_args() === 1) {
            $operator = '>=';
            $count    = 1;
        } elseif (func_num_args() === 2) {
            // has('posts', 5) → interpret second arg as $count
            $count    = $operator;
            $operator = '>=';
        }

        $model = $this->model;
        if (!method_exists($model, $relation)) {
            throw new \InvalidArgumentException(
                "Relation '$relation' not defined on model " . get_class($model)
            );
        }

        // Build the correlated subquery for the count.
        $relQuery = $model->$relation();
        if (!($relQuery instanceof self)) {
            throw new \InvalidArgumentException(
                "Relation '$relation' must return a QueryBuilder"
            );
        }

        $ctx  = $relQuery->getRelationContext();
        $type = $ctx['type'] ?? null;

        $outerTable = $this->table;

        // ── hasOne / hasMany ─────────────────────────────────────────────────
        if ($type === 'hasOne' || $type === 'hasMany') {
            $foreignKey   = $ctx['foreignKey'];
            $localKey     = $ctx['localKey'];
            $relatedTable = $relQuery->table;

            $sub = new self($this->model, false);
            $sub->from($relatedTable)
                ->select(["COUNT(*)"])
                ->whereColumn("$relatedTable.$foreignKey", '=', "$outerTable.$localKey");

            $subSql = $sub->buildSelectQuery();
            $this->wheres[]   = "($subSql) $operator %s";
            $this->bindings[] = (int) $count;
            return $this;
        }

        // ── belongsTo ────────────────────────────────────────────────────────
        if ($type === 'belongsTo') {
            $foreignKey   = $ctx['foreignKey'];
            $ownerKey     = $ctx['ownerKey'];
            $relatedTable = $relQuery->table;

            $sub = new self($this->model, false);
            $sub->from($relatedTable)
                ->select(["COUNT(*)"])
                ->whereColumn("$relatedTable.$ownerKey", '=', "$outerTable.$foreignKey");

            $subSql = $sub->buildSelectQuery();
            $this->wheres[]   = "($subSql) $operator %s";
            $this->bindings[] = (int) $count;
            return $this;
        }

        // ── belongsToMany ─────────────────────────────────────────────────────
        if ($type === 'belongsToMany') {
            $pivotTable      = $ctx['pivotTable'];
            $foreignPivotKey = $ctx['foreignPivotKey'];
            $localKey        = $ctx['localKey'];

            $sub = new self($this->model, false);
            $sub->from($pivotTable)
                ->select(["COUNT(*)"])
                ->whereColumn("$pivotTable.$foreignPivotKey", '=', "$outerTable.$localKey");

            $subSql = $sub->buildSelectQuery();
            $this->wheres[]   = "($subSql) $operator %s";
            $this->bindings[] = (int) $count;
            return $this;
        }

        // ── hasManyThrough ────────────────────────────────────────────────────
        if ($type === 'hasManyThrough') {
            $firstKey     = $ctx['firstKey'];
            $secondKey    = $ctx['secondKey'];
            $localKey     = $ctx['localKey'];
            $relatedTable = $ctx['relatedTable'];
            $throughTable = $ctx['throughTable'];
            $throughPK    = $ctx['throughPK'];

            $sub = new self($this->model, false);
            $sub->from($relatedTable)
                ->select(["COUNT(*)"])
                ->join($throughTable, "$relatedTable.$secondKey", '=', "$throughTable.$throughPK")
                ->whereColumn("$throughTable.$firstKey", '=', "$outerTable.$localKey");

            $subSql = $sub->buildSelectQuery();
            $this->wheres[]   = "($subSql) $operator %s";
            $this->bindings[] = (int) $count;
            return $this;
        }

        // ── Fallback: delegate to whereHas (existence only, count ignored) ───
        return $this->whereHas($relation);
    }

    /**
     * Find a model by its primary key, or multiple models by an array of
     * primary keys (Eloquent-style).
     *
     * Usage: Model::query()->find($id)         // single id  -> Model|null
     *        Model::query()->find([1, 2, 3])    // array of ids -> Collection
     *        Model::with('rel')->find($id)
     *
     * @param mixed $id A single primary key value, or an array of values.
     * @return Model|\MJ\WPORM\Collection|null Model|null for a single id,
     *         Collection for an array of ids (missing ids are simply
     *         omitted, same as Eloquent).
     */
    public function find($id) {
        $primaryKey = $this->model->primaryKey ?? 'id';
        if (is_array($id)) {
            if (empty($id)) {
                return new \MJ\WPORM\Collection([]);
            }
            return $this->whereIn($primaryKey, $id)->get();
        }
        return $this->where($primaryKey, $id)->first();
    }

    /**
     * Find a model by its primary key, or throw a ModelNotFoundException if
     * no record matches (Eloquent-style). Identical to find() otherwise —
     * same single query, no extra DB round-trip is incurred just to check
     * existence first.
     *
     * When given an array of ids, behaves like Eloquent's findOrFail(): all
     * matching models are returned as a Collection, but if ANY of the
     * requested ids was not found, a ModelNotFoundException is thrown
     * listing every missing id (not just the first).
     *
     * Usage: Model::query()->findOrFail($id)
     *        Model::query()->findOrFail([1, 2, 3])
     *        Model::with('rel')->findOrFail($id)
     *
     * @param mixed $id A single primary key value, or an array of values.
     * @return Model|\MJ\WPORM\Collection
     * @throws ModelNotFoundException
     */
    public function findOrFail($id) {
        $result = $this->find($id);

        if (is_array($id)) {
            $primaryKey = $this->model->primaryKey ?? 'id';
            $foundIds = [];
            foreach ($result as $model) {
                $foundIds[] = $model->$primaryKey;
            }
            $missingIds = array_values(array_diff($id, $foundIds));

            if (!empty($missingIds)) {
                throw (new ModelNotFoundException())->setModel(get_class($this->model), $missingIds);
            }

            return $result;
        }

        if ($result === null) {
            throw (new ModelNotFoundException())->setModel(get_class($this->model), $id);
        }

        return $result;
    }

    /**
     * Get the first result matching the query, or throw a
     * ModelNotFoundException if nothing matched (Eloquent-style).
     * Identical to first() otherwise.
     *
     * Usage: Model::query()->where('email', $email)->firstOrFail()
     *
     * @return Model
     * @throws ModelNotFoundException
     */
    public function firstOrFail() {
        $result = $this->first();

        if ($result === null) {
            throw (new ModelNotFoundException())->setModel(get_class($this->model));
        }

        return $result;
    }

    // -------------------------------------------------------------------------
    // Aggregates & single-value helpers
    // -------------------------------------------------------------------------

    /**
     * Run an aggregate function (COUNT, SUM, AVG, MIN, MAX) against a column,
     * respecting the current WHERE/JOIN/GROUP BY constraints and the model's
     * soft-delete scope.
     *
     * @param string $function SQL aggregate function name, e.g. 'SUM', 'AVG'
     * @param string $column   Column to aggregate
     * @return mixed The scalar result, or null if no rows matched.
     */
    protected function aggregate($function, $column) {
        $this->applySoftDeleteScope();

        $quotedColumn = $column === '*' ? '*' : Helpers::quoteIdentifier($column);
        $selects = $this->selects;
        $this->selects = ["$function($quotedColumn) as aggregate"];

        $sql = $this->buildSelectQuery();
        $bindings = $this->getBindings();

        // Restore selects so the builder remains reusable.
        $this->selects = $selects;

        if ($this->debug) {
            error_log("[WPORM][$function] SQL: " . $sql);
            error_log("[WPORM][$function] Bindings: " . print_r($bindings, true));
        }

        if (!empty($bindings)) {
            $result = $this->wpdb->get_row($this->wpdb->prepare($sql, ...$bindings), ARRAY_A);
        } else {
            $result = $this->wpdb->get_row($sql, ARRAY_A);
        }

        return $result['aggregate'] ?? null;
    }

    /**
     * Get the sum of a column's values.
     * Usage: Order::query()->where('status', 'paid')->sum('total')
     * @param string $column
     * @return float|int
     */
    public function sum($column) {
        $result = $this->aggregate('SUM', $column);
        return $result === null ? 0 : $result + 0; // +0 promotes numeric string to int|float
    }

    /**
     * Get the average of a column's values.
     * Usage: Product::query()->avg('price')
     * @param string $column
     * @return float|int|null
     */
    public function avg($column) {
        $result = $this->aggregate('AVG', $column);
        return $result === null ? null : $result + 0;
    }

    /**
     * Alias for avg().
     * @param string $column
     * @return float|int|null
     */
    public function average($column) {
        return $this->avg($column);
    }

    /**
     * Get the minimum value of a column.
     * Usage: Product::query()->min('price')
     * @param string $column
     * @return mixed
     */
    public function min($column) {
        $result = $this->aggregate('MIN', $column);
        return is_numeric($result) ? $result + 0 : $result;
    }

    /**
     * Get the maximum value of a column.
     * Usage: Product::query()->max('price')
     * @param string $column
     * @return mixed
     */
    public function max($column) {
        $result = $this->aggregate('MAX', $column);
        return is_numeric($result) ? $result + 0 : $result;
    }

    /**
     * Get a single column's value from the first matching row.
     * Usage: User::query()->where('id', 1)->value('email')
     * @param string $column
     * @return mixed|null
     */
    public function value($column) {
        $row = $this->select([$column])->first();
        if ($row === null) {
            return null;
        }
        $key = $this->resultColumnName($column);
        return $row->$key;
    }

    /**
     * Get a flat array (or key/value map) of a column's values across all
     * matching rows, without hydrating full models.
     * Usage: User::query()->pluck('email'); User::query()->pluck('email', 'id')
     * @param string $column
     * @param string|null $key
     * @return array
     */
    public function pluck($column, $key = null) {
        $this->applySoftDeleteScope();

        $columns = $key !== null ? [$column, $key] : [$column];
        $selects = $this->selects;
        $this->selects = $columns;

        $sql = $this->buildSelectQuery();
        $bindings = $this->getBindings();

        $this->selects = $selects;

        if ($this->debug) {
            error_log('[WPORM][pluck] SQL: ' . $sql);
            error_log('[WPORM][pluck] Bindings: ' . print_r($bindings, true));
        }

        if (!empty($bindings)) {
            $rows = $this->wpdb->get_results($this->wpdb->prepare($sql, ...$bindings), ARRAY_A);
        } else {
            $rows = $this->wpdb->get_results($sql, ARRAY_A);
        }

        if (!$rows) {
            return [];
        }

        // Resolve the actual result column names, since quoteIdentifier()/aliasing
        // may differ from the raw column name passed in (e.g. 'table.col').
        $columnKey = $this->resultColumnName($column);
        $keyKey = $key !== null ? $this->resultColumnName($key) : null;

        $results = [];
        foreach ($rows as $row) {
            $value = $row[$columnKey] ?? null;
            if ($keyKey !== null) {
                $results[$row[$keyKey] ?? null] = $value;
            } else {
                $results[] = $value;
            }
        }
        return $results;
    }

    /**
     * Resolve the array key a given select expression will appear under in
     * a $wpdb ARRAY_A result row (handles "table.column" and "col AS alias").
     * @param string $column
     * @return string
     */
    protected function resultColumnName($column) {
        if (preg_match('/\s+as\s+(.+)$/i', $column, $m)) {
            return trim($m[1], ' `');
        }
        if (strpos($column, '.') !== false) {
            $parts = explode('.', $column);
            return trim(end($parts), ' `');
        }
        return trim($column, ' `');
    }

    /**
     * Determine if any rows match the current query.
     * Usage: User::query()->where('email', $email)->exists()
     * @return bool
     */
    public function exists() {
        $this->applySoftDeleteScope();

        $selects = $this->selects;
        $this->selects = ['1 as exists_flag'];

        // We only need to know whether at least one row matches.
        $this->limit(1);
        $sql = $this->buildSelectQuery();
        $bindings = $this->getBindings();

        $this->selects = $selects;

        if ($this->debug) {
            error_log('[WPORM][exists] SQL: ' . $sql);
            error_log('[WPORM][exists] Bindings: ' . print_r($bindings, true));
        }

        if (!empty($bindings)) {
            $result = $this->wpdb->get_var($this->wpdb->prepare($sql, ...$bindings));
        } else {
            $result = $this->wpdb->get_var($sql);
        }

        return $result !== null;
    }

    /**
     * Determine if no rows match the current query (inverse of exists()).
     * Usage: User::query()->where('email', $email)->doesntExist()
     * @return bool
     */
    public function doesntExist() {
        return !$this->exists();
    }

    // -------------------------------------------------------------------------
    // increment / decrement
    // -------------------------------------------------------------------------

    /**
     * Increment a column's value for all rows matching the current query.
     * Usage: User::query()->where('id', 1)->increment('votes');
     *        User::query()->where('id', 1)->increment('votes', 5);
     *        User::query()->where('id', 1)->increment('votes', 1, ['last_voted_at' => current_time('mysql')]);
     *
     * @param string $column Column to increment
     * @param int|float $amount Amount to increment by (default 1)
     * @param array $extra Additional column => value pairs to set in the same query
     * @return int|false Number of affected rows, or false on failure
     */
    public function increment($column, $amount = 1, array $extra = []) {
        return $this->incrementOrDecrement($column, $amount, $extra, '+');
    }

    /**
     * Decrement a column's value for all rows matching the current query.
     * Usage: User::query()->where('id', 1)->decrement('votes');
     *        User::query()->where('id', 1)->decrement('votes', 5);
     *
     * @param string $column Column to decrement
     * @param int|float $amount Amount to decrement by (default 1)
     * @param array $extra Additional column => value pairs to set in the same query
     * @return int|false Number of affected rows, or false on failure
     */
    public function decrement($column, $amount = 1, array $extra = []) {
        return $this->incrementOrDecrement($column, $amount, $extra, '-');
    }

    /**
     * Shared implementation for increment()/decrement(). Builds a single
     * UPDATE ... SET col = col +/- %d [, extra = %s ...] WHERE ... statement.
     *
     * @param string $column
     * @param int|float $amount
     * @param array $extra
     * @param string $sign '+' or '-'
     * @return int|false
     */
    protected function incrementOrDecrement($column, $amount, array $extra, $sign) {
        $quotedColumn = Helpers::quoteIdentifier($column);
        $set = ["$quotedColumn = $quotedColumn $sign %s"];
        $bindings = [$amount];

        // Auto-touch updated_at if the model supports timestamps, unless the
        // caller already provided a value for it via $extra.
        $updatedAtColumn = $this->model->updatedAtColumn ?? null;
        if (!empty($this->model->timestamps) && $updatedAtColumn && !array_key_exists($updatedAtColumn, $extra)) {
            $extra[$updatedAtColumn] = current_time('mysql');
        }

        foreach ($extra as $col => $val) {
            $set[] = Helpers::quoteIdentifier($col) . ' = %s';
            $bindings[] = $val;
        }

        $sql = 'UPDATE ' . Helpers::quoteIdentifier($this->table) . ' SET ' . implode(', ', $set);
        $where = $this->buildWhereClause();
        if (!empty($where)) {
            $sql .= ' WHERE ' . $where;
        }
        $allBindings = array_merge($bindings, $this->bindings);

        if ($this->debug) {
            error_log('[WPORM][incrementOrDecrement] SQL: ' . $sql);
            error_log('[WPORM][incrementOrDecrement] Bindings: ' . print_r($allBindings, true));
        }

        if (!empty($allBindings)) {
            return $this->wpdb->query($this->wpdb->prepare($sql, ...$allBindings));
        }
        return $this->wpdb->query($sql);
    }
}
