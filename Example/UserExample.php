<?php
// Example/UserExample.php
// Demonstrates User model features: attributes, casts, soft deletes (timestamp), global scopes, relationships

use MJ\WPORM\Model;
use MJ\WPORM\Blueprint;

class User extends Model {
    protected $table = 'users';
    protected $fillable = ['id', 'name', 'email', 'country', 'age', 'verified', 'subscribed', 'meta'];
    protected $casts = [
        'age' => 'int',
        'verified' => 'bool',
        'subscribed' => 'bool',
        'meta' => 'json',
    ];
    protected $softDeletes = true;
    public function up(Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('email');
        $table->string('country', 2);
        $table->integer('age');
        $table->boolean('verified');
        $table->boolean('subscribed');
        $table->json('meta');
        $table->timestamp('deleted_at')->nullable();
        $table->timestamps();
        $this->schema = $table->toSql();
    }
    // Accessor
    public function getNameAttribute() {
        return strtoupper($this->attributes['name']);
    }
    // Mutator
    public function setNameAttribute($value) {
        $this->attributes['name'] = ucfirst($value);
    }
    // Relationship
    public function posts() {
        return $this->hasMany(Post::class, 'user_id');
    }
    // Global scope example
    protected static function boot() {
        parent::boot();
        static::addGlobalScope('active', function($query) {
            $query->where('verified', true);
        });
    }
}

// Usage
$user = new User([
    'name' => 'alice',
    'email' => 'alice@example.com',
    'country' => 'US',
    'age' => 30,
    'verified' => true,
    'subscribed' => false,
    'meta' => ['newsletter' => true]
]);
$user->save();

// Query with global scope (only verified users)
$activeUsers = User::all();
// Remove global scope for a query
$allUsers = User::query(false)->get();
// Soft delete (timestamp)
$user->delete(); // sets deleted_at
$trashedUsers = User::query()->onlyTrashed()->get();
$user->restore(); // sets deleted_at to null
// Relationship usage
$userPosts = $user->posts(); // hasMany
// Casts usage
$meta = $user->meta; // array
$verified = $user->verified; // bool
