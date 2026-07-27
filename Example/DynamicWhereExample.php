<?php
// Example/DynamicWhereExample.php
// Demonstrates and validates Eloquent-style dynamic where clauses in WPORM.

use MJ\WPORM\Model;

class DynamicWhereUser extends Model {
    protected $table = 'users';
}

global $wpdb;

$query = DynamicWhereUser::whereEmailAndFirstName(
    'dwight@example.com',
    'Dwight'
);

$expectedSql = 'SELECT * FROM `' . $wpdb->prefix . 'users`'
    . ' WHERE `email` = %s AND `first_name` = %s';

if ($query->toSql() !== $expectedSql) {
    throw new RuntimeException('Dynamic AND where generated unexpected SQL.');
}

if ($query->getBindings() !== ['dwight@example.com', 'Dwight']) {
    throw new RuntimeException('Dynamic AND where generated unexpected bindings.');
}

$orQuery = DynamicWhereUser::query()->whereStatusOrRole('active', 'administrator');

$expectedOrSql = 'SELECT * FROM `' . $wpdb->prefix . 'users`'
    . ' WHERE `status` = %s OR `role` = %s';

if ($orQuery->toSql() !== $expectedOrSql) {
    throw new RuntimeException('Dynamic OR where generated unexpected SQL.');
}

if ($orQuery->getBindings() !== ['active', 'administrator']) {
    throw new RuntimeException('Dynamic OR where generated unexpected bindings.');
}

$user = $query->first();
