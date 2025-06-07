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
composer require mjkhajeh/wporm
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
Create a model class extending `MJ\WPORM\Model`:

```php
use MJ\WPORM\Model;
use MJ\WPORM\Blueprint;

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
use MJ\WPORM\SchemaBuilder;

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
$parts = Parts::query()->where('qty', '>', 5)->orderBy('qty', 'desc')->limit(10)->get(); // Limit to 10 results

// First result
$first = Parts::query()->where('product_id', 2)->first();
```

### Querying by a Specific Column

You can easily retrieve records by a specific column using the query builder's `where` method. For example, to get all parts with a specific `product_id`:

```php
$parts = Parts::query()->where('product_id', 123)->get();
```

Or, to get the first user by email:

```php
$user = User::query()->where('email', 'user@example.com')->first();
```

You can also use other comparison operators:

```php
$recentUsers = User::query()->where('created_at', '>=', '2025-01-01')->get();
```

This approach works for any column in your table.

### Creating or Updating Records: updateOrCreate

WPORM provides an `updateOrCreate` method, similar to Laravel Eloquent, for easily updating an existing record or creating a new one if it doesn't exist.

**Usage:**

```php
// Update if a user with this email exists, otherwise create a new one
$user = User::updateOrCreate(
    ['email' => 'user@example.com'],
    ['name' => 'John Doe', 'country' => 'US']
);
```

- The first argument is an array of attributes to search for.
- The second argument is an array of values to update or set if creating.
- Returns the updated or newly created model instance.

This is useful for upsert operations, such as syncing data or ensuring a record exists with certain values.

### Creating or Getting Records: firstOrCreate and firstOrNew

WPORM also provides `firstOrCreate` and `firstOrNew` methods, similar to Laravel Eloquent, for convenient record retrieval or creation.

**firstOrCreate Usage:**

```php
// Get the first user with this email, or create if not found
$user = User::firstOrCreate(
    ['email' => 'user@example.com'],
    ['name' => 'Jane Doe', 'country' => 'US']
);
```
- Returns the first matching record, or creates and saves a new one if none exists.

**firstOrNew Usage:**

```php
// Get the first user with this email, or instantiate (but do not save) if not found
$user = User::firstOrNew(
    ['email' => 'user@example.com'],
    ['name' => 'Jane Doe', 'country' => 'US']
);
if (!$user->exists) {
    $user->save(); // Save if you want to persist
}
```
- Returns the first matching record, or a new (unsaved) instance if none exists.

These methods are useful for ensuring a record exists, or for preparing a new record with default values if not found.

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
    ->limit(5) // Limit to top 5 parts
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

## Using newQuery()

The `newQuery()` method returns a fresh query builder instance for your model. This is useful when you want to start a new query chain, especially in custom scopes or advanced use cases. It is functionally similar to `query()`, but is a common convention in many ORMs.

**Example:**

```php
// Start a new query chain for the User model
$query = User::newQuery();
$activeUsers = $query->where('active', true)->get();
```

You can use `newQuery()` anywhere you would use `query()`. Both methods are available for convenience and compatibility with common ORM patterns.

## Timestamp Columns

You can customize how WPORM handles timestamp columns in your models. By default, models will automatically manage `created_at` and `updated_at` columns if `$timestamps = true` (the default).

### Example: Customizing Timestamp Column Names

```php
use MJ\WPORM\Model;
use MJ\WPORM\Blueprint;

class Article extends Model {
    protected $table = 'articles';
    protected $fillable = ['id', 'title', 'content', 'created_on', 'changed_on'];
    protected $timestamps = true; // default is true
    protected $createdAtColumn = 'created_on';
    protected $updatedAtColumn = 'changed_on';

    public function up(Blueprint $table) {
        $table->id();
        $table->string('title');
        $table->text('content');
        $table->timestamp('created_on');
        $table->timestamp('changed_on');
        $this->schema = $table->toSql();
    }
}
```

With this setup, WPORM will automatically set `created_on` and `changed_on` when you create or update an `Article` record.

### Example: Disabling Timestamps

If you do not want WPORM to manage any timestamp columns, set `$timestamps = false` in your model:

```php
use MJ\WPORM\Model;
use MJ\WPORM\Blueprint;

class LogEntry extends Model {
    protected $table = 'log_entries';
    protected $fillable = ['id', 'message'];
    protected $timestamps = false;

    public function up(Blueprint $table) {
        $table->id();
        $table->string('message');
        $this->schema = $table->toSql();
    }
}
```

In this case, WPORM will not attempt to set or update any timestamp columns automatically.

## Extending/Improving
- Add more casts by implementing `MJ\WPORM\Casts\CastableInterface`.
- Add more schema types in `Blueprint` as needed.
- Add more model events as needed.

## License
MIT

---

# Full Example: Using Every Feature of WPORM

```php
use MJ\WPORM\Model;
use MJ\WPORM\Blueprint;
use MJ\WPORM\SchemaBuilder;

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
- **Extending Casts:** Implement `MJ\WPORM\Casts\CastableInterface` for custom attribute casting logic.
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
- For large datasets, use pagination and limit/offset queries to avoid memory issues:
  ```php
  // For large datasets, use limit and offset for pagination:
  $usersPage2 = User::query()->orderBy('id')->limit(20)->offset(20)->get(); // Get users 21-40
  ```

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
