<?php

use MJ\WPORM\Blueprint;
use MJ\WPORM\SchemaBuilder;

global $wpdb;

$schema = new SchemaBuilder($wpdb);

$schema->table('products', function (Blueprint $table) {
    $table->string('name', 150)
        ->nullable()
        ->default('Unnamed product')
        ->change();

    $table->decimal('price', 12, 2)
        ->default(0)
        ->change();
});

return true;
