<?php
// Example/OtherFeatures.php
// Demonstrates additional ORM features: updateOrCreate, firstOrCreate, transactions, appended attributes, custom static methods, pagination, toArray, forceDeleteWith, disabling global scopes, complex where/orWhere

use MJ\WPORM\Model;
use MJ\WPORM\DB;

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
