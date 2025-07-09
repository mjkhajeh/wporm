<?php
// Example/WithAndScopesExample.php
// Demonstrates complex eager loading (with) and global scopes

use MJ\WPORM\Model;

// Complex eager loading with nested relations
$productsWithReviewsAndUsers = Product::query()
    ->with(['reviews', 'reviews.user'])
    ->where('price', '>', 10)
    ->get();

// Remove global scope for a query
$allUsers = User::query(false)->get();
