<?php
namespace MJ\WPORM;

function class_basename($class) {
    return basename(str_replace('\\', '/', $class));
}

function quoteIdentifier($name) {
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
