<?php
// Example/LazyExample.php
// Demonstrates chunked, lazy iteration over a large query result set.

use MJ\WPORM\WordPress\User;

// Fetch results in pages of 500 and yield each model without materializing
// the complete result set in a Collection.
foreach (User::query()->orderBy('ID')->lazy(500) as $user) {
    // Process one user at a time.
}

// Model's static query proxy also supports lazy().
foreach (User::orderBy('ID')->lazy() as $user) {
    // Process one user at a time using the default page size of 1000.
}
