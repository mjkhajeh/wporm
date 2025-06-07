# WPORM - Lightweight WordPress ORM

WPORM is a lightweight Object-Relational Mapping (ORM) library for WordPress plugins. It provides an Eloquent-like API for defining models, querying data, and managing database schema, all while leveraging WordPress's native `$wpdb` database layer.

![wporm](https://github.com/user-attachments/assets/f84f6905-4279-4ee3-9e1f-9fb9a3fd2e51)

## Features
- **Model-based data access**: Define models for your tables and interact with them using PHP objects.
- **Schema management**: Create and modify tables using a fluent schema builder.
- **Query builder**: Chainable query builder for flexible and safe SQL queries.
- **Attribute casting**: Automatic type casting for model attributes.
- **Relationships**: Define `hasOne`, `hasMany`, `belongsTo`, `belongsToMany`, and `hasManyThrough` relationships.
- **Events**: Hooks for model lifecycle events (creating, updating, deleting).
- **Global scopes**: Add global query constraints to models.

## Installation

### With Composer (Recommended)
You can install WPORM via Composer. In your plugin or theme directory, run:

```sh
composer require your-vendor/wporm
```

Then include Composer's autoloader in your plugin bootstrap file:

```php
require_once __DIR__ . '/vendor/autoload.php';
```

### Manual Installation
1. Place the `ORM` directory in your plugin folder.
2. Include the ORM in your plugin bootstrap:

```php
require_once __DIR__ . '/ORM/Helpers.php';
require_once __DIR__ . '/ORM/Model.php';
require_once __DIR__ . '/ORM/QueryBuilder.php';
require_once __DIR__ . '/ORM/Blueprint.php';
require_once __DIR__ . '/ORM/SchemaBuilder.php';
require_once __DIR__ . '/ORM/ColumnDefinition.php';
```

## Defining a Model
Create a model class extending `WPORM\Model`:

```php
use WPORM\Model;
use WPORM\Schema\Blueprint;

class Parts extends Model {
    protected $table = 'parts';
    protected $fillable = ['id', 'part_id', 'qty', 'product_id'];
    protected $timestamps = false;

    public function up(Blueprint $blueprint) {
        $blueprint->id();
        $blueprint->integer('part_id');
        $blueprint->integer('product_id');
        $blueprint->integer('qty');
        $blueprint->index('product_id');
        $this->schema = $blueprint->toSql();
    }
}
```

> **Note:** When using `$table` in custom SQL queries, do **not** manually add the WordPress prefix (e.g., `$wpdb->prefix`). The ORM automatically handles table prefixing. Use `$table = (new User)->getTable();` as shown in the next, which returns the fully-prefixed table name.

## Schema Management
Create or update tables using the model's `up` method and the `SchemaBuilder`:

```php
use WPORM\Schema\SchemaBuilder;

$schema = new SchemaBuilder($wpdb);
$schema->create('parts', function($table) {
    $table->id();
    $table->integer('part_id');
    $table->integer('product_id');
    $table->integer('qty');
    $table->index('product_id');
});
```

## Basic Usage
### Creating a Record
```php
$part = new Parts(['part_id' => 1, 'product_id' => 2, 'qty' => 10]);
$part->save();
```

### Querying Records
```php
// Get all parts
$all = Parts::all();

// Find by primary key
$part = Parts::find(1);

// Where clause
$parts = Parts::query()->where('qty', '>', 5)->orderBy('qty', 'desc')->get();

// First result
$first = Parts::query()->where('product_id', 2)->first();
```

### Updating a Record
```php
$part = Parts::find(1);
$part->qty = 20;
$part->save();
```

### Deleting a Record
```php
$part = Parts::find(1);
$part->delete();
```

## Attribute Casting
Add a `$casts` property to your model:
```php
protected $casts = [
    'qty' => 'int',
    'meta' => 'json',
];
```

## Relationships
```php
// In Product model
public function parts() {
    return $this->hasMany(Parts::class, 'product_id');
}

// In Parts model
public function product() {
    return $this->belongsTo(Product::class, 'product_id');
}
```

## Custom Attribute Accessors/Mutators
```php
public function getQtyAttribute() {
    return $this->attributes['qty'] * 2;
}

public function setQtyAttribute($value) {
    $this->attributes['qty'] = $value / 2;
}
```

## Transactions
```php
Parts::query()->beginTransaction();
// ...
Parts::query()->commit();
// or
Parts::query()->rollBack();
```

## Custom Queries
You can execute custom SQL queries using the underlying `$wpdb` instance or by extending the model/query builder. For example:

```php
// Using the query builder for a custom select
$results = Parts::query()
    ->select(['part_id', 'SUM(qty) as total_qty'])
    ->where('product_id', 2)
    ->orderBy('total_qty', 'desc')
    ->get();

// Using $wpdb directly for full custom SQL
global $wpdb;
$table = (new Parts)->getTable();
$results = $wpdb->get_results(
    $wpdb->prepare("SELECT part_id, SUM(qty) as total_qty FROM $table WHERE product_id = %d GROUP BY part_id", 2),
    ARRAY_A
);
```

You can also add custom static methods to your model for more complex queries:

```php
class Parts extends Model {
    // ...existing code...
    public static function partsWithMinQty($minQty) {
        return static::query()->where('qty', '>=', $minQty)->get();
    }
}

// Usage:
$parts = Parts::partsWithMinQty(5);
```

## Complex Where Statements
WPORM now supports complex nested where/orWhere statements using closures, similar to Eloquent:

```php
$users = User::query()
    ->where(function ($query) {
        $query->where('country', 'US')
              ->where(function ($q) {
                  $q->where('age', '>=', 18)
                    ->orWhere('verified', true);
              });
    })
    ->orWhere(function ($query) {
        $query->where('country', 'CA')
              ->where('subscribed', true);
    })
    ->get();
```

You can still use multiple `where` calls for AND logic, and `orWhere` for OR logic:

```php
$parts = Parts::query()
    ->where('qty', '>', 5)
    ->where('product_id', 2)
    ->orWhere('qty', '<', 2)
    ->get();
```
> Note: For very advanced SQL, you can always use `$wpdb` directly.
> 
You can also use `$wpdb` directly for complex SQL logic:

```php
global $wpdb;
$table = (new User)->getTable();
$results = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT * FROM $table WHERE (country = %s AND (age >= %d OR verified = %d)) OR (country = %s AND subscribed = %d)",
        'US', 18, 1, 'CA', 1
    ),
    ARRAY_A
);
```

## Extending/Improving
- Add more casts by implementing `WPORM\Casts\CastableInterface`.
- Add more schema types in `Blueprint` as needed.
- Add more model events as needed.

## License
MIT

---

# Full Example: Using Every Feature of WPORM

```php
use WPORM\Model;
use WPORM\Schema\Blueprint;
use WPORM\Schema\SchemaBuilder;

global $wpdb;

// 1. Define Models
class User extends Model {
    protected $table = 'users';
    protected $fillable = ['id', 'name', 'email', 'country', 'age', 'verified', 'subscribed', 'meta'];
    protected $casts = [
        'age' => 'int',
        'verified' => 'bool',
        'subscribed' => 'bool',
        'meta' => 'json',
    ];
    public function up(Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('email');
        $table->string('country', 2);
        $table->integer('age');
        $table->boolean('verified');
        $table->boolean('subscribed');
        $table->json('meta');
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
}

class Post extends Model {
    protected $table = 'posts';
    protected $fillable = ['id', 'user_id', 'title', 'content', 'meta'];
    protected $casts = [
        'meta' => 'json',
    ];
    public function up(Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('user_id');
        $table->string('title');
        $table->text('content');
        $table->json('meta');
        $table->timestamps();
        $table->foreign('user_id', 'users');
        $this->schema = $table->toSql();
    }
    public function user() {
        return $this->belongsTo(User::class, 'user_id');
    }
}

// 2. Schema Management
$schema = new SchemaBuilder($wpdb);
$schema->create('users', function($table) {
    $table->id();
    $table->string('name');
    $table->string('email');
    $table->string('country', 2);
    $table->integer('age');
    $table->boolean('verified');
    $table->boolean('subscribed');
    $table->json('meta');
    $table->timestamps();
});
$schema->create('posts', function($table) {
    $table->id();
    $table->unsignedBigInteger('user_id');
    $table->string('title');
    $table->text('content');
    $table->json('meta');
    $table->timestamps();
    $table->foreign('user_id', 'users');
});

// 3. Creating Records
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

$post = new Post([
    'user_id' => $user->id,
    'title' => 'Hello World',
    'content' => 'This is a post.',
    'meta' => ['tags' => ['intro', 'welcome']]
]);
$post->save();

// 4. Querying Records
$allUsers = User::all();
$found = User::find($user->id);
$adults = User::query()->where('age', '>=', 18)->get();
$firstUser = User::query()->where('country', 'US')->first();

// 5. Updating Records
$found->subscribed = true;
$found->save();

// 6. Deleting Records
$post->delete();

// 7. Attribute Casting
$casted = $found->meta; // array

// 8. Relationships
$userPosts = $user->posts(); // hasMany
$postUser = $post->user();  // belongsTo

// 9. Custom Accessors/Mutators
$name = $user->name; // Accessor (uppercased)
$user->name = 'bob'; // Mutator (ucfirst)
$user->save();

// 10. Transactions
User::query()->beginTransaction();
try {
    $user2 = new User([
        'name' => 'eve',
        'email' => 'eve@example.com',
        'country' => 'CA',
        'age' => 22,
        'verified' => false,
        'subscribed' => true,
        'meta' => []
    ]);
    $user2->save();
    User::query()->commit();
} catch (Exception $e) {
    User::query()->rollBack();
}

// 11. Global Scopes
User::addGlobalScope('active', function($query) {
    $query->where('verified', true);
});
$activeUsers = User::all();
User::removeGlobalScope('active');

// 12. Complex Where Statements
$complex = User::query()
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

// 13. Custom Queries
$custom = User::query()
    ->select(['country', 'COUNT(*) as total'])
    ->groupBy('country')
    ->get();

// 14. $wpdb Direct SQL
$table = (new User)->getTable();
$results = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT * FROM $table WHERE (country = %s AND (age >= %d OR verified = %d)) OR (country = %s AND subscribed = %d)",
        'US', 18, 1, 'CA', 1
    ),
    ARRAY_A
);
```

This example demonstrates every major feature of WPORM: model definition, schema, CRUD, casting, relationships, accessors/mutators, transactions, global scopes, complex queries, custom queries, and direct SQL.

## Troubleshooting & Tips

- **Table Prefixing:** Always use `$table = (new ModelName)->getTable();` to get the correct, prefixed table name for custom SQL. Do not manually prepend `$wpdb->prefix`.
- **Model Booting:** If you add static boot methods or global scopes, ensure you call them before querying if not using the model's constructor.
- **Schema Changes:** If you change your model's `up()` schema, you may need to drop and recreate the table or use the `SchemaBuilder`'s `table()` method for migrations.
- **Events:** You can add `creating`, `updating`, and `deleting` methods to your models for event hooks.
- **Extending Casts:** Implement `WPORM\Casts\CastableInterface` for custom attribute casting logic.
- **Testing:** Always test your queries and schema changes on a staging environment before deploying to production.

## Contributing

Contributions, bug reports, and feature requests are welcome! Please open an issue or submit a pull request.

## Credits

WPORM is inspired by Laravel's Eloquent ORM and adapted for the WordPress ecosystem.

---

## Version

- **Current Version:** 1.0.0
- **Changelog:**
  - Initial release with full Eloquent-style ORM features for WordPress.

## Security Note

- Always validate and sanitize user input, even when using the ORM. The ORM helps prevent SQL injection, but you are responsible for data integrity and security.

## Performance Tips

- Use indexes for columns you frequently query (e.g., foreign keys, search fields). The ORM's schema builder supports `$table->index('column')`.
- For large datasets, use pagination and limit/offset queries to avoid memory issues.

## FAQ

**Q: Why is my table not created?**
- A: Ensure your model's `up()` method is correct and that you call the schema builder. Check for errors in your SQL or schema definition.

**Q: How do I debug a failed query?**
- A: Use `$wpdb->last_query` and `$wpdb->last_error` after running a query to inspect the last executed SQL and any errors.

**Q: Can I use this ORM outside of WordPress?**
- A: No, it is tightly coupled to WordPress's `$wpdb` and plugin environment.

## Resources

- [WordPress Plugin Developer Handbook](https://developer.wordpress.org/plugins/)
- [Laravel Eloquent ORM Documentation](https://laravel.com/docs/eloquent)

## License Details

This project is licensed under the MIT License. See the LICENSE file or [MIT License](https://opensource.org/licenses/MIT) for details.

---
