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
- **Relationships**: Define `hasOne`, `hasMany`, `belongsTo`, `belongsToMany`, and `hasManyThrough` relationships, with eager loading via `with()` and existence filtering via `whereHas()`/`has()`. Polymorphic relationships (`morphOne`, `morphMany`, `morphTo`) are also supported, including an optional `morphMap()` for short type aliases.
- **Convenient creation**: `create()` for a one-line insert + return model, plus `updateOrCreate()`, `firstOrCreate()`, and `firstOrNew()` for upsert-style lookups.
- **Aggregates & utilities**: `sum()`, `avg()`, `min()`, `max()`, `value()`, `pluck()`, `exists()`/`doesntExist()`, and `increment()`/`decrement()`.
- **Fail-fast lookups**: `findOrFail()`/`firstOrFail()` (including array-of-ids lookups, and `Collection::firstOrFail()`) throw a `ModelNotFoundException` instead of silently returning `null`.
- **Batch processing**: `chunk()` and `each()` for iterating large result sets in pages without loading everything into memory at once.
- **Serialization**: `toArray()`/`toJson()`/`__toString()` on both models and collections, with `$hidden`/`$visible` support and safe (exception-on-failure) JSON encoding.
- **Raw SQL expressions**: `selectRaw()`, `whereRaw()`/`orWhereRaw()`, `groupByRaw()`, `havingRaw()`/`orHavingRaw()`, and `orderByRaw()` for dropping down to raw SQL with safe, bound placeholders.
- **Subqueries**: `fromSub()` for derived tables, `selectSub()` for scalar subselects in the SELECT list, and `whereSub()`/`whereInSub()`/`whereNotInSub()` (plus OR variants) for subqueries in WHERE — all accepting a `QueryBuilder`, `Closure`, or raw SQL string, Eloquent-style.
- **Combining queries**: `union()`/`unionAll()` to combine two or more queries' result sets, Eloquent-style.
- **Events**: Model lifecycle event hooks (`creating`, `updating`, `deleting`, etc.) via overridable methods, Eloquent-style `$dispatchesEvents` property mapping, and a standalone `EventDispatcher` for global listeners — no Laravel dependency required.
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
require_once __DIR__ . '/ORM/Events/ModelEvent.php';
require_once __DIR__ . '/ORM/Events/Events.php';
require_once __DIR__ . '/ORM/EventDispatcher.php';
require_once __DIR__ . '/ORM/Model.php';
require_once __DIR__ . '/ORM/QueryBuilder.php';
require_once __DIR__ . '/ORM/Blueprint.php';
require_once __DIR__ . '/ORM/SchemaBuilder.php';
require_once __DIR__ . '/ORM/ColumnDefinition.php';
require_once __DIR__ . '/ORM/DB.php';
require_once __DIR__ . '/ORM/Collection.php';
require_once __DIR__ . '/ORM/ModelNotFoundException.php';
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
    }
}
```

> **Note:** Just build your columns on the `$blueprint` passed into `up()` — WPORM reads the schema directly from it via `$blueprint->toSql()`. You do **not** need to (and should not) manually assign `$this->schema` anymore; `up(Blueprint $blueprint)` is now the single source of truth for table schema.

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

> `SchemaBuilder::create()` automatically wraps your column definitions in a full
> `CREATE TABLE {prefix}parts (...) {charset_collate};` statement (using `$wpdb->get_charset_collate()`)
> before handing it to WordPress's `dbDelta()`, and prefixes the table name for you — you only
> need to supply the bare table name and build columns on `$table`, as shown above.

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

> Prefer a one-liner? `Parts::create([...])` does the same thing (instantiate + `save()`) in a single call — see [One-Line Create: create()](#one-line-create-create) below.

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

### Finding a Record or Failing: findOrFail and firstOrFail

When a missing record should be treated as an error rather than handled as `null`, use `findOrFail()` / `firstOrFail()` (Eloquent-style). They behave exactly like `find()` / `first()` — same single query, same `retrieved()` event — except they throw a `MJ\WPORM\ModelNotFoundException` instead of returning `null` when nothing matches.

```php
use MJ\WPORM\ModelNotFoundException;

// Find by primary key, or throw
try {
    $user = User::findOrFail(1);
} catch (ModelNotFoundException $e) {
    wp_die('User not found', '', ['response' => 404]);
}

// Works mid-chain on the query builder too
$user = User::with('posts')->findOrFail(1);
$user = User::query()->withTrashed()->findOrFail(1);

// Find multiple records by an array of ids — returns a Collection.
// find() simply omits any ids that don't exist; findOrFail() throws
// if ANY of them are missing, listing every missing id.
$users = User::find([1, 2, 3]);        // Collection of whichever ids exist
try {
    $users = User::findOrFail([1, 2, 3]);
} catch (ModelNotFoundException $e) {
    // $e->getIds() === [2, 3] if only id 1 existed
}

// First match by attributes, or throw
$user = User::firstOrFail(['email' => 'user@example.com']);

// Or build up arbitrary constraints on the query builder, then fail if empty
$user = User::query()
    ->where('active', true)
    ->orderBy('created_at', 'desc')
    ->firstOrFail();

// Collection::firstOrFail() — same idea, but on an already-fetched
// Collection (e.g. after in-memory filtering), where re-running a query
// isn't an option:
$activeAdmins = User::query()->where('role', 'admin')->get()
    ->filter(fn($u) => $u->active);
