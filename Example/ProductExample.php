<?php
// Example/ProductExample.php
// Demonstrates Product model with boolean soft delete, casts, and relationships

use MJ\WPORM\Model;
use MJ\WPORM\Blueprint;

class Product extends Model {
    protected $table = 'products';
    protected $fillable = ['id', 'name', 'price', 'deleted'];
    protected $casts = [
        'price' => 'float',
        'deleted' => 'bool',
    ];
    protected $softDeletes = true;
    protected $deletedAtColumn = 'deleted';
    protected $softDeleteType = 'boolean';
    public function up(Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->float('price');
        $table->boolean('deleted')->default(0);
        $table->timestamps();
        $this->schema = $table->toSql();
    }
    // Relationship
    public function reviews() {
        return $this->hasMany(Review::class, 'product_id');
    }
}

// Usage
$product = new Product([
    'name' => 'Widget',
    'price' => 19.99,
]);
$product->save();
// Soft delete (boolean)
$product->delete(); // sets deleted = 1
$trashedProducts = Product::query()->onlyTrashed()->get();
$product->restore(); // sets deleted = 0
// Relationship usage
$productReviews = $product->reviews(); // hasMany
// Casts usage
$price = $product->price; // float
