<?php

namespace MJ\WPORM;

use wpdb;

class SchemaBuilder
{
    protected wpdb $db;
    protected string $prefix;

    public function __construct(wpdb $db)
    {
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
        $this->db->query("DROP TABLE IF EXISTS `{$this->prefix}$table`");
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
            $this->db->query($sql);
        }
    }
}