try {
    $admin = $activeAdmins->firstOrFail();
} catch (ModelNotFoundException $e) {
    // no active admins found
}
```

`ModelNotFoundException` extends PHP's built-in `\RuntimeException`, and exposes `getModel()` (the model class that was queried) and `getIds()` (the id(s) passed to `findOrFail()` — a single value, or the array of missing ids for an array lookup; `null` for `firstOrFail()`/`Collection::firstOrFail()`) so error handlers can respond appropriately (e.g. a JSON 404) without parsing the message string.

### One-Line Create: create()

WPORM provides a `create` static method, similar to Laravel Eloquent, for instantiating a new model with the given attributes, saving it, and returning the instance — all in one call.

**Usage:**

```php
// One-line insert + return model
$user = User::create([
    'name' => 'John Doe',
    'email' => 'user@example.com',
]);

echo $user->id; // the newly-inserted primary key
```

- Attributes are mass-assigned through the same `$fillable`/`$guarded` rules as `new Model([...])` — any attribute not allowed through mass assignment is silently skipped, exactly like the constructor.
- Equivalent to (and a shorthand for):
  ```php
  $user = new User(['name' => 'John Doe', 'email' => 'user@example.com']);
  $user->save();
  ```
- Returns the model instance regardless of whether the underlying `save()` succeeded; check `$user->exists` (or your own validation beforehand) if you need to confirm the insert actually happened.

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

### Bulk Upsert: upsert

WPORM provides an Eloquent-style `upsert` method for inserting or updating multiple records in a single query. It uses MySQL's `INSERT ... ON DUPLICATE KEY UPDATE` syntax for maximum efficiency.

**Signature:**
```php
Model::upsert(array $values, array|string $uniqueBy, array|null $update = null)
```

**Parameters:**
- `$values` — An array of records (each an associative array) to insert or update.
- `$uniqueBy` — The column(s) that uniquely identify a record (must have a unique or primary key constraint in the database).
- `$update` — (Optional) The columns to update when a duplicate is found. If omitted or `null`, all columns except `$uniqueBy` are updated automatically.

**Examples:**
```php
// Upsert multiple records — insert new ones, update existing by email
User::upsert([
    ['email' => 'alice@test.com', 'name' => 'Alice', 'votes' => 1],
    ['email' => 'bob@test.com', 'name' => 'Bob', 'votes' => 2],
], ['email'], ['name', 'votes']);

// Auto-detect update columns (updates all columns except the unique key)
User::upsert([
    ['email' => 'alice@test.com', 'name' => 'Alice Updated', 'votes' => 10],
], 'email');

// Single record upsert
User::upsert(
    ['email' => 'alice@test.com', 'name' => 'Alice', 'votes' => 5],
    ['email'],
    ['votes']
);

// Also available via DB::table() for raw table queries
use MJ\WPORM\DB;

DB::table('users')->upsert([
    ['email' => 'alice@test.com', 'name' => 'Alice', 'votes' => 1],
    ['email' => 'bob@test.com', 'name' => 'Bob', 'votes' => 2],
], ['email'], ['name', 'votes']);
```

- If timestamps are enabled on the model, `created_at` and `updated_at` are handled automatically.
- Returns the number of affected rows, or `false` on failure.
- If no update columns are specified and none can be inferred, falls back to `INSERT IGNORE` behavior.

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

### Truncating a Table
You can quickly remove all rows from a model's table using `truncate()` on the model query builder:

```php
// Remove all records from the table
Parts::query()->truncate();
```

## Aggregates & Utility Methods

WPORM provides Eloquent-style aggregate and utility methods on the query builder for common lookups, so you don't always need to fetch full models just to compute a number or check a single value.

```php
// Sum, average, min, max
$totalQty   = Parts::query()->where('product_id', 2)->sum('qty');
$avgPrice   = Product::query()->avg('price');     // or ->average('price')
$cheapest   = Product::query()->min('price');
$mostExpensive = Product::query()->max('price');

// Get a single column's value from the first matching row
$email = User::query()->where('id', 1)->value('email');

// Get a flat array of a column's values (optionally keyed by another column)
$emails     = User::query()->pluck('email');
$emailsById = User::query()->pluck('email', 'id');

// Existence checks
if (User::query()->where('email', $email)->exists()) {
    // already taken
}
if (User::query()->where('email', $email)->doesntExist()) {
    // free to use
}
```

### increment() / decrement()

Bump a numeric column up or down in a single atomic `UPDATE` statement — no need to read the value, add to it in PHP, then write it back.

```php
// Instance usage — scoped automatically to this model's primary key
$user = User::find(1);
$user->increment('votes');                 // votes + 1
$user->increment('votes', 5);               // votes + 5
$user->increment('votes', 1, [
    'last_voted_at' => current_time('mysql'),
]);

$user->decrement('credits');                // credits - 1
$user->decrement('credits', 3);             // credits - 3

// Query builder usage — affects every row matching the query
User::query()->where('active', true)->increment('votes');
User::query()->where('role', 'admin')->increment('credits', 10);
User::query()->where('subscription', 'expired')->decrement('seats');
```

- If the model uses timestamps, `updated_at` is touched automatically (unless you pass it yourself via the optional `$extra` array).
- The instance form keeps the in-memory model in sync with the new value, so you don't need to `refresh()`/re-fetch afterward.

See [Methods.md](./Methods.md#aggregates--utility-methods) for the full list with signatures.

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

## Processing Large Datasets: chunk() and each()

When you need to iterate over a large number of records, loading them all into memory at once with `get()` isn't practical. `chunk()` and `each()` solve this Eloquent-style, by running the query in pages (using the same `limit()`/`offset()` mechanism as `paginate()`) and feeding results to a callback as they come in.

### chunk($count, $callback)

Runs the query in pages of `$count` records, calling `$callback` once per page with a `Collection` of models:

```php
User::query()->where('active', true)->chunk(100, function ($users) {
    foreach ($users as $user) {
        // ...
    }
});
```

The callback also receives the current page number as a second argument, and can return `false` to stop processing early:

```php
Order::query()->chunk(200, function ($orders, $page) {
    foreach ($orders as $order) {
        if ($order->total > 1_000_000) {
            return false; // stops chunk() immediately
        }
    }
});
```

### each($callback, $count = 1000)

Like `chunk()`, but calls `$callback` once per **individual model** instead of once per page, while still fetching records from the database in pages internally (default page size: 1000). The callback receives the model and a running zero-based index:

```php
User::query()->where('active', true)->each(function ($user, $index) {
    // process one $user at a time
});

