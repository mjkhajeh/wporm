<?php
namespace MJ\WPORM;

class Helpers {
    public static function class_basename($class) {
        return basename(str_replace('\\', '/', $class));
    }

    public static function quoteIdentifier($name) {
        // If already quoted or is a function call, return as is
        if ($name === '*' || strpos($name, '`') !== false || preg_match('/\w+\s*\(/', $name)) {
            return $name;
        }
        // Support dot notation (table.column)
        if (strpos($name, '.') !== false) {
            return implode('.', array_map(function($part) {
                return '`' . str_replace('`', '', $part) . '`';
            }, explode('.', $name)));
        }
        return '`' . str_replace('`', '', $name) . '`';
    }

    public static function convert_to_pascal_case( $input ) {
        $input = str_replace( ['-', '_'], ' ', $input );
        $words = explode( ' ', $input ); // Split input string into an array of words
        $capitalizedWords = array_map( 'ucwords', $words ); // Capitalize the first letter of each word
        $pascalCaseString = implode( '', $capitalizedWords ); // Combine the words back into a string
        return str_replace( ' ', '', $pascalCaseString ); // Remove spaces
    }
}