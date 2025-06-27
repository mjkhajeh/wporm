<?php

namespace MJ\WPORM;

use wpdb;

class Blueprint
{
    public string $table;
    protected bool $alter;
    protected wpdb $db;

    protected array $columns = [];
    protected array $commands = [];
    protected array $keys = [];
    protected array $foreigns = [];
    protected array $primaryKeys = [];

    public function __construct(string $table, bool $alter, wpdb $db)
    {
        $this->table = $table;
        $this->alter = $alter;
        $this->db = $db;
    }

    public function __call($method, $args)
    {
        // For fluent API like $table->string('name')->nullable()
        if (method_exists(ColumnDefinition::class, $method)) {
            $column = end($this->columns);
            $column->$method(...$args);
        }
    }

    public function addColumn(string $type, string $name): ColumnDefinition
    {
        $col = new ColumnDefinition($name, $type);
        $col->setBlueprint($this); // Inject reference to Blueprint
        $this->columns[] = $col;
        return $col;
    }

    public function string(string $column, int $length = 255) { return $this->addColumn("VARCHAR($length)", $column); }
    public function text(string $column) { return $this->addColumn("TEXT", $column); }
    public function integer(string $column) { return $this->addColumn("INT", $column); }
    public function bigInteger(string $column) { return $this->addColumn("BIGINT", $column); }
    public function boolean(string $column) { return $this->addColumn("TINYINT(1)", $column); }
    public function timestamp(string $column) { return $this->addColumn("TIMESTAMP", $column); }
    public function increments(string $column) {
        $col = $this->addColumn("INT UNSIGNED", $column)->autoIncrement();
        $this->primary($column);
        return $col;
    }

    public function longText(string $column) { return $this->addColumn("LONGTEXT", $column); }
    public function mediumText(string $column) { return $this->addColumn("MEDIUMTEXT", $column); }
    public function smallInteger(string $column) { return $this->addColumn("SMALLINT", $column); }

    public function tinyInteger(string $column) { return $this->addColumn("TINYINT", $column); }
    public function unsignedInteger(string $column) { return $this->addColumn("INT UNSIGNED", $column); }
    public function unsignedBigInteger(string $column) { return $this->addColumn("BIGINT UNSIGNED", $column); }
    public function unsignedSmallInteger(string $column) { return $this->addColumn("SMALLINT UNSIGNED", $column); }
    public function unsignedTinyInteger(string $column) { return $this->addColumn("TINYINT UNSIGNED", $column); }
    public function unsignedMediumInteger(string $column) { return $this->addColumn("MEDIUMINT UNSIGNED", $column); }
    public function mediumInteger(string $column) { return $this->addColumn("MEDIUMINT", $column); }
    public function tinyText(string $column) { return $this->addColumn("TINYTEXT", $column); }
    public function binaryText(string $column) { return $this->addColumn("BINARY", $column); }
    public function mediumBinaryText(string $column) { return $this->addColumn("MEDIUMBLOB", $column); }
    public function longBinaryText(string $column) { return $this->addColumn("LONGBLOB", $column); }
    public function smallIncrements(string $column) {
        $col = $this->addColumn("SMALLINT UNSIGNED", $column)->autoIncrement()->primary();
        return $col;
    }
    public function tinyIncrements(string $column) {
        $col = $this->addColumn("TINYINT UNSIGNED", $column)->autoIncrement()->primary();
        return $col;
    }
    public function unsignedMediumIncrements(string $column) {
        $col = $this->addColumn("MEDIUMINT UNSIGNED", $column)->autoIncrement()->primary();
        return $col;
    }
    public function unsignedBigIncrements(string $column) {
        $col = $this->addColumn("BIGINT UNSIGNED", $column)->autoIncrement()->primary();
        return $col;
    }
    public function unsignedSmallIncrements(string $column) {
        $col = $this->addColumn("SMALLINT UNSIGNED", $column)->autoIncrement()->primary();
        return $col;
    }
    public function unsignedTinyIncrements(string $column) {
        $col = $this->addColumn("TINYINT UNSIGNED", $column)->autoIncrement()->primary();
        return $col;
    }
    public function unsignedIntegerIncrements(string $column) {
        $col = $this->addColumn("INT UNSIGNED", $column)->autoIncrement()->primary();
        return $col;
    }
    public function unsignedIntegerBigIncrements(string $column) {
        $col = $this->addColumn("BIGINT UNSIGNED", $column)->autoIncrement()->primary();
        return $col;
    }
    public function unsignedIntegerMediumIncrements(string $column) {
        $col = $this->addColumn("MEDIUMINT UNSIGNED", $column)->autoIncrement()->primary();
        return $col;
    }
    public function mediumIncrements(string $column) {
        $col = $this->addColumn("MEDIUMINT UNSIGNED", $column)->autoIncrement()->primary();
        return $col;
    }