// Customize the internal page size
User::query()->each(function ($user) {
    // ...
}, 500);
```

Just like `chunk()`, returning `false` from the callback stops processing early.

Both methods automatically respect any `where()`/`join()`/soft-delete scoping already applied to the query, since they're built on the same query builder instance.

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

## Serialization: toJson()

In addition to `toArray()`, models and collections can be converted directly to a JSON string with `toJson()` (Eloquent-style):

```php
$user = User::find(1);
$json = $user->toJson();                 // '{"id":1,"name":"Jane",...}'
$pretty = $user->toJson(JSON_PRETTY_PRINT);

$users = User::query()->where('active', true)->get();
$json = $users->toJson();                 // JSON array of every user
```

- `toJson($options = 0)` internally calls `toArray()` and JSON-encodes the result, so it respects `$fillable`/casts and, importantly, `$hidden`/`$visible` (see [Hidden & Visible Attributes](#hidden--visible-attributes-hidden-and-visible) below) — sensitive columns stay out of the JSON output the same way they stay out of `toArray()`.
- `$options` is passed straight through to PHP's `json_encode()` (e.g. `JSON_PRETTY_PRINT`, `JSON_UNESCAPED_UNICODE`).
- If encoding fails — e.g. an attribute contains malformed UTF-8, or a cast produced a `NAN`/`INF` float — `toJson()` throws a `\JsonException` describing the failure, rather than silently returning `false`. Wrap calls in a `try`/`catch` if you need to handle that case explicitly:

```php
try {
    $json = $user->toJson();
} catch (\JsonException $e) {
    // log / handle the encoding failure
}
```

- Both `Model` and `Collection` also implement `__toString()`, so they can be used directly in string contexts and will produce the same output as `toJson()`:

```php
echo $user;                    // same as echo $user->toJson();
$log = "Created user: {$user}";

echo $users;                   // same as echo $users->toJson();
```

## Mass Assignment Protection: $fillable and $guarded

WPORM protects against unintended mass assignment, just like Eloquent. Use `$fillable` to whitelist attributes that can be set via `fill()`, the constructor, `__set()` (including array access like `$model['name'] = ...`), `updateOrCreate()`, `firstOrCreate()`, or `firstOrNew()`. Use `$guarded` (default: `['id']`) to blacklist attributes instead — anything **not** in `$guarded` is mass-assignable. `$guarded` is only checked when `$fillable` is empty.

```php
class User extends Model {
    protected $fillable = ['name', 'email'];
}

$user = new User(['name' => 'Jane', 'is_admin' => true]);
$user->is_admin; // null — not in $fillable, so it was never set

// Or, blacklist style:
class Post extends Model {
    protected $guarded = ['id', 'is_published']; // everything else is mass-assignable
}

// Block all mass assignment:
class StrictModel extends Model {
    protected $guarded = ['*'];
}
```

> Note: Hydrating a model from a database row (e.g. via `find()`, `get()`, `all()`) always populates every column, regardless of `$fillable`/`$guarded` — these protections only apply to mass assignment of *user-supplied* data.

## Hidden & Visible Attributes: $hidden and $visible

To keep sensitive columns (passwords, tokens, API secrets, etc.) out of `toArray()`/`toJson()` output — and therefore out of API responses or logs — set `$hidden` on your model, Eloquent-style:

```php
class User extends Model {
    protected $fillable = ['name', 'email', 'password'];
    protected $hidden = ['password', 'remember_token'];
}

$user = User::find(1);
$user->toArray(); // 'password' and 'remember_token' are excluded
$user->toJson();  // same — toJson() JSON-encodes the result of toArray()
```

Hidden attributes are still fully accessible on the model object itself (`$user->password` works fine) — they're only stripped when the model is converted to an array or JSON.

You can also use `$visible` as an allow-list instead — only the listed keys will appear in the output:

```php
class User extends Model {
    protected $visible = ['id', 'name', 'email'];
}
```

For one-off overrides on a single instance, use `makeHidden()` / `makeVisible()` (both return `$this` for chaining):

```php
$user = User::find(1); // $hidden = ['password']

