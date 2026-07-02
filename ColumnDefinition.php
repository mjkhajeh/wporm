<?php

namespace MJ\WPORM;

class ColumnDefinition
{
    public string $name;
    public string $type;
    public bool $nullable = false;
    public bool $autoIncrement = false;
    public bool $primary = false;
    public $default = null;
    protected $blueprint;
    protected string $rawName;

    public function __construct(string $name, string $type)
    {
        $this->rawName = $name;
        $this->name = Helpers::quoteIdentifier( $name );
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

    public function setBlueprint($blueprint)
    {
        $this->blueprint = $blueprint;
        return $this;
    }

    /**
     * Add a unique index for this column (Eloquent-style).
     * Usage: $table->string('email')->unique();
     * @param string|null $name
     * @return $this
     */
    public function unique($name = null)
    {
        if ($this->blueprint) {
            $this->blueprint->unique($this->rawName, $name);
        }
        return $this;
    }

    /**
     * Raw SQL default expressions/keywords that must NOT be quoted as string
     * literals when rendered in DEFAULT (...). Matched case-insensitively,
     * with an optional parenthesized argument (e.g. CURRENT_TIMESTAMP(3)).
     *
     * @var string[]
     */
    protected static $rawDefaultKeywords = [
        'CURRENT_TIMESTAMP',
        'NULL',
        'TRUE',
        'FALSE',
        'NOW',
        'UUID',
    ];

    /**
     * Determine whether a given default value should be emitted as a raw,
     * unquoted SQL expression (e.g. CURRENT_TIMESTAMP, CURRENT_TIMESTAMP(3),
     * NOW()) rather than a quoted string literal.
     *
     * @param mixed $value
     * @return bool
     */
    protected static function isRawDefaultExpression($value): bool
    {
        if (!is_string($value)) {
            return false;
        }

        $trimmed = trim($value);

        foreach (static::$rawDefaultKeywords as $keyword) {
            // Matches the bare keyword, or the keyword followed by an
            // optional parenthesized argument list, e.g.:
            //   CURRENT_TIMESTAMP
            //   CURRENT_TIMESTAMP(3)
            //   current_timestamp ON UPDATE CURRENT_TIMESTAMP
            if (preg_match('/^' . preg_quote($keyword, '/') . '(\s*\(\d+(?:\s*,\s*\d+)?\))?$/i', $trimmed)) {
                return true;
            }
        }

        // Also treat "<KEYWORD> ON UPDATE <KEYWORD>(...)" style compound
        // defaults as raw, since they're SQL expressions, not literals.
        if (preg_match('/^(CURRENT_TIMESTAMP|NOW)(\s*\(\d+(?:\s*,\s*\d+)?\))?\s+ON\s+UPDATE\s+(CURRENT_TIMESTAMP|NOW)(\s*\(\d+(?:\s*,\s*\d+)?\))?$/i', $trimmed)) {
            return true;
        }

        return false;
    }

    public function toSql(): string
    {
        $sql = "{$this->name} {$this->type}";

        if ($this->autoIncrement) $sql .= " AUTO_INCREMENT";
        if (!$this->nullable) $sql .= " NOT NULL";
        if ($this->nullable) $sql .= " NULL";

        if ($this->default !== null) {
            if (is_bool($this->default)) {
                // Booleans map to 1/0, never quoted.
                $val = $this->default ? '1' : '0';
            } elseif (is_int($this->default) || is_float($this->default)) {
                $val = $this->default;
            } elseif (static::isRawDefaultExpression($this->default)) {
                // Raw SQL keyword/expression (CURRENT_TIMESTAMP, NOW(), etc.)
                // — emit as-is, uppercased for readability, never quoted.
                $val = strtoupper(trim($this->default));
            } else {
                // Plain string literal — quote it (and escape embedded quotes).
                $val = "'" . addslashes($this->default) . "'";
            }
            $sql .= " DEFAULT $val";
        }

        return $sql;
    }
}
