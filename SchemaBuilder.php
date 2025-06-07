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

    public function create(string $table, \Closure $callback)
    {
        $blueprint = new Blueprint($this->prefix . $table, false, $this->db);
        $callback($blueprint);
        $sql = $blueprint->toSql();
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        \dbDelta($sql);
    }

    public function drop(string $table)
    {
        $this->db->query("DROP TABLE IF EXISTS `{$this->prefix}$table`");
    }

    public function rename(string $from, string $to)
    {
        $this->db->query("RENAME TABLE `{$this->prefix}$from` TO `{$this->prefix}$to`");
    }

    public function table(string $table, \Closure $callback)
    {
        $blueprint = new Blueprint($this->prefix . $table, true, $this->db);
        $callback($blueprint);

        foreach ($blueprint->toAlterSql() as $sql) {
            $this->db->query($sql);
        }
    }
}