$user->makeVisible('password')->toArray(); // reveal it just this once
$user->makeHidden('email')->toArray();     // hide an extra field just this once
```

`Collection::toArray()` / `Collection::toJson()` call each model's own `toArray()`, so `$hidden`/`$visible` are respected automatically for lists of models too:

```php
$users = User::query()->get();
$users->toArray(); // every user in the list has 'password' excluded
```

`$fillable`/`$guarded` and `$hidden`/`$visible` solve two different problems and are meant to be used together for sensitive columns: `$fillable`/`$guarded` control what can be **written** via mass assignment, while `$hidden`/`$visible` control what's **read** back out via serialization.

## Collections

All multi-result queries (`get()`, `all()`, etc.) return a `Collection` instance. Collections provide a fluent, Eloquent-style API for working with arrays of models.

### Available Methods

| Method | Returns | Description |
|---|---|---|
| `all()` | `array` | Get the underlying array of items |
| `first()` | `mixed` | Get the first item |
| `firstOrFail()` | `mixed` | Get the first item, or throw `ModelNotFoundException` if the collection is empty |
| `last()` | `mixed` | Get the last item |
| `count()` | `int` | Number of items |
| `isEmpty()` | `bool` | Whether the collection is empty |
| `toArray()` | `array` | Convert all items to arrays |
| `toJson($options = 0)` | `string` | JSON-encode the collection (via `toArray()`); throws `\JsonException` on encoding failure |
| `__toString()` | `string` | Same output as `toJson()`, for use in string contexts (e.g. `echo $collection;`) |
| `filter(callable)` | `Collection` | Return a new filtered collection |
| `map(callable)` | `Collection` | Return a new collection with transformed items |
| `transform(callable)` | `$this` | Transform items **in-place** (mutating) |
| `pluck($key, $indexKey)` | `array` | Extract a single column from each item |
| `contains($value)` | `bool` | Check if a value exists (strict) |
| `slice($offset, $length)` | `Collection` | Slice the collection |
| `reverse()` | `Collection` | Reverse item order |
| `after($value)` | `Collection` | Items after the first occurrence of a value |

### map() vs transform()

`map()` returns a **new** collection, leaving the original unchanged. `transform()` modifies the collection **in-place** and returns `$this` for chaining — just like Eloquent.

```php
$users = User::query()->where('active', true)->get();

// map() — returns a new collection, original is unchanged
$names = $users->map(function ($user) {
    return $user->name;
});

// transform() — mutates the collection in-place
$users->transform(function ($user) {
    $user->name = strtoupper($user->name);
    return $user;
});
```

### Other Examples

```php
$users = User::query()->where('role', 'admin')->get();

// Filter
$active = $users->filter(function ($user) {
    return $user->active;
});

// Pluck emails
$emails = $users->pluck('email');

// Pluck emails keyed by id
$emailMap = $users->pluck('email', 'id');

// Slice and reverse
$lastFive = $users->slice(-5)->reverse();

// Check existence
if ($users->isEmpty()) {
    // No results
}
```

Collections also implement `ArrayAccess`, `Countable`, and `IteratorAggregate`, so you can use them in `foreach` loops, access items by index (`$users[0]`), and pass them to `count()`.

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
  > `belongsTo()` returns a `QueryBuilder` (just like `hasOne`/`hasMany`), so it is lazy and chainable: `$comment->belongsTo(User::class, 'user_id')->where('active', 1)->first()`. Accessing it as a property (`$comment->user`) automatically resolves it to a single model via `first()`.
- **belongsToMany**: Many-to-many (with optional pivot table and keys)
  ```php
  public function roles() {
      return $this->belongsToMany(Role::class, 'user_role', 'user_id', 'role_id');
  }
  ```
  **Pivot table naming:** If `$pivotTable` is omitted, WPORM follows Eloquent's convention — the
  lowercased, singular basenames of both models, alphabetically sorted, joined with an underscore,
  and automatically prefixed (e.g. `User` + `Role` → `{prefix}role_user`). Pass an explicit pivot
  table name (with or without the prefix) to override this.

  **Join column:** The related table is joined on its own `$primaryKey` (not a hardcoded `id`), so
  this works correctly even if the related model uses a custom primary key.
- **hasManyThrough**: Has-many-through
  ```php
  public function comments() {
      return $this->hasManyThrough(Comment::class, Post::class, 'user_id', 'post_id');
  }
  ```
  **Key convention (matches Eloquent):**
  - `$firstKey` — the foreign key **on the through table** (`Post`) that points back to *this*
    model (`User`). Defaults to `{this_model}_id`, e.g. `user_id`.
  - `$secondKey` — the foreign key **on the related table** (`Comment`) that points to the
    through table (`Post`). Defaults to `{through_model}_id`, e.g. `post_id`.
  - `$localKey` — the primary key on *this* model (`User`), defaults to `$primaryKey`.

  In other words: `User` → (`Post.user_id`) → `Post` → (`Comment.post_id`) → `Comment`.

### Polymorphic Relationships: morphOne, morphMany, morphTo

A polymorphic relationship lets a model belong to more than one other model type using a single association — e.g. a `Comment` that can belong to either a `Post` or a `Video`, or an `Image` that can belong to a `Post` or a `User`. Instead of a single foreign key column, the related table carries **two** columns: a `*_type` column storing the owning model's class (or a short [morph map](#morph-map-short-type-aliases) alias), and a `*_id` column storing its primary key.

- **morphOne**: One-to-one polymorphic, defined on the *owning* model.
  ```php
  // Post owns a single Image via imageable_type / imageable_id
  class Post extends Model {
      public function image() {
          return $this->morphOne(Image::class, 'imageable');
      }
  }
  ```
- **morphMany**: One-to-many polymorphic, defined on the *owning* model.
  ```php
  // Post and Video both own many Comments via commentable_type / commentable_id
  class Post extends Model {
      public function comments() {
          return $this->morphMany(Comment::class, 'commentable');
      }
  }
  class Video extends Model {
      public function comments() {
          return $this->morphMany(Comment::class, 'commentable');
      }
  }
  ```
- **morphTo**: The inverse side, defined on the *related* (child) model. Resolves to whichever model class is actually named in this row's own `*_type` column.
  ```php
  class Comment extends Model {
      protected $fillable = ['commentable_type', 'commentable_id', 'body'];

      public function commentable() {
          return $this->morphTo('commentable');
      }
  }

  $comment = Comment::find(1);
  $owner = $comment->commentable; // a Post or Video instance, depending on commentable_type
  ```
  > Unlike every other relationship method, `morphTo()` requires the morph **name** as its first argument (e.g. `'commentable'`) — PHP has no cheap, reliable way to recover the calling method's own name at runtime, so it can't be inferred automatically the way Eloquent's reflection-based version does.

**Column naming:** By default, `morphOne($related, $name)` / `morphMany($related, $name)` / `morphTo($name)` use `{$name}_type` and `{$name}_id` (e.g. `'imageable'` → `imageable_type` / `imageable_id`). Pass explicit `$type`/`$id` arguments to override either column name:
```php
$this->morphOne(Image::class, 'imageable', 'img_type', 'img_id');
```

**Schema:** Add both columns wherever you store the polymorphic relation — typically a string/varchar `*_type` column and an unsigned-integer `*_id` column, usually indexed together:
```php
public function up(Blueprint $table) {
    $table->id();
    $table->text('body');
    $table->string('commentable_type');
    $table->unsignedBigInteger('commentable_id');
    $table->index(['commentable_type', 'commentable_id']);
}
```

#### Morph Map: Short Type Aliases

By default, the `*_type` column stores the fully-qualified class name (e.g. `App\Models\Post`). Register a `morphMap()` to store a short string instead (e.g. `post`) — this keeps stored values stable even if you rename or move a class later:

```php
use MJ\WPORM\Model;

