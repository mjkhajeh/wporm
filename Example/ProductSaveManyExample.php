<?php
// Example/ProductSaveManyExample.php
// Demonstrates usage of saveMany() with QueryBuilder

require_once __DIR__ . '/Product.php';

use MJ\WPORM\Product;

// Create multiple Product model instances
$products = [
    new Product(['name' => 'Widget', 'price' => 19.99]),
    new Product(['name' => 'Gadget', 'price' => 29.99]),
    new Product(['name' => 'Thingamajig', 'price' => 9.99]),
];

// Save all products in a transaction using saveMany()
$saved = Product::query()->saveMany($products);

foreach ($saved as $product) {
    echo "Saved product: {$product->name} (ID: {$product->id})\n";
}