    public function bigIncrements(string $column) {
        $col = $this->addColumn("BIGINT UNSIGNED", $column)->autoIncrement()->primary();
        return $col;
    }

    public function float(string $column, int $precision = 8, int $scale = 2) {
        return $this->addColumn("FLOAT($precision, $scale)", $column);
    }

    public function double(string $column, int $precision = 16, int $scale = 8) {
        return $this->addColumn("DOUBLE($precision, $scale)", $column);
    }

    public function decimal(string $column, int $precision = 16, int $scale = 2) {
        return $this->addColumn("DECIMAL($precision, $scale)", $column);
    }
    public function date(string $column) { return $this->addColumn("DATE", $column); }

    public function time(string $column) { return $this->addColumn("TIME", $column); }

    public function datetime(string $column) { return $this->addColumn("DATETIME", $column); }
    public function datetimeTz(string $column) { return $this->addColumn("DATETIME WITH TIME ZONE", $column); }
    public function timestampTz(string $column) { return $this->addColumn("TIMESTAMP WITH TIME ZONE", $column); }
    public function timestampTzWithDefault(string $column) { return $this->addColumn("TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP", $column); }
    public function timestampWithDefault(string $column) { return $this->addColumn("TIMESTAMP DEFAULT CURRENT_TIMESTAMP", $column); }
    public function dateTimeWithDefault(string $column) { return $this->addColumn("DATETIME DEFAULT CURRENT_TIMESTAMP", $column); }
    public function dateTimeTzWithDefault(string $column) { return $this->addColumn("DATETIME WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP", $column); }
    public function dateTimeTzWithDefaultCurrentOnUpdate(string $column) { return $this->addColumn("DATETIME WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP", $column); }
    public function dateTimeWithDefaultCurrentOnUpdate(string $column) { return $this->addColumn("DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP", $column); }
    public function timeTz(string $column) { return $this->addColumn("TIME WITH TIME ZONE", $column); }
    public function timeTzWithDefault(string $column) { return $this->addColumn("TIME WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP", $column); }
    public function timeTzWithDefaultCurrentOnUpdate(string $column) { return $this->addColumn("TIME WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP", $column); }
    public function timeWithDefault(string $column) { return $this->addColumn("TIME DEFAULT CURRENT_TIMESTAMP", $column); }

    public function binary(string $column) { return $this->addColumn("BLOB", $column); }
    public function json(string $column) { return $this->addColumn("JSON", $column); }
    public function jsonb(string $column) { return $this->addColumn("JSONB", $column); }
    public function char(string $column, int $length = 1) { return $this->addColumn("CHAR($length)", $column); }
    public function enum(string $column, array $values) {
        $values = implode("', '", $values);
        return $this->addColumn("ENUM('$values')", $column);
    }
    public function set(string $column, array $values) {
        $values = implode("', '", $values);
        return $this->addColumn("SET('$values')", $column);
    }
    public function year(string $column) { return $this->addColumn("YEAR", $column); }
    public function ipAddress(string $column) { return $this->addColumn("VARCHAR(45)", $column); }
    public function macAddress(string $column) { return $this->addColumn("VARCHAR(17)", $column); }
    public function uuid(string $column) { return $this->addColumn("CHAR(36)", $column); }
    public function uuidBinary(string $column) { return $this->addColumn("BINARY(16)", $column); }
    public function binaryUuid(string $column) { return $this->addColumn("BINARY(16)", $column); }
    public function ulid(string $column) { return $this->addColumn("CHAR(26)", $column); }
    public function point(string $column) { return $this->addColumn("POINT", $column); }
    public function polygon(string $column) { return $this->addColumn("POLYGON", $column); }
    public function id(string $column = 'id') {
        return $this->increments($column);
    }
    public function geography(string $column) { return $this->addColumn("GEOGRAPHY", $column); }