Model::morphMap([
    'post'  => Post::class,
    'video' => Video::class,
]);
```

Call this once during plugin bootstrap, before any polymorphic relations are queried or saved. Once registered:
- **Writing**: `morphOne()`/`morphMany()` automatically store the alias (`'post'`) instead of the FQCN when building their query, via `getMorphClass()`.
- **Reading**: `morphTo()` automatically resolves the alias back to the real class via `getMorphedModel()`.

`morphMap()` merges into the existing map by default; pass `true` as the second argument to replace it entirely: `Model::morphMap([...], true)`. `Model::getMorphMap()` returns the currently registered map.

> If a `*_type` value doesn't match any registered alias, it's treated as a literal class name automatically (Eloquent's default, un-mapped behavior) — so `morphMap()` is entirely optional and safe to add or skip per-model.

All relationship methods (`hasOne`, `hasMany`, `belongsTo`, `belongsToMany`, `hasManyThrough`, `morphOne`, `morphMany`, `morphTo`) return a lazy, chainable `QueryBuilder` when called directly — e.g. `$user->posts()->where('published', 1)->get()`. When accessed as a property instead (e.g. `$user->posts`, `$comment->user`, `$post->comments`, `$comment->commentable`), WPORM automatically resolves the query for you: `hasOne`/`belongsTo`/`morphOne`/`morphTo`-style relations resolve to a single model (or `null`), and `hasMany`/`belongsToMany`/`hasManyThrough`/`morphMany`-style relations resolve to a `Collection`.

> **Note:** Every relationship method embeds metadata about its type and keys on the returned
> `QueryBuilder` (its "relation context"). This is what powers property-access resolution,
> `with()` eager loading, and `whereHas()`/`has()` — there's no reflection or guesswork involved,
> so eager loading and existence filtering work correctly for all relationship types,
> including `belongsToMany`, `hasManyThrough`, and the polymorphic relations.

### Relationship Existence Filtering: whereHas, orWhereHas, has

- `whereHas('relation', function($q) { ... })`: Filter models where the relation exists and matches constraints.
- `orWhereHas('relation', function($q) { ... })`: OR version of whereHas.
- `has('relation', '>=', 2)`: Filter models with at least (or exactly, or at most) N related records. Operator and count are optional (defaults to ">= 1"). Implemented as a correlated `COUNT(*)` subquery, so the count comparison is enforced precisely (not just existence).

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

// Works for belongsToMany and hasManyThrough too:
User::query()->whereHas('roles', function($q) {
    $q->where('name', 'admin');
})->get();

// Works for morphOne/morphMany too — posts that have at least one comment:
Post::query()->has('comments')->get();
Post::query()->whereHas('comments', function($q) {
    $q->where('approved', 1);
})->get();
```

> **Note on `whereHas()`/`has()` with `morphTo`:** these filter from the "many" side (`morphOne`/`morphMany`, e.g. filtering `Post`s by their `comments()`), which is fully supported. Filtering from the `morphTo` side itself (e.g. `Comment::query()->whereHas('commentable', ...)`) is inherently ambiguous for polymorphic relations — the related table isn't known until each row is read — so, matching Eloquent's own constraints in this area, it resolves against a single row's own currently-loaded type and is best avoided in bulk query construction; eager-load with `with('commentable')` and filter in PHP instead if you need to inspect the resolved related model across many rows.

## Eager Loading: with()

To avoid N+1 query problems, load relations up front with `with()` instead of accessing them lazily per-model. All relationship types (`hasOne`, `hasMany`, `belongsTo`, `belongsToMany`, `hasManyThrough`, `morphOne`, `morphMany`, `morphTo`) are supported.

```php
// Eager load a single relation
$users = User::with('posts')->get();

// Eager load multiple relations
$users = User::with(['posts', 'profile'])->get();

// Works the same on an instance query chain
$users = User::query()->where('active', true)->with('posts')->get();

// And with first()
$user = User::with('posts')->where('id', 1)->first();

// Polymorphic relations work the same way
$posts = Post::with('comments')->get();
$comments = Comment::with('commentable')->get(); // resolves Post/Video per row
```

