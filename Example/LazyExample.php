<?php
// Example/LazyExample.php
// Demonstrates chunked, lazy iteration over a large query result set.

use MJ\WPORM\WordPress\User;

// Fetch results in pages of 500 and yield each model without materializing
// the complete result set in a Collection.
foreach (User::query()->orderBy('ID')->lazy(500) as $user) {
    // Process one user at a time.
}

// Transform lazily and stop after the first 100 users matching the predicate.
$userIds = User::orderBy('ID')->lazy(500)
    ->filter(function ($user) {
        return $user->user_status == 0;
    })
    ->map(function ($user) {
        return $user->ID;
    })
    ->take(100)
    ->all();

// Model's static query proxy also supports lazy().
foreach (User::orderBy('ID')->lazy() as $user) {
    // Process one user at a time using the default page size of 1000.
}

// cursor() also returns a LazyCollection and supports transformations,
// but executes one query and does not eager load relationships.
$recentUserIds = User::orderBy('ID')->cursor()
    ->map(function ($user) {
        return $user->ID;
    })
    ->take(100)
    ->all();
