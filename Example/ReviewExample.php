<?php
// Example/ReviewExample.php
// Demonstrates Review model with relationships and casts

use MJ\WPORM\Model;
use MJ\WPORM\Blueprint;

class Review extends Model {
    protected $table = 'reviews';
    protected $fillable = ['id', 'product_id', 'user_id', 'rating', 'comment'];
    protected $casts = [
        'rating' => 'int',
    ];
    public function up(Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('product_id');
        $table->unsignedBigInteger('user_id');
        $table->integer('rating');
        $table->text('comment');
        $table->timestamps();
        $table->foreign('product_id', 'products');
        $table->foreign('user_id', 'users');
        $this->schema = $table->toSql();
    }
    public function product() {
        return $this->belongsTo(Product::class, 'product_id');
    }
    public function user() {
        return $this->belongsTo(User::class, 'user_id');
    }
}

// Usage
$review = new Review([
    'product_id' => 1,
    'user_id' => 1,
    'rating' => 5,
    'comment' => 'Great product!'
]);
$review->save();
// Relationship usage
$reviewProduct = $review->product(); // belongsTo
$reviewUser = $review->user(); // belongsTo
