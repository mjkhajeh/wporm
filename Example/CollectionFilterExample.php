<?php
// Example/CollectionFilterExample.php
// Demonstrates Collection::filter() with and without a callback.

use MJ\WPORM\Collection;

$values = new Collection([
    0,
    1,
    false,
    'WPORM',
    '',
    null,
    [],
    ['id' => 1],
]);

$truthy = $values->filter();
$expectedTruthy = [
    1 => 1,
    3 => 'WPORM',
    7 => ['id' => 1],
];

if ($truthy->all() !== $expectedTruthy) {
    throw new RuntimeException('filter() did not remove falsy values as expected.');
}

$strings = $values->filter(function ($value) {
    return is_string($value) && $value !== '';
});

if ($strings->all() !== [3 => 'WPORM']) {
    throw new RuntimeException('filter(callback) did not apply the callback as expected.');
}
