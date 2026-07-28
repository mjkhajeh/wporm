<?php

use MJ\WPORM\Blueprint;
use MJ\WPORM\SchemaBuilder;

global $wpdb;

$schema = new SchemaBuilder($wpdb);

$schema->table('users', function (Blueprint $table) {
    $table->string('nickname')->nullable()->after('display_name');

    $table->after('nickname', function (Blueprint $table) {
        $table->string('address_line1')->nullable();
        $table->string('address_line2')->nullable();
        $table->string('city')->nullable();
    });
});

return true;
