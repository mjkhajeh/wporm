<?php

namespace WPORM\Schema;

class ColumnDefinition
{
    public string $name;
    public string $type;
    public bool $nullable = false;
    public bool $autoIncrement = false;
    public bool $primary = false;
    public $default = null;

    public function __construct(string $name, string $type)
    {
        $this->name = $name;
        $this->type = $type;
    }

    public function nullable(bool $value = true)
    {
        $this->nullable = $value;
        return $this;
    }

    public function default($value)
    {
        $this->default = $value;
        return $this;
    }

    public function autoIncrement(bool $value = true)
    {
        $this->autoIncrement = $value;
        return $this;
    }

    public function primary(bool $value = true)
    {
        $this->primary = $value;
        return $this;
    }

    public function toSql(): string
    {
        $sql = "{$this->name} {$this->type}";

        if ($this->autoIncrement) $sql .= " AUTO_INCREMENT";
        if (!$this->nullable) $sql .= " NOT NULL";
        if ($this->nullable) $sql .= " NULL";

        if ($this->default !== null) {
            $val = is_string($this->default) ? "'{$this->default}'" : $this->default;
            $sql .= " DEFAULT $val";
        }

        return $sql;
    }
}