`with()` runs exactly **one extra query per relation** for `hasOne`/`hasMany`/`belongsTo`/`belongsToMany`/`hasManyThrough`/`morphOne`/`morphMany` (not one per model), regardless of how many parent rows were fetched — it batches all parent keys into a single `WHERE ... IN (...)` (or, for `belongsToMany`/`hasManyThrough`, a single joined query), then distributes results back onto each parent model in memory. `morphTo()` is the one exception: since different rows may point to *different* related model classes, it runs one batched query **per distinct type** present in the result set (still no N+1 — typically just 1–2 extra queries even with mixed types).

### Constraining an eager-loaded relation

Pass a closure to add extra `WHERE` constraints to the relation's query:

```php
$users = User::with(['posts' => function($q) {
    $q->where('published', 1)->orderBy('created_at', 'desc');
}])->get();
```

### Result shape

- `hasOne` / `belongsTo` / `morphOne` / `morphTo` relations resolve to a single model instance (or `null` if none matched).
- `hasMany` / `belongsToMany` / `hasManyThrough` / `morphMany` relations resolve to a `Collection` (empty if none matched).

This applies whether the relation was eager-loaded via `with()` or accessed lazily as a property (e.g. `$user->posts`, `$post->user`).

### Disabling global scopes for an eager-loaded relation

