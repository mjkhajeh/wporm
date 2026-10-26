<?php

namespace MJ\WPORM;

use wpdb;

class SchemaBuilder
{
    protected wpdb $db;
    protected string $prefix;

    public function __construct(wpdb $db = null)
    {
        if( $db === null ) {
            global $wpdb;
            $db = $wpdb;
        }
        $this->db = $db;
        $this->prefix = $db->prefix;
    }

    /**
     * Ensure the table name is bare (un-prefixed) before prefixing.
     * Strips $wpdb->prefix if already present to prevent double-prefixing.
     */
    protected function bareTable(string $table): string
    {
        if ($this->prefix !== '' && strpos($table, $this->prefix) === 0) {
            return substr($table, strlen($this->prefix));
        }
        return $table;
    }

    /**
     * Run a metadata query and surface database errors.
     *
     * @return array
     */
    protected function getSchemaResults(string $sql): array
    {
        $results = $this->db->get_results($sql, ARRAY_A);

        if (!empty($this->db->last_error)) {
            throw new \RuntimeException('Failed to inspect database schema: ' . $this->db->last_error);
        }

        if (!is_array($results)) {
            throw new \RuntimeException('Failed to inspect database schema.');
        }

        return $results;
    }

    /**
     * Determine whether a table exists.
     */
    public function hasTable(string $table): bool
    {
        $fullTable = $this->prefix . $this->bareTable($table);
        $sql = $this->db->prepare(
            'SHOW FULL TABLES LIKE %s',
            $this->db->esc_like($fullTable)
        );
        $results = $this->db->get_results($sql, ARRAY_N);

        if (!empty($this->db->last_error)) {
            throw new \RuntimeException('Failed to inspect database schema: ' . $this->db->last_error);
        }

        if (!is_array($results)) {
            throw new \RuntimeException('Failed to inspect database schema.');
        }

        foreach ($results as $row) {
            if (
                isset($row[0], $row[1])
                && strcasecmp((string) $row[0], $fullTable) === 0
                && strcasecmp((string) $row[1], 'BASE TABLE') === 0
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine whether a view exists.
     */
    public function hasView(string $view): bool
    {
        $fullTable = $this->prefix . $this->bareTable($view);
        $sql = $this->db->prepare(
            'SHOW FULL TABLES LIKE %s',
            $this->db->esc_like($fullTable)
        );
        $results = $this->db->get_results($sql, ARRAY_N);

        if (!empty($this->db->last_error)) {
            throw new \RuntimeException('Failed to inspect database schema: ' . $this->db->last_error);
        }

        if (!is_array($results)) {
            throw new \RuntimeException('Failed to inspect database schema.');
        }

        foreach ($results as $row) {
            if (
                isset($row[0], $row[1])
                && strcasecmp((string) $row[0], $fullTable) === 0
                && strcasecmp((string) $row[1], 'VIEW') === 0
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine whether a table has a given column.
     */
    public function hasColumn(string $table, string $column): bool
    {
        $columns = array_map('strtolower', $this->getColumnListing($table));
        return in_array(strtolower($column), $columns, true);
    }

    /**
     * Determine whether a table has all of the given columns.
     */
    public function hasColumns(string $table, array $columns): bool
    {
        $tableColumns = array_map('strtolower', $this->getColumnListing($table));

        foreach ($columns as $column) {
            if (!in_array(strtolower($column), $tableColumns, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Execute a table callback if the table has the given column.
     */
    public function whenTableHasColumn(string $table, string $column, \Closure $callback): void
    {
        if ($this->hasColumn($table, $column)) {
            $this->table($table, $callback);
        }
    }

    /**
     * Execute a table callback if the table does not have the given column.
     */
    public function whenTableDoesntHaveColumn(string $table, string $column, \Closure $callback): void
    {
        if (!$this->hasColumn($table, $column)) {
            $this->table($table, $callback);
        }
    }

    /**
     * Execute a table callback if the table has the given index.
     *
     * @param string|array $index
     */
    public function whenTableHasIndex(string $table, $index, \Closure $callback, ?string $type = null): void
    {
        if ($this->hasIndex($table, $index, $type)) {
            $this->table($table, $callback);
        }
    }

    /**
     * Execute a table callback if the table does not have the given index.
     *
     * @param string|array $index
     */
    public function whenTableDoesntHaveIndex(string $table, $index, \Closure $callback, ?string $type = null): void
    {
        if (!$this->hasIndex($table, $index, $type)) {
            $this->table($table, $callback);
        }
    }

    /**
     * Get the data type for a column.
     *
     * @throws \InvalidArgumentException If the column does not exist.
     */
    public function getColumnType(string $table, string $column, bool $fullDefinition = false): string
    {
        foreach ($this->getColumns($table) as $definition) {
            if (strcasecmp($definition['name'], $column) === 0) {
                return $fullDefinition ? $definition['type'] : $definition['type_name'];
            }
        }

        throw new \InvalidArgumentException("There is no column with name '{$column}' on table '{$table}'.");
    }

    /**
     * Get the column names for a table.
     *
     * @return array
     */
    public function getColumnListing(string $table): array
    {
        return array_column($this->getColumns($table), 'name');
    }

    /**
     * Get the column definitions for a table.
     *
     * @return array
     */
    public function getColumns(string $table): array
    {
        $fullTable = $this->prefix . $this->bareTable($table);
        $sql = $this->db->prepare(
            'SELECT COLUMN_NAME AS name, DATA_TYPE AS type_name, COLUMN_TYPE AS type, '
            . 'COLLATION_NAME AS collation, IS_NULLABLE AS nullable, '
            . 'COLUMN_DEFAULT AS `default`, COLUMN_COMMENT AS comment, '
            . 'GENERATION_EXPRESSION AS expression, EXTRA AS extra '
            . 'FROM information_schema.COLUMNS '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s '
            . 'ORDER BY ORDINAL_POSITION',
            $fullTable
        );
        $rows = $this->getSchemaResults($sql);
        $columns = [];

        foreach ($rows as $row) {
            $type = strtolower((string) $row['type']);
            $extra = (string) $row['extra'];
            $expression = (string) $row['expression'];
            $generation = $expression !== '' ? [
                'type' => stripos($extra, 'STORED GENERATED') !== false ? 'stored' : 'virtual',
                'expression' => $expression,
            ] : null;

            $columns[] = [
                'name' => $row['name'],
                'type' => $type,
                'type_name' => strtolower((string) $row['type_name']),
                'collation' => $row['collation'],
                'nullable' => strtoupper((string) $row['nullable']) === 'YES',
                'default' => $row['default'],
                'auto_increment' => stripos($extra, 'auto_increment') !== false,
                'comment' => $row['comment'] !== '' ? $row['comment'] : null,
                'generation' => $generation,
            ];
        }

        return $columns;
    }

    /**
     * Get the indexes for a table.
     *
     * @return array
     */
    public function getIndexes(string $table): array
    {
        $fullTable = $this->prefix . $this->bareTable($table);
        $sql = $this->db->prepare(
            'SELECT INDEX_NAME AS name, '
            . 'GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS columns, '
            . 'INDEX_TYPE AS type, NOT NON_UNIQUE AS unique_index '
            . 'FROM information_schema.STATISTICS '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s '
            . 'GROUP BY INDEX_NAME, INDEX_TYPE, NON_UNIQUE',
            $fullTable
        );
        $rows = $this->getSchemaResults($sql);
        $indexes = [];

        foreach ($rows as $row) {
            $name = strtolower($row['name']);
            $indexes[] = [
                'name' => $name,
                'columns' => $row['columns'] ? explode(',', $row['columns']) : [],
                'type' => strtolower($row['type']),
                'unique' => (bool) $row['unique_index'],
                'primary' => $name === 'primary',
            ];
        }

        return $indexes;
    }

    /**
     * Get the index names for a table.
     *
     * @return array
     */
    public function getIndexListing(string $table): array
    {
        return array_column($this->getIndexes($table), 'name');
    }

    /**
     * Determine whether a table has a given index.
     *
     * @param string|array $index
     */
    public function hasIndex(string $table, $index, ?string $type = null): bool
    {
        $type = $type === null ? null : strtolower($type);
        $indexName = is_string($index) ? strtolower($index) : null;

        foreach ($this->getIndexes($table) as $definition) {
            $typeMatches = $type === null
                || ($type === 'primary' && $definition['primary'])
                || ($type === 'unique' && $definition['unique'])
                || $type === $definition['type'];
            $indexMatches = $indexName !== null
                ? strtolower($definition['name']) === $indexName
                : $definition['columns'] === $index;

            if ($indexMatches && $typeMatches) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the foreign keys for a table.
     *
     * @return array
     */
    public function getForeignKeys(string $table): array
    {
        $fullTable = $this->prefix . $this->bareTable($table);
        $sql = $this->db->prepare(
            'SELECT kc.CONSTRAINT_NAME AS name, '
            . 'GROUP_CONCAT(kc.COLUMN_NAME ORDER BY kc.ORDINAL_POSITION) AS columns, '
            . 'kc.REFERENCED_TABLE_SCHEMA AS foreign_schema, '
            . 'kc.REFERENCED_TABLE_NAME AS foreign_table, '
            . 'GROUP_CONCAT(kc.REFERENCED_COLUMN_NAME ORDER BY kc.ORDINAL_POSITION) AS foreign_columns, '
            . 'rc.UPDATE_RULE AS on_update, rc.DELETE_RULE AS on_delete '
            . 'FROM information_schema.KEY_COLUMN_USAGE AS kc '
            . 'JOIN information_schema.REFERENTIAL_CONSTRAINTS AS rc '
            . 'ON kc.CONSTRAINT_SCHEMA = rc.CONSTRAINT_SCHEMA '
            . 'AND kc.CONSTRAINT_NAME = rc.CONSTRAINT_NAME '
            . 'WHERE kc.TABLE_SCHEMA = DATABASE() AND kc.TABLE_NAME = %s '
            . 'AND kc.REFERENCED_TABLE_NAME IS NOT NULL '
            . 'AND rc.CONSTRAINT_SCHEMA = DATABASE() AND rc.TABLE_NAME = %s '
            . 'GROUP BY kc.CONSTRAINT_NAME, kc.REFERENCED_TABLE_SCHEMA, '
            . 'kc.REFERENCED_TABLE_NAME, rc.UPDATE_RULE, rc.DELETE_RULE',
            $fullTable,
            $fullTable
        );
        $rows = $this->getSchemaResults($sql);
        $foreignKeys = [];

        foreach ($rows as $row) {
            $foreignKeys[] = [
                'name' => $row['name'],
                'columns' => explode(',', $row['columns']),
                'foreign_schema' => $row['foreign_schema'],
                'foreign_table' => $row['foreign_table'],
                'foreign_columns' => explode(',', $row['foreign_columns']),
                'on_update' => strtolower($row['on_update']),
                'on_delete' => strtolower($row['on_delete']),
            ];
        }

        return $foreignKeys;
    }

    public function create(string $table, \Closure $callback)
    {
        $table = $this->bareTable($table);
        $blueprint = new Blueprint($this->prefix . $table, false, $this->db);
        $callback($blueprint);
        $columnSql = $blueprint->toSql();

        if (empty($columnSql)) {
            return;
        }

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        $charsetCollate = $this->db->get_charset_collate();
        $fullTable      = $this->prefix . $table;

        $sql = "CREATE TABLE {$fullTable} (
{$columnSql}
) {$charsetCollate};";

        \dbDelta($sql);

        if (!empty($this->db->last_error)) {
            throw new \RuntimeException(
                "Failed to create table {$fullTable}: " . $this->db->last_error
            );
        }
    }

    public function drop(string $table)
    {
        $table = $this->bareTable($table);
        $fullTable = Helpers::quoteIdentifier($this->prefix . $table);
        $this->db->query("DROP TABLE IF EXISTS {$fullTable}");
    }

    /**
     * Drop a table if it exists.
     */
    public function dropIfExists(string $table): void
    {
        $this->drop($table);
    }

    /**
     * Drop one or more columns from a table.
     *
     * @param string|array $columns
     */
    public function dropColumns(string $table, $columns): void
    {
        $this->table($table, function (Blueprint $blueprint) use ($columns): void {
            foreach ((array) $columns as $column) {
                $blueprint->dropColumn($column);
            }
        });
    }

    public function rename(string $from, string $to)
    {
        $from = $this->bareTable($from);
        $to   = $this->bareTable($to);
        $this->db->query("RENAME TABLE `{$this->prefix}$from` TO `{$this->prefix}$to`");
    }

    public function table(string $table, \Closure $callback)
    {
        $table = $this->bareTable($table);
        $blueprint = new Blueprint($this->prefix . $table, true, $this->db);
        $callback($blueprint);

        foreach ($blueprint->toAlterSql() as $sql) {
            $result = $this->db->query($sql);

            if ($result === false || !empty($this->db->last_error)) {
                $error = !empty($this->db->last_error)
                    ? $this->db->last_error
                    : 'Unknown database error.';

                throw new \RuntimeException(
                    "Failed to alter table {$this->prefix}{$table}: {$error}"
                );
            }
        }
    }
}
