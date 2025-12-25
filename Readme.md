# WPORM - Lightweight WordPress ORM

WPORM is a lightweight Object-Relational Mapping (ORM) library for WordPress plugins. It provides an Eloquent-like API for defining models, querying data, and managing database schema, all while leveraging WordPress's native `$wpdb` database layer.

![wporm](https://github.com/user-attachments/assets/f84f6905-4279-4ee3-9e1f-9fb9a3fd2e51)

## Documentation
- [Methods list and documents](./Methods.md)
- [Blueprint and column types documents](./Blueprint.md)
- [Casts types and define custom casts](./CastsType.md)
- [DB usage and raw queries](./DB.md)
- [Debugging tips](./Debugging.md)

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
require_once __DIR__ . '/ORM/DB.php';
require_once __DIR__ . '/ORM/Collection.php';
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

### Unique Indexes (Eloquent-style)

You can add a unique index to a column using Eloquent-style chaining:

```php
$table->string('email')->unique();
$table->integer('user_id')->unique('custom_index_name');
```

For multi-column unique indexes, use:

```php
$table->unique(['col1', 'col2']);
```

This works for all column types and matches Eloquent's API.

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

// Raw ORDER BY example
$parts = Parts::query()->where('qty', '>', 5)
    ->orderByRaw('FIELD(name, ?, ?)', ['Widget', 'Gadget'])
    ->limit(10)
    ->get();

// This allows custom SQL ordering, e.g. sorting by a specific value list. Bindings are safely passed to $wpdb->prepare.

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

// Disable global scopes for this call
$user = User::updateOrCreate(
    ['email' => 'user@example.com'],
    ['name' => 'John Doe', 'country' => 'US'],
    false // disables global scopes
);
```

- The first argument is an array of attributes to search for.
- The second argument is an array of values to update or set if creating.
- The optional third argument disables global scopes if set to `false` (default is `true`).
- Returns the updated or newly created model instance.

This is useful for upsert operations, such as syncing data or ensuring a record exists with certain values.

### Creating or Getting Records: firstOrCreate and firstOrNew
### Inserting Records: insertOrIgnore

WPORM provides an `insertOrIgnore` method, similar to Laravel Eloquent, for inserting one or multiple records and ignoring duplicate key errors (such as unique constraint violations).

**Usage:**

```php
// Insert a single user, ignore if email already exists
$success = User::insertOrIgnore([
    'email' => 'user@example.com',
    'name' => 'Jane Doe',
    'country' => 'US'
]);

// Insert multiple users, ignore duplicates
$data = [
    ['email' => 'user1@example.com', 'name' => 'User One'],
    ['email' => 'user2@example.com', 'name' => 'User Two'],
    ['email' => 'user1@example.com', 'name' => 'User One Duplicate'], // duplicate email
];
$success = User::insertOrIgnore($data);
```

- Returns `true` if the insert(s) succeeded or were ignored due to duplicate keys.
- Returns `false` on other errors.
- Uses MySQL's `INSERT IGNORE` for safe upsert-like behavior.

This is useful for bulk imports or situations where you want to avoid errors on duplicate records.

WPORM also provides `firstOrCreate` and `firstOrNew` methods, similar to Laravel Eloquent, for convenient record retrieval or creation.

**firstOrCreate Usage:**

```php
// Get the first user with this email, or create if not found
$user = User::firstOrCreate(
    ['email' => 'user@example.com'],
    ['name' => 'Jane Doe', 'country' => 'US']
);

// Disable global scopes for this call
$user = User::firstOrCreate(
    ['email' => 'user@example.com'],
    ['name' => 'Jane Doe', 'country' => 'US'],
    false // disables global scopes
);
```
- Returns the first matching record, or creates and saves a new one if none exists.
- The optional third argument disables global scopes if set to `false` (default is `true`).

**firstOrNew Usage:**

```php
// Get the first user with this email, or instantiate (but do not save) if not found
$user = User::firstOrNew(
    ['email' => 'user@example.com'],
    ['name' => 'Jane Doe', 'country' => 'US']
);

// Disable global scopes for this call
$user = User::firstOrNew(
    ['email' => 'user@example.com'],
    ['name' => 'Jane Doe', 'country' => 'US'],
    false // disables global scopes
);
if (!$user->exists) {
    $user->save(); // Save if you want to persist
}
```
- Returns the first matching record, or a new (unsaved) instance if none exists.
- The optional third argument disables global scopes if set to `false` (default is `true`).

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

## Pagination

WPORM supports Eloquent-style pagination with the following methods on the query builder:

### paginate($perPage = 15, $page = null)

Returns a paginated result array with total count and page info:

```php
$result = User::query()->where('active', true)->paginate(10, 2);
// $result = [
//   'data' => Collection,
//   'total' => int,
//   'per_page' => int,
//   'current_page' => int,
//   'last_page' => int,
//   'from' => int,
//   'to' => int
// ]
```

### simplePaginate($perPage = 15, $page = null)

Returns a paginated result array without total count (more efficient for large tables):

```php
$result = User::query()->where('active', true)->simplePaginate(10, 2);
// $result = [
//   'data' => Collection,
//   'per_page' => int,
//   'current_page' => int,
//   'next_page' => int|null
// ]
```

See [Methods.md](./Methods.md) for more details and options.

## Attribute Casting
Add a `$casts` property to your model:
```php
protected $casts = [
    'qty' => 'int',
    'meta' => 'json',
];
```

## Array Conversion and Casting

- Call `->toArray()` on a model or a collection to get an array representation with all casts applied.
- Built-in types (e.g. 'int', 'bool', 'float', 'json', etc.) are handled natively and will not be instantiated as classes.
- Custom cast classes must implement `MJ\WPORM\Casts\CastableInterface`.

Example:

```php
protected $casts = [
    'user_id'    => 'int',
    'from'       => Time::class, // custom cast
    'to'         => Time::class, // custom cast
    'use_default'=> 'bool',
    'status'     => 'bool',
];

$model = Times::find(1);
$array = $model->toArray();

$collection = Times::query()->get();
$arrays = $collection->toArray();
```

- Custom cast classes will be instantiated and their `get()` method called.
- Built-in types will be cast using native PHP logic.

## Relationships

WPORM supports Eloquent-style relationships. You can define them in your model using the following methods:

- **hasOne**: One-to-one
  ```php
  public function profile() {
      return $this->hasOne(Profile::class, 'user_id');
  }
  ```
- **hasMany**: One-to-many
  ```php
  public function posts() {
      return $this->hasMany(Post::class, 'user_id');
  }
  ```
- **belongsTo**: Inverse one-to-one or many
  ```php
  public function user() {
      return $this->belongsTo(User::class, 'user_id');
  }
  ```
- **belongsToMany**: Many-to-many (with optional pivot table and keys)
  ```php
  public function roles() {
      return $this->belongsToMany(Role::class, 'user_role', 'user_id', 'role_id');
  }
  ```
- **hasManyThrough**: Has-many-through
  ```php
  public function comments() {
      return $this->hasManyThrough(Comment::class, Post::class, 'user_id', 'post_id');
  }
  ```

All relationship methods return either a model instance or a `Collection` of models. You can use them just like in Eloquent.

### Relationship Existence Filtering: whereHas, orWhereHas, has

- `whereHas('relation', function($q) { ... })`: Filter models where the relation exists and matches constraints.
- `orWhereHas('relation', function($q) { ... })`: OR version of whereHas.
- `has('relation', '>=', 2)`: Filter models with at least (or exactly, or at most) N related records. Operator and count are optional (defaults to ">= 1").

**Examples:**
```php
// Users with at least one post
User::query()->has('posts')->get();

// Users with at least 5 posts
User::query()->has('posts', '>=', 5)->get();

// Users with exactly 2 posts
User::query()->has('posts', '=', 2)->get();

// Users with at least one published post
User::query()->whereHas('posts', function($q) {
    $q->where('published', 1);
})->get();
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

## Appended (Computed) Attributes

You can add computed (virtual) attributes to your model's array/JSON output using the `$appends` property, just like in Eloquent.

```php
protected $appends = ['user'];

public function getUserAttribute() {
    return get_user_by('id', $this->user_id);
}
```

- Appended attributes are included in `toArray()` and JSON output.
- The value is resolved via a `get{AttributeName}Attribute()` accessor or, if not present, by a public property.
- Do **not** set appended attributes in `retrieved()`; use accessors instead.

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

## Raw Table Queries with DB::table()

WPORM now supports Eloquent-style raw table queries using the `DB` class:

```php
use MJ\WPORM\DB;

// Update posts with IDs 3, 4, 5
db::table('post')
    ->whereIn('id', [3, 4, 5])
    ->update(['title' => 'Updated Title']);

// Select rows from any table
db::table('custom_table')->where('status', 'active')->get();
```

See [DB.md](./DB.md) for more details.

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

## Global Scopes

You can define global scopes on your model to automatically apply query constraints to all queries for that model.

Example:

```php
class Post extends \MJ\WPORM\Model {
    protected static function boot() {
        parent::boot();
        static::addGlobalScope('published', function($query) {
            $query->where('status', 'published');
        });
    }
}
```

All queries will now include `status = 'published'` automatically:

```php
$posts = Post::all(); // Only published posts
```

To disable global scopes for a query:

```php
$allPosts = Post::query(false)->get(); // disables all global scopes
// or
$allPosts = Post::query()->withoutGlobalScopes()->get();
```

To remove a specific global scope at runtime:

```php
Post::removeGlobalScope('published');
```

### Per-relation global-scope control (eager loads)

You can disable global scopes for a specific relation when using `with()` to eager-load relations. Pass an array for the relation with the optional key `disableGlobalScopes` set to `true` and an optional `constraint` callable. This affects only the related query used to load that relation.

Examples:

```php
// Disable global scopes for the 'topics' relation only
$department = Departments::query(false)
    ->with(['topics' => ['disableGlobalScopes' => true]])
    ->orderBy('id', 'desc')
    ->first();

print_r($department->topics);
```

```php
// Disable global scopes and also apply a constraint to the related query
$dept = Departments::query()
    ->with([ 
        'topics' => [
            'disableGlobalScopes' => true,
            'constraint' => function($q) { $q->where('active', true); }
        ]
    ])
    ->first();
```

You can still use the shorthand closure form for simple constraints (unchanged):

```php
$dept = Departments::query()->with(['topics' => function($q) {
    $q->where('active', true);
}])->first();
```


## Soft Deletes

WPORM supports Eloquent-style soft deletes, allowing you to "delete" records without actually removing them from the database. To enable soft deletes on a model, set the `$softDeletes` property to `true`:

```php
class User extends Model {
    protected $softDeletes = true;
    // Optionally customize the deleted_at column:
    // protected $deletedAtColumn = 'deleted_at';
    // Optionally set the soft delete type (see below)
    // protected $softDeleteType = 'timestamp'; // or 'boolean'
}
```

### Soft Delete Strategies: Timestamp vs Boolean Flag

WPORM supports two soft delete strategies:

1. **Timestamp column (default, Eloquent-style):**
   - Uses a `deleted_at` (or custom) column to store the deletion datetime.
   - Set `$softDeletes = true;` and (optionally) `$deletedAtColumn = 'deleted_at';` in your model.
   - Example:
     ```php
     class User extends Model {
         protected $softDeletes = true;
         // protected $deletedAtColumn = 'deleted_at'; // optional
         // protected $softDeleteType = 'timestamp'; // optional, default
     }
     ```
   - In your migration/schema:
     ```php
     $table->timestamp('deleted_at')->nullable();
     ```

2. **Boolean flag column:**
   - Uses a boolean column (e.g., `deleted`) to indicate soft deletion (`1` = deleted, `0` = not deleted).
   - Set `$softDeletes = true;`, `$deletedAtColumn = 'deleted'`, and `$softDeleteType = 'boolean';` in your model.
   - Example:
     ```php
     class Product extends Model {
         protected $softDeletes = true;
         protected $deletedAtColumn = 'deleted'; // boolean column
         protected $softDeleteType = 'boolean'; // enable boolean-flag mode
     }
     ```
   - In your migration/schema:
     ```php
     $table->boolean('deleted')->default(0);
     ```

#### How it works
- **Timestamp mode:**
  - `delete()` sets `deleted_at` to the current datetime.
  - `restore()` sets `deleted_at` to `null`.
  - Queries exclude rows where `deleted_at` is not null (unless `withTrashed()` or `onlyTrashed()` is used).
- **Boolean mode:**
  - `delete()` sets `deleted` to `1` (true).
  - `restore()` sets `deleted` to `0` (false).
  - Queries exclude rows where `deleted` is true (unless `withTrashed()` or `onlyTrashed()` is used).

#### Example Usage
```php
// Timestamp soft deletes (default)
$user = User::find(1);
$user->delete(); // sets deleted_at
User::query()->withTrashed()->get(); // includes soft-deleted
User::query()->onlyTrashed()->get(); // only soft-deleted
$user->restore(); // sets deleted_at to null

// Boolean flag soft deletes
$product = Product::find(1);
$product->delete(); // sets deleted = 1
Product::query()->withTrashed()->get(); // includes deleted
Product::query()->onlyTrashed()->get(); // only deleted
$product->restore(); // sets deleted = 0
```


## Conditional Queries: when()

WPORM supports Eloquent-style conditional queries using the `when()` method. This allows you to add query constraints only if a given condition is true, making your code more readable and dynamic.

**Usage:**
```php
// Add a where clause only if $isActive is true
$users = User::query()
    ->when($isActive, function ($query) {
        $query->where('active', true);
    })
    ->get();

// You can also provide a default callback for the false case
$users = User::query()
    ->when($country, function ($query, $country) {
        $query->where('country', $country);
    }, function ($query) {
        $query->where('country', 'US'); // fallback
    })
    ->get();
```

- The first argument is the condition value.
- The second argument is a callback executed if the condition is truthy.
- The optional third argument is a callback executed if the condition is falsy.

This method is available on both the query builder and as a static method on models.


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