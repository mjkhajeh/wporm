<?php
// Example/AllFeatures.php
// Comprehensive WPORM usage examples

use MJ\WPORM\Model;
use MJ\WPORM\Blueprint;
use MJ\WPORM\SchemaBuilder;
use MJ\WPORM\DB;

// 1. Model with all attributes, casts, soft delete (timestamp)
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
    // protected $deletedAtColumn = 'deleted_at'; // default
    // protected $softDeleteType = 'timestamp'; // default
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

// 2. Model with boolean soft delete
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

// 3. Related model
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

// 4. Usage examples

// Create users, products, reviews
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

$product = new Product([
    'name' => 'Widget',
    'price' => 19.99,
]);
$product->save();

$review = new Review([
    'product_id' => $product->id,
    'user_id' => $user->id,
    'rating' => 5,
    'comment' => 'Great product!'
]);
$review->save();

// Query with global scope (only verified users)
$activeUsers = User::all();

// Remove global scope for a query
$allUsers = User::query(false)->get();

// Soft delete (timestamp)
$user->delete(); // sets deleted_at
$trashedUsers = User::query()->onlyTrashed()->get();
$user->restore(); // sets deleted_at to null

// Soft delete (boolean)
$product->delete(); // sets deleted = 1
$trashedProducts = Product::query()->onlyTrashed()->get();
$product->restore(); // sets deleted = 0

// Relationship usage
$userPosts = $user->posts(); // hasMany
$reviewProduct = $review->product(); // belongsTo
$reviewUser = $review->user(); // belongsTo
$productReviews = $product->reviews(); // hasMany

// Casts usage
$price = $product->price; // float
$meta = $user->meta; // array
$verified = $user->verified; // bool

// Complex where/with usage
$productsWithReviews = Product::query()
    ->with(['reviews', 'reviews.user'])
    ->where('price', '>', 10)
    ->get();

// Using updateOrCreate
$newUser = User::updateOrCreate(
    ['email' => 'bob@example.com'],
    ['name' => 'Bob', 'country' => 'CA', 'age' => 25, 'verified' => false, 'subscribed' => true, 'meta' => []]
);

// Using firstOrCreate
$firstUser = User::firstOrCreate(
    ['email' => 'carol@example.com'],
    ['name' => 'Carol', 'country' => 'UK', 'age' => 28, 'verified' => true, 'subscribed' => false, 'meta' => []]
);

// Using transactions
User::query()->beginTransaction();
try {
    $user2 = new User([
        'name' => 'dave',
        'email' => 'dave@example.com',
        'country' => 'DE',
        'age' => 40,
        'verified' => true,
        'subscribed' => true,
        'meta' => []
    ]);
    $user2->save();
    User::query()->commit();
} catch (Exception $e) {
    User::query()->rollBack();
}

// Custom query with DB::table
$results = DB::table('products')->where('price', '>', 10)->get();

// Appended attributes
class ExampleWithAppends extends Model {
    protected $table = 'examples';
    protected $fillable = ['id', 'user_id'];
    protected $appends = ['user'];
    public function getUserAttribute() {
        return get_user_by('id', $this->user_id);
    }
}

// Example of forceDeleteWith (cascading soft deletes)
$post = $user->posts()->first();
if ($post) {
    $post->forceDeleteWith(['comments', 'tags']);
}

// Example of disabling all global scopes for a query
$allProducts = Product::query(false)->get();

// Example of using complex where/orWhere
$complexUsers = User::query()
    ->where(function ($q) {
        $q->where('country', 'US')
          ->where(function ($q2) {
              $q2->where('age', '>=', 18)
                  ->orWhere('verified', true);
          });
    })
    ->orWhere(function ($q) {
        $q->where('country', 'CA')
          ->where('subscribed', true);
    })
    ->get();

// Example of pagination
$paginated = User::query()->paginate(10, 1);

// Example of toArray
$userArray = $user->toArray();

// Example of custom static method
class Parts extends Model {
    public static function partsWithMinQty($minQty) {
        return static::query()->where('qty', '>=', $minQty)->get();
    }
}
$parts = Parts::partsWithMinQty(5);