See [Per-relation global-scope control](#per-relation-global-scope-control-eager-loads) below — pass an options array instead of a plain closure to disable global scopes and/or apply a constraint together.

## Model Events and $dispatchesEvents

WPORM provides three complementary ways to respond to model lifecycle events.

### 1. Method overrides (back-compat)

Override `creating()`, `updating()`, `deleting()` (and the soft-delete variants) directly on the model:

```php
class User extends Model {
    protected function creating() {
        $this->name = sanitize_text_field($this->name);
    }
    protected function deleting() {
        // clean up related data
    }
}
```

### 2. $dispatchesEvents (Eloquent-style class mapping)

Map lifecycle event short-names to listener classes. The listener must expose a `handle(\MJ\WPORM\Events\ModelEvent $event)` method.

```php
use MJ\WPORM\Events\Creating;
use MJ\WPORM\Events\Deleted;

class LogUserCreating {
    public function handle(Creating $event): void {
        error_log('Creating user: ' . $event->model->email);
    }
}

class CleanupUserData {
    public function handle(Deleted $event): void {
        wp_delete_user_meta($event->model->id, 'auth_token');
    }
}

class User extends Model {
    protected $fillable = ['name', 'email'];

    public $dispatchesEvents = [
        'creating' => LogUserCreating::class,
        'deleted'  => CleanupUserData::class,
    ];
}
```

**Halting an operation:** Return `false` from any before-hook listener to cancel the operation. `save()`, `delete()`, and `restore()` return `false` when halted.

```php
class ValidateEmail {
    public function handle(Creating $event) {
        if (empty($event->model->email)) {
            return false; // aborts save()
        }
    }
}
```

### 3. Global listeners via EventDispatcher

Register listeners that fire for every model that raises an event, regardless of model class:

```php
use MJ\WPORM\EventDispatcher;
use MJ\WPORM\Events\Creating;
use MJ\WPORM\Events\Saved;

// Closure
EventDispatcher::listen(Creating::class, function(Creating $event) {
    error_log(get_class($event->model) . ' is being created');
});

// Class-string (must have handle() method)
EventDispatcher::listen(Saved::class, \App\Listeners\AuditLog::class);

// Remove listeners
EventDispatcher::forget(Creating::class); // one event
EventDispatcher::forget();                // all events
```

### Supported lifecycle events

| Event class | Key in `$dispatchesEvents` | Fires when |
|---|---|---|
| `Events\Retrieved` | `retrieved` | after fetch from DB |
| `Events\Saving` | `saving` | before INSERT or UPDATE |
| `Events\Saved` | `saved` | after INSERT or UPDATE |
| `Events\Creating` | `creating` | before INSERT |
| `Events\Created` | `created` | after INSERT |
| `Events\Updating` | `updating` | before UPDATE |
| `Events\Updated` | `updated` | after UPDATE |
| `Events\Deleting` | `deleting` | before hard DELETE |
| `Events\Deleted` | `deleted` | after hard DELETE |
| `Events\SoftDeleting` | `softDeleting` | before soft delete |
| `Events\SoftDeleted` | `softDeleted` | after soft delete |
| `Events\Restoring` | `restoring` | before restore |
| `Events\Restored` | `restored` | after restore |

All event objects extend `MJ\WPORM\Events\ModelEvent` and carry `$event->model`, the model instance that fired the event.

See [Methods.md](./Methods.md#dispatchesevents-and-eventdispatcher) for the full API reference.

---

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

WPORM provides an Eloquent-style `DB::transaction()` for safely wrapping multiple database operations in a single atomic transaction — no manual `beginTransaction()` / `commit()` / `rollBack()` calls required.

### DB::transaction(Closure $callback, int $attempts = 1)

The callback is executed inside a transaction. If it returns without throwing, the transaction is committed and the callback's return value is forwarded to the caller. If any exception or error is thrown, the transaction is rolled back and the exception is re-thrown automatically.

```php
use MJ\WPORM\DB;

// Basic usage — commit on success, rollback on any exception
$user = DB::transaction(function() {
    $u = User::create(['name' => 'Alice', 'email' => 'alice@example.com']);
    Profile::create(['user_id' => $u->id, 'bio' => 'Hello!']);
    return $u; // returned value is forwarded to the caller
});

echo $user->id; // the newly created user

// The transaction callback can return any value, or nothing at all
DB::transaction(function() {
    Order::query()->where('status', 'pending')->update(['status' => 'processing']);
    // no return needed for side-effect-only work
});
```

### Automatic Deadlock Retry

Pass a second argument to retry the entire callback automatically on MySQL deadlock (error 1213) or lock-wait timeout (error 1205) — the same behaviour as Laravel's `DB::transaction()`:

```php
// Try up to 3 times before giving up
DB::transaction(function() {
    Inventory::query()->where('product_id', 42)->decrement('stock');
    Order::create(['product_id' => 42, 'qty' => 1]);
}, 3);
```

On any non-retryable exception, or after all retry attempts are exhausted, the last exception is re-thrown to the caller unchanged.

### Also Available on the Query Builder

`transaction()` is available directly on a `QueryBuilder` instance for cases where you already have one:

```php
User::query()->transaction(function() {
    User::create(['name' => 'Bob']);
    // ...
});
```

### Manual Transaction Control

For situations where you need explicit control over the transaction boundary (e.g. across multiple request steps or within a class that manages state), the lower-level methods remain available:

```php
$query = Parts::query();
$query->beginTransaction();
try {
    // ... multiple operations ...
    $query->commit();
} catch (\Throwable $e) {
    $query->rollBack();
    throw $e;
}
```

Prefer `DB::transaction()` over the manual approach — it guarantees the transaction is always cleaned up, even when the callback throws a non-`\Exception` `\Throwable` (e.g. a PHP `Error`).

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

// Plain column aliasing also works: ->select(['user_id as uid', 'email'])

// Selecting all columns from a specific (joined) table with `.*` is also supported:
// ->select(['parts.*', 'products.name as product_name'])

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

## Raw SQL Expressions

When the fluent query builder can't cleanly express what you need — SQL functions, computed columns, vendor-specific syntax — drop down to raw SQL for individual clauses with `selectRaw()`, `whereRaw()`/`orWhereRaw()`, `groupByRaw()`, and `havingRaw()`/`orHavingRaw()` (alongside the existing `orderByRaw()`). Bindings use the same `%s`-style placeholders as the rest of WPORM and are passed straight through to `$wpdb->prepare()`, so they're just as safe as the regular query builder methods — and they can be freely mixed with non-raw calls in the same query.

```php
// selectRaw() — add a raw expression to the SELECT list (combine with select())
$products = Product::query()
    ->select('name')
    ->selectRaw('price * %s as adjusted_price', [1.1])
    ->get();

// whereRaw() / orWhereRaw() — raw WHERE conditions
$orders = Order::query()
    ->whereRaw('YEAR(created_at) = %s AND MONTH(created_at) = %s', [2025, 6])
    ->get();

$products = Product::query()
    ->where('featured', true)
    ->orWhereRaw('price > %s', [1000])
    ->get();

// groupByRaw() — group by a SQL expression instead of a plain column
$dailyTotals = Order::query()
    ->selectRaw('DATE(created_at) as day, SUM(total) as total')
    ->groupByRaw('DATE(created_at)')
    ->get();

// havingRaw() / orHavingRaw() — raw HAVING conditions
$bigSpenders = Order::query()
    ->groupBy('user_id')
    ->havingRaw('SUM(total) > %s', [1000])
    ->get();
```

See [Methods.md](./Methods.md#raw-sql-expressions) for the full list with signatures.

## Subqueries: fromSub(), selectSub(), whereSub() / whereInSub()

WPORM supports Eloquent-style subqueries (subselects and derived tables) in the SELECT, FROM, and WHERE clauses. Every method accepts a `QueryBuilder` instance, a `Closure` that receives a fresh builder, or a raw SQL string. Bindings propagate automatically — you never need to manage them by hand.

### fromSub() — Derived Tables

Use a subquery as the `FROM` table. The derived table is aliased and treated like a real table by all subsequent query builder calls.

```php
// Closure form (inline)
$result = DB::table(function($q) {
    $q->from('orders')
      ->select(['user_id', 'SUM(total) as revenue'])
      ->groupBy('user_id');
}, 'order_totals')
->where('revenue', '>', 500)
->orderBy('revenue', 'desc')
->get();

// QueryBuilder form
$sub = Order::query()
    ->select(['user_id', 'SUM(total) as revenue'])
    ->groupBy('user_id');

$result = DB::table($sub, 'order_totals')
    ->where('revenue', '>', 500)
    ->get();

// On an existing model query
$activeUsers = User::query()
    ->fromSub(function($q) {
        $q->from('users')->where('active', 1)->select('*');
    }, 'active_users')
    ->orderBy('name')
    ->get();
```

### selectSub() — Scalar Subselects

Add a subquery to the SELECT list, aliased as a virtual column on each returned row.

```php
$users = User::query()
    ->select(['id', 'name'])
    ->selectSub(function($q) {
        $q->from('posts')
          ->selectRaw('COUNT(*)')
          ->whereColumn('user_id', 'users.id');
    }, 'post_count')
    ->selectSub(function($q) {
        $q->from('orders')
          ->selectRaw('SUM(total)')
          ->whereColumn('user_id', 'users.id');
    }, 'order_total')
    ->get();

foreach ($users as $user) {
    echo $user->post_count;
    echo $user->order_total;
}
```

### whereSub() / whereInSub() — Subqueries in WHERE

```php
// WHERE id IN (subquery) — shorthand
User::query()->whereInSub('id', function($q) {
    $q->from('role_user')->select('user_id')->where('role_id', 1);
})->get();

// WHERE id NOT IN (subquery)
User::query()->whereNotInSub('id', function($q) {
    $q->from('banned_users')->select('user_id');
})->get();

// WHERE total > (SELECT AVG(total) FROM orders)
Order::query()->whereSub('total', '>', function($q) {
    $q->from('orders')->selectRaw('AVG(total)');
})->get();

// OR variants
User::query()
    ->where('is_superadmin', 1)
    ->orWhereInSub('id', function($q) {
        $q->from('role_user')->select('user_id')->where('role_id', 2);
    })
    ->get();

// Mix with existing QueryBuilder
$adminIds = DB::table('role_user')->select('user_id')->where('role_id', 1);
User::query()->whereInSub('id', $adminIds)->get();
```

All subquery methods (`whereSub`, `orWhereSub`, `whereInSub`, `whereNotInSub`, `orWhereInSub`, `orWhereNotInSub`) fully participate in the same binding-order pipeline as the rest of WPORM — safe to combine with `whereRaw()`, `havingRaw()`, `selectRaw()`, and unions on the same query.

See [Methods.md](./Methods.md#subquery-support) for the full method signatures.

## Combining Queries: union() / unionAll()

WPORM supports Eloquent-style query unions via `union()` and `unionAll()` on the query builder. `union()` combines this query's result set with another query's, removing duplicate rows (SQL `UNION`); `unionAll()` does the same but keeps duplicates (SQL `UNION ALL`). Both accept either an already-built query (your own, or another model's) or a closure that builds the second branch inline against the same model.

```php
// Combine with another already-built query
$highVotes = User::query()->where('votes', '>', 100);
$lowVotes  = User::query()->where('votes', '<', 10);
$users = $highVotes->union($lowVotes)->get();

// Combine using a closure
$users = User::query()
    ->where('votes', '>', 100)
    ->union(function ($query) {
        $query->where('votes', '<', 10);
    })
    ->get();

// Chain as many union()/unionAll() calls as you need
$users = User::query()->where('role', 'admin')
    ->union(User::query()->where('role', 'editor'))
    ->unionAll(User::query()->where('role', 'owner'))
    ->orderBy('name')
    ->get();
```

A few things to know:

- The outer query's own `orderBy()`/`latest()`/`oldest()`/`limit()`/`offset()` (if set) apply to the **combined** result set — not to either branch individually — exactly like Eloquent/Laravel. If a branch itself has its own ordering or limiting, WPORM automatically wraps that branch in parentheses so its ordering/limiting is preserved rather than ambiguously merged into the outer query.
- `get()`, `first()`, `paginate()`/`simplePaginate()`, `count()`, `exists()`/`doesntExist()`, `pluck()`, and the aggregates (`sum()`, `avg()`/`average()`, `min()`, `max()`) all correctly operate on the **combined** result set when unions are present — not just the base query's rows.
- Both sides of a union must select the same number of columns (a SQL requirement). With the closure form, the second branch defaults to the same model's `*` selection, so column counts line up automatically unless you call `select()` inside the closure.
- Soft-delete scoping is applied independently to the outer query and to every union branch, just as if you had called `get()` on each one separately.
- `union()`/`unionAll()` apply to read queries only; they have no effect on `update()`/`delete()`.

See [Methods.md](./Methods.md#combining-queries) for the full method signatures.

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
> Note: `where()`/`orWhere()` detect nested groups via `instanceof \Closure`, so column names that happen to match PHP function names (e.g. `trim`, `count`, `date`) are treated as plain column names, not as closures — `->where('count', 5)` works exactly as expected.
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
- **Schema Changes:** Your model's `up(Blueprint $blueprint)` method is the single source of truth for the table schema — WPORM reads it via `$blueprint->toSql()` automatically, so you no longer need to assign `$this->schema` yourself. If you change `up()`, you may need to drop and recreate the table or use the `SchemaBuilder`'s `table()` method for migrations.
- **Reusing a Query Builder:** It's safe to call `toSql()`, `count()`, `get()`, etc. multiple times (or in combination, as `paginate()` does internally) on the same query instance — soft-delete constraints and HAVING bindings are only applied once per instance and won't duplicate or misalign bindings on repeat calls.
- **Constructing Models:** `new Model(['id' => 5])` (or any attributes) only fills the model's attributes in memory — it does **not** query the database. Use `Model::find($id)` to load an existing record.
- **Events:** WPORM supports three complementary event approaches. (1) Override `creating()`, `updating()`, `deleting()` etc. directly on the model. (2) Use `$dispatchesEvents` to map event names to listener classes — the listener must expose a `handle(\MJ\WPORM\Events\ModelEvent $event)` method. (3) Register global listeners via `EventDispatcher::listen(EventClass::class, $listener)` to respond to any model's events. All three fire in that order per event. Any before-hook listener can cancel an operation by returning `false`. See [Methods.md]($dispatchesEvents-and-eventdispatcher) for full API.
- **Extending Casts:** Implement `MJ\WPORM\Casts\CastableInterface` for custom attribute casting logic.
- **Testing:** Always test your queries and schema changes on a staging environment before deploying to production.

## Contributing

Contributions, bug reports, and feature requests are welcome! Please open an issue or submit a pull request.

## Credits

WPORM is inspired by Laravel's Eloquent ORM and adapted for the WordPress ecosystem.

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
- A: Ensure your model's `up(Blueprint $blueprint)` method correctly builds the columns on the `$blueprint` argument (WPORM reads the schema from it automatically). Check for errors in your column definitions, and check `$wpdb->last_error` for SQL errors.

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
