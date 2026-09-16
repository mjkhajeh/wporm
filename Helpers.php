<?php
namespace MJ\WPORM;

class Helpers {
    public static function class_basename($class) {
        return basename(str_replace('\\', '/', $class));
    }

    public static function quoteIdentifier($name) {
        if (!is_string($name)) {
            throw new \InvalidArgumentException('Identifier must be a string.');
        }

        if ($name === '*') {
            return $name;
        }

        // Handle "column AS alias" / "column as alias" — quote each side
        // separately and preserve the AS keyword verbatim.
        if (preg_match('/^(.+?)\s+as\s+(.+)$/i', $name, $m)) {
            $expr  = trim($m[1]);
            $alias = trim($m[2]);
            return self::quoteIdentifier($expr) . ' AS ' . self::quoteIdentifier($alias);
        }

        // Function expressions are intentionally supported for select()/orderBy()
        // compatibility; callers should use the corresponding *Raw() method for
        // arbitrary SQL expressions.
        if (preg_match('/^\s*[A-Za-z_][A-Za-z0-9_]*\s*\(/', $name)) {
            return $name;
        }

        // Support dot notation (table.column or table.*)
        if (strpos($name, '.') !== false) {
            return implode('.', array_map(function($part) {
                $part = trim($part);
                if ($part === '*') {
                    return '*';
                }
                return self::quoteIdentifierPart($part);
            }, explode('.', $name)));
        }

        return self::quoteIdentifierPart($name);
    }

    /**
     * Quote one identifier segment. This supports names containing spaces,
     * hyphens, Unicode, and other otherwise-valid identifier characters. Any
     * backticks supplied by the caller are normalized so each segment has one
     * pair of delimiters.
     *
     * @param string $part
     * @return string
     */
    protected static function quoteIdentifierPart(string $part): string {
        $part = trim($part);
        if ($part === '*') {
            return '*';
        }

        if (strpos($part, "\0") !== false) {
            throw new \InvalidArgumentException('Identifier cannot contain NUL bytes.');
        }

        // Normalize caller-supplied quoting, then add exactly one pair.
        $part = str_replace('`', '', $part);
        return '`' . $part . '`';
    }

    /**
     * Validate that a comparison operator is safe to interpolate into SQL.
     *
     * @param string $operator
     * @return string  The original operator (unchanged) if valid
     * @throws \InvalidArgumentException if the operator is not in the allowlist
     */
    public static function validateOperator(string $operator): string {
        static $allowed = [
            '=', '!=', '<>', '<', '>', '<=', '>=', '<=>',
            'LIKE', 'NOT LIKE',
            'RLIKE', 'REGEXP', 'NOT REGEXP',
            'IN', 'NOT IN',
            'BETWEEN', 'NOT BETWEEN',
            'IS', 'IS NOT',
        ];

        $upper = strtoupper(trim($operator));

        if (!in_array($upper, $allowed, true)) {
            throw new \InvalidArgumentException(
                "Invalid SQL operator: {$operator}. Allowed operators: " . implode(', ', $allowed)
            );
        }

        return $operator;
    }

    public static function convert_to_pascal_case( $input ) {
        $input = str_replace( ['-', '_'], ' ', $input );
        $words = explode( ' ', $input ); // Split input string into an array of words
        $capitalizedWords = array_map( 'ucwords', $words ); // Capitalize the first letter of each word
        $pascalCaseString = implode( '', $capitalizedWords ); // Combine the words back into a string
        return str_replace( ' ', '', $pascalCaseString ); // Remove spaces
    }

    /**
     * Escape a plain PHP string for embedding inside a single-quoted MySQL
     * string literal, safely for ANY connection charset.
     *
     * Unlike addslashes() — which blindly escapes individual bytes and can be
     * defeated or corrupted by multibyte encodings such as GBK/BIG5, where a
     * trailing escape backslash can be swallowed into a multibyte character —
     * this uses only encoding-independent escaping rules:
     *
     *   - backslashes are doubled      ( \  -> \\ )
     *   - single quotes are doubled    ( '  -> '' )   pure syntax-level escaping
     *   - NUL bytes use the \0 escape sequence
     *
     * Quote-doubling cannot be abused by charset tricks because it operates at
     * the SQL syntax layer rather than the byte layer.
     *
     * @param string $value Raw value (without surrounding quotes).
     * @return string Escaped content, ready to wrap in single quotes.
     */
    public static function escapeSqlString(string $value): string {
        // Order matters: double existing backslashes first, then double
        // quotes; finally emit NUL as the \0 escape sequence (its backslash
        // must stay single, so it comes last).
        $value = str_replace('\\', '\\\\', $value);
        $value = str_replace("'", "''", $value);
        return str_replace("\0", '\0', $value);
    }

    /**
     * Validate that every row in a batch insert/upsert carries exactly the same
     * column set as the first row.
     *
     * Column order may differ between rows (values are matched by key when the
     * VALUES tuples are built), but the set of keys must be identical — otherwise
     * missing keys would be silently inserted as NULL and extra keys silently
     * dropped.
     *
     * @param array $values Array of rows, each an associative array of column => value
     * @param string $method Caller method name used in error messages
     * @return void
     * @throws \InvalidArgumentException if a row is not an array or has a different column set
     */
    public static function validateConsistentColumns(array $values, string $method = 'upsert'): void {
        $firstKeys = null;
        foreach ($values as $index => $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException(
                    "{$method}(): row at index " . var_export($index, true) . ' is not an associative array of column => value pairs.'
                );
            }
            $keys = array_keys($row);
            sort($keys);
            if ($firstKeys === null) {
                $firstKeys = $keys;
                continue;
            }
            if ($keys !== $firstKeys) {
                throw new \InvalidArgumentException(
                    "{$method}(): row at index " . var_export($index, true) . ' has a different column set than the first row.'
                    . ' Expected: [' . implode(', ', $firstKeys) . '], got: [' . implode(', ', $keys) . '].'
                );
            }
        }
    }
}