    // foreignId
    public function foreignId(string $column, string $refTable, string $refColumn = 'id', string $onDelete = 'CASCADE', string $onUpdate = 'CASCADE')
    {
        $this->addColumn("BIGINT UNSIGNED", $column)->nullable();
        $this->foreign($column, $refTable, $refColumn, $onDelete, $onUpdate);
        return $this;
    }

    public function foreignIdFor(string $column, string $refTable, string $refColumn = 'id', string $onDelete = 'CASCADE', string $onUpdate = 'CASCADE')
    {
        return $this->foreignId($column, $refTable, $refColumn, $onDelete, $onUpdate);
    }
    public function foreignUlid(string $column, string $refTable, string $refColumn = 'id', string $onDelete = 'CASCADE', string $onUpdate = 'CASCADE')
    {
        $this->addColumn("CHAR(26)", $column)->nullable();
        $this->foreign($column, $refTable, $refColumn, $onDelete, $onUpdate);
        return $this;
    }
    public function foreignUuid(string $column, string $refTable, string $refColumn = 'id', string $onDelete = 'CASCADE', string $onUpdate = 'CASCADE')
    {
        $this->addColumn("CHAR(36)", $column)->nullable();
        $this->foreign($column, $refTable, $refColumn, $onDelete, $onUpdate);
        return $this;
    }

    public function nullable(): self
    {
        $column = end($this->columns);
        $column->nullable();
        return $this;
    }

    public function timestamps() {
        $this->timestamp('created_at');
        $this->timestamp('updated_at');
    }
    /**
     * Add a nullable datetime column for soft deletes (Eloquent-style shortcut).
     * @param string $column
     * @return ColumnDefinition
     */
    public function softDeletes(string $column = 'deleted_at') {
        return $this->datetime($column)->nullable();
    }

    public function dropColumn(string $column)
    {
        $this->commands[] = "DROP COLUMN $column";
    }

    public function renameColumn(string $from, string $to)
    {
        // Optional: Try to guess type via DESCRIBE
        $columns = $this->db->get_results("DESCRIBE {$this->table}");
        $type = 'TEXT';

        foreach ($columns as $col) {
            if ($col->Field === $from) {
                $type = $col->Type;
                break;
            }
        }

        $this->commands[] = "CHANGE $from $to $type";
    }

    /**
     * Add a unique index for a column (Eloquent-style).
     *
     * Usage:
     *   $table->string('email')->unique();
     *   $table->integer('user_id')->unique('custom_index_name');
     *
     * You can also use the Blueprint method for multi-column unique:
     *   $table->unique(['col1', 'col2']);
     */
    public function unique($columns, $name = null)
    {
        $cols = $this->wrapArray($columns);
        $this->keys[] = "UNIQUE KEY " . ($name ?? "unique_" . md5($cols)) . " ($cols)";
    }

    public function foreign(string $column, string $refTable, string $refColumn = 'id', string $onDelete = 'CASCADE', string $onUpdate = 'CASCADE')
    {
        $this->foreigns[] = "FOREIGN KEY ($column) REFERENCES $refTable($refColumn) ON DELETE $onDelete ON UPDATE $onUpdate";
    }

    protected function wrapArray($columns)
    {
        return implode(", ", array_map(fn($c) => "$c", (array) $columns));
    }

    public function primary($columns)
    {
        $cols = $this->wrapArray($columns);
        $this->primaryKeys[] = $cols;
        return $this;
    }

    public function toSql(): string
    {
        $definitions = array_map(fn($c) => $c->toSql(), $this->columns);

        $constraints = [];
        if (!empty($this->primaryKeys)) {
            foreach ($this->primaryKeys as $pk) {
                $constraints[] = "PRIMARY KEY  ($pk)";
            }
        }

        $allDefs = array_merge($definitions, $constraints, $this->keys, $this->foreigns);
        $schema = implode("," . PHP_EOL, $allDefs);

        return $schema;
    }

    public function toAlterSql(): array
    {
        $sql = [];

        foreach ($this->columns as $col) {
            $sql[] = "ALTER TABLE {$this->table} ADD " . $col->toSql();
        }

        foreach ($this->keys as $key) {
            $sql[] = "ALTER TABLE {$this->table} ADD $key";
        }

        foreach ($this->foreigns as $fk) {
            $sql[] = "ALTER TABLE {$this->table} ADD $fk";
        }

        foreach ($this->commands as $cmd) {
            $sql[] = "ALTER TABLE {$this->table} $cmd";
        }

        return $sql;
    }
}
