<?php
// Example/WhenExample.php
// Demonstrates usage of the when() method for conditional queries in WPORM

use MJ\WPORM\Model;

// Example model
class User extends Model {
    protected $table = 'users';
    protected $fillable = ['id', 'name', 'email', 'active', 'country'];
}

// Example 1: Add a where clause only if $isActive is true
$isActive = true;
$users = User::query()
    ->when($isActive, function ($query) {
        $query->where('active', true);
    })
    ->get();

// Example 2: Provide a default callback for the false case
$country = null;
$users = User::query()
    ->when($country, function ($query, $country) {
        $query->where('country', $country);
    }, function ($query) {
        $query->where('country', 'US'); // fallback
    })
    ->get();

// Example 3: Use static Model::when()
$users = User::when($isActive, function ($query) {
    $query->where('active', true);
})->get();
