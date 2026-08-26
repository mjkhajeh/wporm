<?php
namespace MJ\WPORM;

class Helpers {
    public static function class_basename($class) {
        return basename(str_replace('\\', '/', $class));
    }

    public static function quoteIdentifier($name) {
        // Fast path: plain column name (id, name, created_at, …) — skip all regex.
        // Alphanumeric + underscores only covers the vast majority of column names.
        if ($name !== '' && $name[0] !== '`' && $name !== '*'
            && ctype_alnum(str_replace('_', '', $name))
        ) {
            return '`' . $name . '`';
        }

        // If already quoted or is a function call, return as is
        if ($name === '*' || strpos($name, '`') !== false || preg_match('/\w+\s*\(/', $name)) {
            return $name;
        }

        // Handle "column AS alias" / "column as alias" — quote each side
        // separately and preserve the AS keyword verbatim.
        if (preg_match('/^(.+?)\s+as\s+(.+)$/i', $name, $m)) {
            $expr  = trim($m[1]);
            $alias = trim($m[2]);
            return self::quoteIdentifier($expr) . ' AS ' . self::quoteIdentifier($alias);
        }

        // Support dot notation (table.column or table.*)
        if (strpos($name, '.') !== false) {
            return implode('.', array_map(function($part) {
                $part = trim($part);
                if ($part === '*') {
                    return '*';
                }
                return '`' . str_replace('`', '', $part) . '`';
            }, explode('.', $name)));
        }
        return '`' . str_replace('`', '', $name) . '`';
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