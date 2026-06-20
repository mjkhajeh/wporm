# WPORM Model Methods Documentation

This document describes all public and static methods of the `MJ\WPORM\Model` class, with a brief description and a simple usage example for each.

---

## Table of Contents
- [Global Scopes](#global-scopes)
- [Constructor](#constructor)
- [Query Methods](#query-methods)
- [Retrieval Methods](#retrieval-methods)
- [Aggregates & Utility Methods](#aggregates--utility-methods)
- [Persistence Methods](#persistence-methods)
- [Relationship Methods](#relationship-methods)
- [Relationship Existence Filtering](#relationship-existence-filtering)
- [Eager Loading](#eager-loading)
- [Utility Methods](#utility-methods)
- [Mass Assignment Protection](#mass-assignment-protection)
- [Hidden & Visible Attributes](#hidden--visible-attributes)
- [JSON Where Clauses](#json-where-clauses)
- [Raw Table Queries with DB::table()](#raw-table-queries-with-dbtable)
- [Pagination](#pagination)
- [Soft Deletes](#soft-deletes)

---

## Global Scopes

### addGlobalScope($identifier, callable $scope)
**Description:** Register a global scope (closure) to be applied to all queries for this model.

**Example:**
```php
User::addGlobalScope('active', function($query) {
    $query->where('active', 1);
});
```

### removeGlobalScope($identifier)
**Description:** Remove a global scope by its identifier.

**Example:**
```php
User::removeGlobalScope('active');
```

### getGlobalScopes()
**Description:** Get all global scopes registered for this model.

**Example:**
```php
$scopes = User::getGlobalScopes();
```

### applyGlobalScopes(QueryBuilder $query)
**Description:** Apply all global scopes to a query builder instance.

**Example:**
```php
$query = User::applyGlobalScopes(new QueryBuilder(new User));
```

---

## Constructor

### __construct(array $attributes = [])
**Description:** Create a new model instance and fill it with the given attributes. This never queries the database — even if the primary key is present in `$attributes` — it only sets attributes in memory. Use `Model::find($id)` to load an existing record from the database. (The constructor does ensure the model's table exists, building it from `up(Blueprint $blueprint)` on first use per table.)

**Example:**
```php
// Does NOT query the database — just an in-memory instance with id pre-filled
$user = new User(['id' => 1]);

// To actually load the record from the database, use find():
$user = User::find(1);
```

---

## Query Methods

### query()
**Description:** Get a new query builder for the model, with global scopes applied.

**Example:**
```php
$users = User::query()->where('role', 'admin')->get();
```

### newQuery()
**Description:** Alias for `query()`. Returns a new query builder with global scopes.

**Example:**
```php
$query = User::newQuery();
```

### whereIn($column, array $values)
**Description:** Add a WHERE ... IN (...) clause to the query.

**Example:**
```php
$users = User::query()->whereIn('status', ['active', 'pending'])->get();
```

### whereNotIn($column, array $values)
**Description:** Add a WHERE ... NOT IN (...) clause to the query.

**Example:**
```php
$users = User::query()->whereNotIn('status', ['banned', 'deleted'])->get();
```

### orWhereIn($column, array $values)
**Description:** Add an OR WHERE ... IN (...) clause to the query.

**Example:**
```php
$users = User::query()->where('role', 'admin')->orWhereIn('status', ['active', 'pending'])->get();
```

### orWhereNotIn($column, array $values)
**Description:** Add an OR WHERE ... NOT IN (...) clause to the query.

**Example:**
```php
$users = User::query()->where('role', 'admin')->orWhereNotIn('status', ['banned', 'deleted'])->get();
```

### whereLike($column, $value)
**Description:** Add a WHERE ... LIKE ... clause to the query.

**Example:**
```php
$users = User::query()->whereLike('name', '%john%')->get();
```

### orWhereLike($column, $value)
**Description:** Add an OR WHERE ... LIKE ... clause to the query.

**Example:**
```php
$users = User::query()->where('role', 'admin')->orWhereLike('name', '%john%')->get();
```

### whereNotLike($column, $value)
**Description:** Add a WHERE ... NOT LIKE ... clause to the query.

**Example:**
```php
$users = User::query()->whereNotLike('name', '%test%')->get();
```

### orWhereNotLike($column, $value)
**Description:** Add an OR WHERE ... NOT LIKE ... clause to the query.

**Example:**
```php
$users = User::query()->where('role', 'admin')->orWhereNotLike('name', '%test%')->get();
```

### whereNot($column, $operator = null, $value = null)
**Description:** Add a WHERE ... NOT ... clause to the query (e.g., WHERE column NOT = value).

**Example:**
```php
$users = User::query()->whereNot('status', 'active')->get();
$users = User::query()->whereNot('age', '>=', 18)->get();
```

### orWhereNot($column, $operator = null, $value = null)
**Description:** Add an OR WHERE ... NOT ... clause to the query (e.g., OR column NOT = value).

**Example:**
```php
$users = User::query()->where('role', 'admin')->orWhereNot('status', 'active')->get();
$users = User::query()->orWhereNot('age', '<', 18)->get();
```

### whereAny(array $conditions)
**Description:** Add a group of OR conditions (any must match) to the query. Equivalent to Eloquent's whereAny.

**Example:**
```php
$users = User::query()->whereAny([
    ['status', 'active'],
    ['role', 'admin'],
])->get();
```

### orWhereAny(array $conditions)
**Description:** Add a group of OR conditions (any must match) as an OR clause to the query.

**Example:**
```php
$users = User::query()->where('country', 'US')->orWhereAny([
    ['status', 'active'],
    ['role', 'admin'],
])->get();
```

### whereAll(array $conditions)
**Description:** Add a group of AND conditions (all must match) to the query. Equivalent to Eloquent's whereAll.

**Example:**
```php
$users = User::query()->whereAll([
    ['status', 'active'],
    ['role', 'admin'],
])->get();
```

### orWhereAll(array $conditions)
**Description:** Add a group of AND conditions (all must match) as an OR clause to the query.

**Example:**
```php
$users = User::query()->where('country', 'US')->orWhereAll([
    ['status', 'active'],
    ['role', 'admin'],
])->get();
```

### whereNone(array $conditions)
**Description:** Add a group of OR conditions, none of which must match (NOT (cond1 OR cond2 ...)). Equivalent to Eloquent's whereNone.

**Example:**
```php
$users = User::query()->whereNone([
    ['status', 'banned'],
    ['role', 'guest'],
])->get();
```

### orWhereNone(array $conditions)
**Description:** Add a group of OR conditions, none of which must match, as an OR clause (OR NOT (...)).

**Example:**
```php
$users = User::query()->where('country', 'US')->orWhereNone([
    ['status', 'banned'],
    ['role', 'guest'],
])->get();
```

### join($table, $first = null, $operator = null, $second = null, $type = 'INNER')
**Description:** Add an INNER JOIN clause to the query. Supports closure for advanced ON conditions.

**Example:**
```php
// Simple join
$users = User::query()
    ->join('profiles', 'users.id', '=', 'profiles.user_id')
    ->get();

// Join with closure for complex ON
$users = User::query()
    ->join('profiles', function($join) {
        $join->where('profiles.active', 1);
    })
    ->get();
```

### leftJoin($table, $first = null, $operator = null, $second = null)
**Description:** Add a LEFT JOIN clause to the query.

**Example:**
```php
$users = User::query()
    ->leftJoin('profiles', 'users.id', '=', 'profiles.user_id')
    ->get();
```

### rightJoin($table, $first = null, $operator = null, $second = null)
**Description:** Add a RIGHT JOIN clause to the query.

**Example:**
```php
$users = User::query()
    ->rightJoin('profiles', 'users.id', '=', 'profiles.user_id')
    ->get();
```

### crossJoin($table)
**Description:** Add a CROSS JOIN clause to the query (cartesian product).

**Example:**
```php
$sizes = DB::table('sizes')
    ->crossJoin('colors')
    ->get();
```

### latest($column = 'created_at')
**Description:** Order the results by the given column in descending order (default: 'created_at').

**Example:**
```php
$users = User::query()->latest()->get();
$users = User::query()->latest('id')->get();
```

### oldest($column = 'created_at')
**Description:** Order the results by the given column in ascending order (default: 'created_at').

**Example:**
```php
$users = User::query()->oldest()->get();
$users = User::query()->oldest('id')->get();
```

### inRandomOrder()
**Description:** Order the results randomly (ORDER BY RAND()).

**Example:**
```php
$users = User::query()->inRandomOrder()->get();
```

### orderByRaw($sql, array $bindings = [])
**Description:** Add a raw SQL ORDER BY clause with optional bindings. Useful for custom sorting or SQL functions.

**Example:**
```php
$products = Product::query()->orderByRaw('FIELD(name, ?, ?)', ['Widget', 'Gadget'])->get();
```

### reorder()
**Description:** Remove all previous order by clauses from the query.

**Example:**
```php
$query = User::query()->orderBy('name');
$unorderedUsers = $query->reorder()->get();
```

### groupBy($columns)
**Description:** Add GROUP BY clause(s) to the query. Accepts a string, array, or multiple arguments.

**Example:**
```php
$users = User::query()->groupBy('country')->get();
$users = User::query()->groupBy(['country', 'status'])->get();
$users = User::query()->groupBy('country', 'status')->get();
```

### having($column, $operator = null, $value = null)
**Description:** Add a HAVING clause to the query. Usage is similar to where().

**Example:**
```php
$users = User::query()
    ->groupBy('country')
    ->having('count(*)', '>', 10)
    ->get();
```

### havingBetween($column, array $values)
**Description:** Add a HAVING ... BETWEEN ... AND ... clause to the query.

**Example:**
```php
$users = User::query()
    ->groupBy('country')
    ->havingBetween('count(*)', [5, 20])
    ->get();
```

### whereBetween($column, array $values)
Add a WHERE ... BETWEEN ... AND ... clause to the query.

**Usage:**
```php
Model::query()->whereBetween('price', [100, 200])->get();
```

---

### orWhereBetween($column, array $values)
Add an OR ... BETWEEN ... AND ... clause to the query.

**Usage:**
```php
Model::query()->orWhereBetween('created_at', ['2024-01-01', '2024-12-31'])->get();
```

---

### whereNotBetween($column, array $values)
Add a WHERE ... NOT BETWEEN ... AND ... clause to the query.

**Usage:**
```php
Model::query()->whereNotBetween('price', [100, 200])->get();
```

---

### orWhereNotBetween($column, array $values)
Add an OR ... NOT BETWEEN ... AND ... clause to the query.

**Usage:**
```php
Model::query()->orWhereNotBetween('created_at', ['2024-01-01', '2024-12-31'])->get();
```

---

### whereBetweenColumns($column, array $columns)
Add a WHERE ... BETWEEN column1 AND column2 clause to the query, using column names for the range (Eloquent-style).

**Usage:**
```php
Model::query()->whereBetweenColumns('score', ['min_score', 'max_score'])->get();
```

---

### orWhereBetweenColumns($column, array $columns)
Add an OR ... BETWEEN column1 AND column2 clause to the query, using column names for the range.

**Usage:**
```php
Model::query()->orWhereBetweenColumns('created_at', ['start_date', 'end_date'])->get();
```

---

### whereNotBetweenColumns($column, array $columns)
Add a WHERE ... NOT BETWEEN column1 AND column2 clause to the query, using column names for the range.

**Usage:**
```php
Model::query()->whereNotBetweenColumns('score', ['min_score', 'max_score'])->get();
```

---

### orWhereNotBetweenColumns($column, array $columns)
Add an OR ... NOT BETWEEN column1 AND column2 clause to the query, using column names for the range.

**Usage:**
```php
Model::query()->orWhereNotBetweenColumns('created_at', ['start_date', 'end_date'])->get();
```

---

### whereNull($column)
Add a WHERE ... IS NULL clause to the query.

**Usage:**
```php
Model::query()->whereNull('deleted_at')->get();
```

---

### orWhereNull($column)
Add an OR ... IS NULL clause to the query.

**Usage:**
```php
Model::query()->where('status', 'active')->orWhereNull('deleted_at')->get();
```

---

### whereNotNull($column)
Add a WHERE ... IS NOT NULL clause to the query.

**Usage:**
```php
Model::query()->whereNotNull('email_verified_at')->get();
```

---

### orWhereNotNull($column)
Add an OR ... IS NOT NULL clause to the query.

**Usage:**
```php
Model::query()->where('status', 'active')->orWhereNotNull('email_verified_at')->get();
```

---

### whereDate($column, $value)
Add a WHERE DATE(column) = value clause to the query.

**Usage:**
```php
Model::query()->whereDate('created_at', '2025-06-10')->get();
```

---

### whereMonth($column, $value)
Add a WHERE MONTH(column) = value clause to the query.

**Usage:**
```php
Model::query()->whereMonth('created_at', 6)->get();
```

---

### whereDay($column, $value)
Add a WHERE DAY(column) = value clause to the query.

**Usage:**
```php
Model::query()->whereDay('created_at', 10)->get();
```

---

### whereYear($column, $value)
Add a WHERE YEAR(column) = value clause to the query.

**Usage:**
```php
Model::query()->whereYear('created_at', 2025)->get();
```

---

### whereTime($column, $value)
Add a WHERE TIME(column) = value clause to the query.

**Usage:**
```php
Model::query()->whereTime('created_at', '14:00:00')->get();
```

---

### wherePast($column)
Add a WHERE column < CURDATE() clause to the query (date is in the past).

**Usage:**
```php
Model::query()->wherePast('created_at')->get();
```

---

### whereFuture($column)
Add a WHERE column > CURDATE() clause to the query (date is in the future).

**Usage:**
```php
Model::query()->whereFuture('expires_at')->get();
```

---

### whereToday($column)
Add a WHERE DATE(column) = CURDATE() clause to the query (date is today).

**Usage:**
```php
Model::query()->whereToday('created_at')->get();
```

---

### whereBeforeToday($column)
Add a WHERE DATE(column) < CURDATE() clause to the query (date is before today).

**Usage:**
```php
Model::query()->whereBeforeToday('created_at')->get();
```

---

### whereAfterToday($column)
Add a WHERE DATE(column) > CURDATE() clause to the query (date is after today).

**Usage:**
```php
Model::query()->whereAfterToday('created_at')->get();
```

---

### whereColumn($first, $operator, $second = null)
Add a WHERE first_column operator second_column clause to the query (column-to-column comparison, Eloquent-style). If only two arguments are given, operator defaults to '='.

**Usage:**
```php
Model::query()->whereColumn('start_date', '<', 'end_date')->get();
Model::query()->whereColumn('price', 'cost')->get(); // Defaults to '='
```

---

### orWhereColumn($first, $operator, $second = null)
Add an OR first_column operator second_column clause to the query (column-to-column comparison, Eloquent-style). If only two arguments are given, operator defaults to '='.

**Usage:**
```php
Model::query()->where('status', 'active')->orWhereColumn('price', '>', 'cost')->get();
Model::query()->orWhereColumn('price', 'cost')->get(); // Defaults to '='
```

---

### whereExists(Closure $callback)
Add a WHERE EXISTS (subquery) clause to the query. The callback receives a subquery builder.

**Usage:**
```php
Model::query()->whereExists(function($q) {
    $q->where('status', 'active');
})->get();
```

---

### orWhereExists(Closure $callback)
Add an OR EXISTS (subquery) clause to the query. The callback receives a subquery builder.

**Usage:**
```php
Model::query()->where('type', 'user')->orWhereExists(function($q) {
    $q->where('status', 'active');
})->get();
```

---

### whereNotExists(Closure $callback)
Add a WHERE NOT EXISTS (subquery) clause to the query. The callback receives a subquery builder.

**Usage:**
```php
Model::query()->whereNotExists(function($q) {
    $q->where('deleted_at', '!=', null);
})->get();
```

---

### orWhereNotExists(Closure $callback)
Add an OR NOT EXISTS (subquery) clause to the query. The callback receives a subquery builder.

**Usage:**
```php
Model::query()->where('type', 'user')->orWhereNotExists(function($q) {
    $q->where('deleted_at', '!=', null);
})->get();
```

---

## Retrieval Methods

### all()
**Description:** Retrieve all records for the model. Triggers `retrieved()` event on each instance.

**Example:**
```php
$users = User::all();
```

### find($id)
**Description:** Find a record by primary key. Triggers `retrieved()` event.

**Example:**
```php
$user = User::find(1);
```

### getWithEvent($query)
**Description:** Get results from a query and trigger `retrieved()` on each instance.

**Example:**
```php
$admins = User::getWithEvent(User::query()->where('role', 'admin'));
```

### firstWithEvent($query)
**Description:** Get the first result from a query and trigger `retrieved()`.

**Example:**
```php
$admin = User::firstWithEvent(User::query()->where('role', 'admin'));
```

### updateOrCreate(array $attributes, array $values = [])
**Description:** Find a record matching attributes, update it or create a new one.

**Example:**
```php
$user = User::updateOrCreate(['email' => 'foo@bar.com'], ['name' => 'Foo']);
```

### firstOrCreate(array $attributes, array $values = [])
**Description:** Return the first record matching attributes or create it.

**Example:**
```php
$user = User::firstOrCreate(['email' => 'foo@bar.com'], ['name' => 'Foo']);
```

### firstOrNew(array $attributes, array $values = [])
**Description:** Return the first record matching attributes or instantiate a new one (not saved).

**Example:**
```php
$user = User::firstOrNew(['email' => 'foo@bar.com'], ['name' => 'Foo']);
```

### insertOrIgnore(array $attributes)
**Description:** Insert one or multiple records, ignoring duplicate key errors (e.g., unique constraint violations). Returns true if insert(s) succeeded or were ignored, false on other errors.

**Examples:**
```php
// Single record
$success = User::insertOrIgnore([
    'email' => 'foo@bar.com',
    'name' => 'Foo'
]);

// Multiple records
$data = [
    ['email' => 'user1@example.com', 'name' => 'User One'],
    ['email' => 'user2@example.com', 'name' => 'User Two'],
    ['email' => 'user1@example.com', 'name' => 'User One Duplicate'], // duplicate email
];
$success = User::insertOrIgnore($data);
```

---

### upsert(array $values, array|string $uniqueBy, array|null $update = null)
**Description:** Insert or update multiple records in a single query (Eloquent-style). Uses MySQL `INSERT ... ON DUPLICATE KEY UPDATE` syntax. If a record with the same unique key(s) already exists, the specified columns are updated; otherwise, a new record is inserted.

**Parameters:**
- `$values` — Array of records to upsert (each record is an associative array).
- `$uniqueBy` — Column(s) that uniquely identify records (e.g., `['email']` or `'email'`).
- `$update` — (Optional) Columns to update when a duplicate is found. If `null`, all columns except `$uniqueBy` are updated.

**Returns:** Number of affected rows or `false` on failure.

**Examples:**
```php
// Upsert with explicit update columns
User::upsert([
    ['email' => 'alice@test.com', 'name' => 'Alice', 'votes' => 1],
    ['email' => 'bob@test.com', 'name' => 'Bob', 'votes' => 2],
], ['email'], ['name', 'votes']);

// Upsert all columns except unique key (auto-detected)
User::upsert([
    ['email' => 'alice@test.com', 'name' => 'Alice Updated', 'votes' => 10],
], 'email');

// Single record upsert
User::upsert(
    ['email' => 'alice@test.com', 'name' => 'Alice', 'votes' => 5],
    ['email'],
    ['votes']
);

// Using DB::table() (raw table query)
DB::table('users')->upsert([
    ['email' => 'alice@test.com', 'name' => 'Alice', 'votes' => 1],
    ['email' => 'bob@test.com', 'name' => 'Bob', 'votes' => 2],
], ['email'], ['name', 'votes']);
```

**Notes:**
- If timestamps are enabled on the model, `created_at` and `updated_at` are handled automatically.
- The `$uniqueBy` columns must have a unique or primary key constraint in the database for the ON DUPLICATE KEY behavior to work.
- If `$update` is empty (no columns to update), falls back to `INSERT IGNORE` behavior.

---

## Aggregates & Utility Methods

These query builder methods cover the most common aggregate/lookup needs (Eloquent-style) without requiring a full `get()`/`toArray()` round-trip.

### sum($column)
**Description:** Get the sum of a column's values across all rows matching the query. Returns `0` if no rows matched.

**Example:**
```php
$total = Order::query()->where('status', 'paid')->sum('total');
```

### avg($column) / average($column)
**Description:** Get the average of a column's values across all rows matching the query. Returns `null` if no rows matched. `average()` is an alias for `avg()`.

**Example:**
```php
$avgPrice = Product::query()->avg('price');
$avgPrice = Product::query()->average('price'); // same thing
```

### min($column)
**Description:** Get the minimum value of a column across all rows matching the query.

**Example:**
```php
$cheapest = Product::query()->min('price');
```

### max($column)
**Description:** Get the maximum value of a column across all rows matching the query.

**Example:**
```php
$mostExpensive = Product::query()->max('price');
```

### value($column)
**Description:** Get a single column's value from the first row matching the query — useful when you only need one field and don't want to hydrate (or look at) the rest of the model.

**Example:**
```php
$email = User::query()->where('id', 1)->value('email');
```

### pluck($column, $key = null)
**Description:** Get a flat array of a single column's values across all matching rows, without hydrating full models. If `$key` is provided, returns an associative array keyed by that column instead.

**Example:**
```php
$emails = User::query()->pluck('email');
$emailsById = User::query()->pluck('email', 'id'); // [1 => 'a@test.com', 2 => 'b@test.com', ...]
```
> Note: This is the query-builder-level `pluck()`, which queries only the requested column(s) directly from the database. `Collection::pluck()` (see [Collections](./Readme.md#collections)) operates on an already-fetched collection of hydrated models instead.

### exists()
**Description:** Determine whether any rows match the current query. More efficient than `count() > 0` or checking `first()` since it short-circuits with `LIMIT 1`.

**Example:**
```php
if (User::query()->where('email', $email)->exists()) {
    // Email is already taken
}
```

### doesntExist()
**Description:** Inverse of `exists()` — returns `true` if no rows match the current query.

**Example:**
```php
if (User::query()->where('email', $email)->doesntExist()) {
    // Safe to create a new user with this email
}
```

### increment($column, $amount = 1, array $extra = [])
**Description:** Increment a column's value. Available both as a **query builder** method (affects every row matching the current query, in a single atomic `UPDATE ... SET col = col + amount` statement) and as an **instance** method on a model (affects only that model's row, scoped automatically by its primary key, and syncs the new value onto the in-memory model). An optional `$extra` array of additional `column => value` pairs can be set in the same query (e.g. to bump a `last_voted_at` timestamp alongside the counter). If the model uses timestamps, `updated_at` is touched automatically unless you supply it yourself in `$extra`.

**Examples:**
```php
// Instance usage — increments votes for this one user only
$user = User::find(1);
$user->increment('votes');
$user->increment('votes', 5);
$user->increment('votes', 1, ['last_voted_at' => current_time('mysql')]);

// Query builder usage — increments votes for every matching row
User::query()->where('active', true)->increment('votes');
User::query()->where('role', 'admin')->increment('credits', 10);
```

### decrement($column, $amount = 1, array $extra = [])
**Description:** Decrement a column's value. Same usage and semantics as `increment()`, just subtracting instead of adding.

**Examples:**
```php
$user = User::find(1);
$user->decrement('credits');
$user->decrement('credits', 3);

User::query()->where('subscription', 'expired')->decrement('seats', 1);
```

---

## Persistence Methods

### save()
**Description:** Save the model to the database (insert or update).

**Example:**
```php
$user = new User(['name' => 'Bar']);
$user->save();
```

### delete()
**Description:** Delete the model from the database.

**Example:**
```php
$user = User::find(1);
$user->delete();
```

### truncate()
**Description:** Truncate the model's table. Executes a `TRUNCATE TABLE` statement for the model's underlying table and removes all records quickly. Use with caution.

**Example:**
```php
// Truncate all records for the model's table
User::query()->truncate();
```

---

## Relationship Methods

### hasOne($related, $foreignKey = null, $localKey = null)
**Description:** Define a one-to-one relationship. Returns a lazy, chainable `QueryBuilder` — call `->first()` to resolve it, or access it as a property (e.g. `$user->profile`) to have it resolved to a single model (or `null`) automatically.

**Example:**
```php
$profile = $user->hasOne(Profile::class)->first();
$profile = $user->profile; // equivalent, via property access
```

### hasMany($related, $foreignKey = null, $localKey = null)
**Description:** Define a one-to-many relationship. Returns a lazy, chainable `QueryBuilder` — call `->get()` to resolve it, or access it as a property (e.g. `$user->posts`) to have it resolved to a `Collection` automatically.

**Example:**
```php
$posts = $user->hasMany(Post::class)->get();
$posts = $user->posts; // equivalent, via property access
```

### belongsTo($related, $foreignKey = null, $ownerKey = null)
**Description:** Define an inverse one-to-one or many relationship. Like `hasOne`/`hasMany`, this returns a lazy, chainable `QueryBuilder` rather than eagerly executing the query — call `->first()` (or further chain `->where(...)` etc.) to resolve it.

**Example:**
```php
$user = $profile->belongsTo(User::class)->first();
```

### belongsToMany($related, $pivotTable = null, $foreignPivotKey = null, $relatedPivotKey = null)
**Description:** Define a many-to-many relationship via a pivot table.

**Parameters:**
- `$pivotTable` — (Optional) Name of the pivot table. If omitted, follows Eloquent's convention: the lowercased, singular basenames of both models, alphabetically sorted and joined with an underscore (e.g. `User` + `Role` → `role_user`), automatically prefixed with the WordPress table prefix. A bare name you pass in is also auto-prefixed if needed.
- `$foreignPivotKey` — (Optional) FK column on the pivot table referencing *this* model. Defaults to `{this_model}_id`.
- `$relatedPivotKey` — (Optional) FK column on the pivot table referencing the *related* model. Defaults to `{related_model}_id`.

The related table is joined on its own `$primaryKey`, so this also works with non-`id` primary keys.

**Example:**
```php
$roles = $user->belongsToMany(Role::class);
// Equivalent to: $user->belongsToMany(Role::class, 'role_user', 'user_id', 'role_id');

// With an explicit pivot table and keys:
$roles = $user->belongsToMany(Role::class, 'user_role', 'user_id', 'role_id');
```

### hasManyThrough($related, $through, $firstKey = null, $secondKey = null, $localKey = null)
**Description:** Define a has-many-through relationship — access a distant relation through an intermediate ("through") model.

**Parameters (matching Eloquent's convention):**
- `$firstKey` — FK **on the through table** that points back to *this* model. Defaults to `{this_model}_id`.
- `$secondKey` — FK **on the related table** that points to the through table. Defaults to `{through_model}_id`.
- `$localKey` — PK on *this* model. Defaults to `$primaryKey`.

**Example:**
```php
// Country hasManyThrough Post, through User:
// users.country_id   -> $firstKey  (FK on the through table -> Country)
// posts.user_id       -> $secondKey (FK on the related table -> User)
public function posts() {
    return $this->hasManyThrough(Post::class, User::class, 'country_id', 'user_id');
}

$comments = $user->hasManyThrough(Comment::class, Post::class, 'user_id', 'post_id');
// posts.user_id     -> $firstKey  (FK on Post, the through table, -> User)
// comments.post_id  -> $secondKey (FK on Comment, the related table, -> Post)
```

---

## Relationship Existence Filtering

### whereHas($relation, $constraint = null)
- Filter models where the given relation exists and matches the constraint closure.
- Supported for all relationship types: `hasOne`, `hasMany`, `belongsTo`, `belongsToMany`, `hasManyThrough`.
- Example: `$query->whereHas('posts', function($q) { $q->where('published', 1); })`

### orWhereHas($relation, $constraint = null)
- OR version of whereHas.

### has($relation, $operator = '>=', $count = 1)
- Filter models with a number of related records matching the operator and count.
- Implemented as a correlated `COUNT(*)` subquery, so the comparison is enforced exactly (not just an existence check).
- Example: `$query->has('posts', '>=', 5)`
- Operator and count are optional (defaults to ">= 1").

---

## Eager Loading

### with($relations)
**Description:** Eager-load one or more relations alongside the main query, avoiding N+1 queries. Accepts a relation name, an array of relation names, or an associative array mapping relation names to constraint closures (or an options array — see below). Works for all five relationship types (`hasOne`, `hasMany`, `belongsTo`, `belongsToMany`, `hasManyThrough`) and runs exactly one extra query per relation, regardless of how many parent rows were loaded.

**Example:**
```php
$users = User::with('posts')->get();
$users = User::with(['posts', 'profile'])->get();
$users = User::with(['posts' => function($q) {
    $q->where('published', 1);
}])->get();
```

- `hasOne`/`belongsTo` relations resolve to a single model (or `null`).
- `hasMany`/`belongsToMany`/`hasManyThrough` relations resolve to a `Collection`.

See [Per-relation global-scope control](./Readme.md#per-relation-global-scope-control-eager-loads) in the Readme for disabling global scopes on a specific eager-loaded relation.

---

## Utility Methods

### fill(array $attributes)
**Description:** Fill the model with an array of attributes.

**Example:**
```php
$user->fill(['name' => 'Baz']);
```

### toArray()
- Converts a model or collection to an array, applying all casts.
- Built-in types (int, bool, float, json, etc.) are handled natively.
- Custom cast classes must implement `MJ\WPORM\Casts\CastableInterface`.
- Respects `$hidden`/`$visible` (and any runtime `makeHidden()`/`makeVisible()` overrides) — see [Hidden & Visible Attributes](#hidden--visible-attributes) below.

**Example:**
```php
$array = $user->toArray();
```

### toJson($options = 0)
**Description:** Convert the model to a JSON string. Internally calls `toArray()`, so it respects `$hidden`/`$visible` the same way. `$options` is passed straight through to `json_encode()` (e.g. `JSON_PRETTY_PRINT`).

**Example:**
```php
$json = $user->toJson();
$pretty = $user->toJson(JSON_PRETTY_PRINT);
```

> `Collection` also has a `toJson()` method that JSON-encodes the result of `Collection::toArray()`, so hidden attributes stay hidden for lists of models too.

### getOriginal($key = null)
**Description:** Get the original value(s) of the model's attributes.

**Example:**
```php
$original = $user->getOriginal();
```

### isDirty($attribute = null)
**Description:** Determine if the model or a given attribute has been modified.

**Example:**
```php
if ($user->isDirty('name')) { /* ... */ }
```

### getChanges()
**Description:** Get the changed attributes of the model.

**Example:**
```php
$changes = $user->getChanges();
```

### forgetAttribute($key)
**Description:** Remove an internal/transient attribute from the model so it no longer appears on the model or in `toArray()`/`toJson()` output. Mainly used internally by `with()` eager loading to strip bookkeeping columns (e.g. a pivot-table foreign key selected only to group `belongsToMany`/`hasManyThrough` results back onto their parent models), but available for any case where you've added a transient/computed attribute and want to discard it before serializing. Safe to call even if the key was never set. Returns `$this` for chaining.

**Example:**
```php
$user->forgetAttribute('_pivot_fk');
```

---

## Mass Assignment Protection

WPORM guards against unintended mass assignment, just like Eloquent, via `$fillable` and `$guarded` on the model. These are enforced by `isFillableAttribute()` for **every** mass-assignment path: `fill()`, `__set()` (and therefore array access like `$user['name'] = ...`), `new Model([...])`, `updateOrCreate()`, `firstOrCreate()`, and `firstOrNew()`.

### $fillable
**Description:** A whitelist of attribute names that may be mass-assigned. If `$fillable` is non-empty, only the listed keys can be set via `fill()`/constructor/`__set()`; everything else is silently ignored.

**Example:**
```php
class User extends Model {
    protected $fillable = ['name', 'email'];
}

$user = new User(['name' => 'Jane', 'email' => 'jane@example.com', 'is_admin' => true]);
$user->is_admin; // null — 'is_admin' was not in $fillable, so it was never set
```

### $guarded
**Description:** A blacklist of attribute names that may **not** be mass-assigned (default: `['id']`). Use `['*']` to block all mass assignment (in which case attributes must be set individually, e.g. `$user->name = 'Jane';`). `$guarded` is only consulted when `$fillable` is empty.

**Example:**
```php
class User extends Model {
    protected $guarded = ['id', 'is_admin']; // everything else is mass-assignable
}

$user = new User(['name' => 'Jane', 'is_admin' => true]);
$user->is_admin; // null — 'is_admin' is guarded

// Block all mass assignment:
class StrictUser extends Model {
    protected $guarded = ['*'];
}
$user = new StrictUser(['name' => 'Jane']); // 'name' is silently ignored
$user->name = 'Jane'; // works fine — direct property assignment still bypasses $guarded by design only for explicit single-attribute sets
```

**Notes:**
- `newFromBuilder()` — used internally to hydrate models from query results — intentionally bypasses `$fillable`/`$guarded`, since it's populating attributes from trusted data already in the database, not from user input.
- For protecting attributes from being **read** out in API responses (rather than written via mass assignment), see `$hidden`/`$visible` below.

---

## Hidden & Visible Attributes

WPORM supports Eloquent-style `$hidden` and `$visible` properties on models to control which attributes appear in `toArray()` / `toJson()` output. This is the standard way to keep sensitive columns (passwords, tokens, API secrets, etc.) out of API responses and logs.

### $hidden
**Description:** An array of attribute names to exclude from `toArray()`/`toJson()` output. Hidden attributes are still fully readable/writable on the model (`$user->password` still works) — they're only stripped from the serialized array/JSON representation.

**Example:**
```php
class User extends Model {
    protected $fillable = ['name', 'email', 'password'];
    protected $hidden = ['password', 'remember_token'];
}

$user = User::find(1);
$user->toArray(); // 'password' and 'remember_token' are NOT present
```

### $visible
**Description:** An allow-list of attribute names to include in `toArray()`/`toJson()` output. When `$visible` is non-empty, only those keys (and any of them not also listed in `$hidden`) are kept; everything else is dropped. If both `$visible` and `$hidden` are set, `$visible` is applied first, then `$hidden` removes from what remains.

**Example:**
```php
class User extends Model {
    protected $visible = ['id', 'name', 'email'];
}

$user = User::find(1);
$user->toArray(); // only id, name, email are present, everything else is dropped
```

### getHidden() / setHidden(array $hidden)
**Description:** Get or replace the model's `$hidden` list at runtime.

**Example:**
```php
$user->setHidden(['password']);
$hiddenKeys = $user->getHidden();
```

### getVisible() / setVisible(array $visible)
**Description:** Get or replace the model's `$visible` allow-list at runtime.

**Example:**
```php
$user->setVisible(['id', 'name']);
$visibleKeys = $user->getVisible();
```

### makeHidden($attributes)
**Description:** Hide one or more additional attributes on this instance only, without modifying the model's `$hidden` property. Accepts a string, multiple string arguments, or an array. Returns `$this` for chaining.

**Example:**
```php
$user = User::find(1);
$user->makeHidden('email')->toArray(); // 'email' is now hidden too, just for this instance
$user->makeHidden(['email', 'phone']);
```

### makeVisible($attributes)
**Description:** Reveal one or more attributes on this instance only, even if they're listed in `$hidden`. Accepts a string, multiple string arguments, or an array. Returns `$this` for chaining.

**Example:**
```php
$user = User::find(1); // $hidden = ['password']
$user->makeVisible('password')->toArray(); // 'password' is included this time
```

**Notes:**
- `$hidden`/`$visible` apply uniformly to plain attributes, eager-loaded relations, and `$appends`-computed attributes, since filtering happens on the fully-assembled array right before it's returned from `toArray()`.
- `makeHidden()`/`makeVisible()` are per-instance and non-persistent — they don't change the class-level `$hidden`/`$visible` defaults, only the current object.
- This complements (not replaces) `$fillable`/`$guarded`: `$fillable`/`$guarded` control what can be **written** via mass assignment (`fill()`, `__set()`, `updateOrCreate()`, etc.), while `$hidden`/`$visible` control what's **read** out via `toArray()`/`toJson()`. Use both together for sensitive columns — guard them from mass assignment AND hide them from serialized output.

---

## JSON Where Clauses

### whereJson / orWhereJson
Query a value inside a JSON column using MySQL/MariaDB JSON path syntax. The `->` operator is used to specify the path.

```php
// Find users where preferences->dining->meal is 'salad'
$users = $query->whereJson('preferences->dining->meal', 'salad')->get();

// With operator
$users = $query->whereJson('preferences->dining->meal', '!=', 'pizza')->get();

// OR variant
$users = $query->orWhereJson('preferences->dining->meal', 'salad')->get();
```

### whereJsonContains / orWhereJsonContains
Query if a JSON array column contains a value or set of values.

```php
// Find users where options->languages contains 'en'
$users = $query->whereJsonContains('options->languages', 'en')->get();

// Find users where options->languages contains both 'en' and 'de'
$users = $query->whereJsonContains('options->languages', ['en', 'de'])->get();

// OR variant
$users = $query->orWhereJsonContains('options->languages', 'fr')->get();
```

### whereJsonLength / orWhereJsonLength
Query the length of a JSON array at a given path.

```php
// Find users where options->languages array is empty
$users = $query->whereJsonLength('options->languages', 0)->get();

// Find users where options->languages array has more than 1 element
$users = $query->whereJsonLength('options->languages', '>', 1)->get();

// OR variant
$users = $query->orWhereJsonLength('options->languages', '>=', 3)->get();
```

**Note:** These methods require your database to support JSON column types and functions (MySQL 5.7+/MariaDB 10.2+/PostgreSQL 9.2+).

---

## Event Hooks

### retrieved()
**Description:** Called after a model is retrieved from the database (get/first/find). Override in your model to add custom logic.

**Example:**
```php
protected function retrieved() {
    // Custom logic after retrieval
}
```

### creating(), updating(), deleting()
**Description:** Event hooks called before insert, update, or delete. Override in your model to add custom logic (e.g., data sanitization).

**Example:**
```php
protected function creating() {
    $this->name = sanitize_text_field($this->name);
}
```

### softDeleting(), softDeleted(), restoring(), restored()
**Description:** Event hooks for soft deletes. Override these in your model to add custom logic before/after soft delete and restore.

**Example:**
```php
protected function softDeleting() {
    // Called before soft delete
}
protected function softDeleted() {
    // Called after soft delete
}
protected function restoring() {
    // Called before restore
}
protected function restored() {
    // Called after restore
}
```

---

## ArrayAccess Methods

### offsetExists($offset)
**Description:** Check if an attribute exists (for array access).

**Example:**
```php
isset($user['name']);
```

### offsetGet($offset)
**Description:** Get an attribute value (for array access).

**Example:**
```php
$name = $user['name'];
```

### offsetSet($offset, $value)
**Description:** Set an attribute value (for array access).

**Example:**
```php
$user['name'] = 'Qux';
```

### offsetUnset($offset)
**Description:** Unset an attribute (for array access).

**Example:**
```php
unset($user['name']);
```

---

## Notes
- All methods assume a model class extending `MJ\WPORM\Model` (e.g., `class User extends Model { ... }`).
- For more advanced usage, see the main `Readme.md`.

---

## Raw Table Queries with DB::table()

You can use the static `DB::table()` method to run queries on any table, not just models. This is useful for quick updates, inserts, or selects on tables without a model class. The underlying model used by `DB::table()` includes `timestamps`, `softDeletes`, `fillable`, `createdAtColumn`, and `updatedAtColumn` properties (all disabled/empty by default), so query builder features that depend on them (e.g. `upsert()`, soft-delete scoping) work without errors.

**Example:**
```php
use MJ\WPORM\DB;

DB::table('post')->where('id', 1)->update(['title' => 'Updated Title']);
DB::table('custom_table')->where('status', 'active')->get();
```

See [DB.md](./DB.md) for more details.

---

## Pagination

### paginate($perPage = 15, $page = null)
Returns a paginated result array with total count and page info:
- `data`: Collection of results for the current page
- `total`: Total number of records
- `per_page`: Number of records per page
- `current_page`: Current page number
- `last_page`: Last page number
- `from`: First record number on this page
- `to`: Last record number on this page

### simplePaginate($perPage = 15, $page = null)
Returns a paginated result array without total count (more efficient for large tables):
- `data`: Collection of results for the current page
- `per_page`: Number of records per page
- `current_page`: Current page number
- `next_page`: Next page number (or null if no more pages)

**Example:**
```php
$result = User::query()->where('active', true)->paginate(10, 2);
foreach ($result['data'] as $user) {
    // ...
}
```

```php
$result = User::query()->where('active', true)->simplePaginate(10, 2);
foreach ($result['data'] as $user) {
    // ...
}
```

---

## Soft Deletes

### $softDeletes
**Description:** Set to `true` on your model to enable soft deletes. When enabled, `delete()` will set the `deleted_at` column instead of removing the record.

**Example:**
```php
class User extends Model {
    protected $softDeletes = true;
}
```

### $deletedAtColumn
**Description:** Optionally customize the column name for soft deletes (default: `deleted_at`).

**Example:**
```php
class User extends Model {
    protected $softDeletes = true;
    protected $deletedAtColumn = 'removed_at';
}
```

### delete()
**Description:** Soft deletes the model (sets `deleted_at`). If soft deletes are not enabled, performs a hard delete.

**Example:**
```php
$user = User::find(1);
$user->delete();
```

### forceDelete()
**Description:** Permanently deletes the model from the database, even if soft deletes are enabled.

**Example:**
```php
$user = User::find(1);
$user->forceDelete();
```

### forceDeleteWith(array $relations = [])

Force delete the model and all specified relationships. Useful for cascading deletes on related models when using soft deletes.

Works with any relationship type (`hasOne`, `hasMany`, `belongsTo`, `belongsToMany`, `hasManyThrough`) — single-result relations (`hasOne`/`belongsTo`) force-delete the one related model if present, and collection relations (`hasMany`/`belongsToMany`/`hasManyThrough`) force-delete every related model returned.

**Parameters:**
- `$relations` (array): Array of relationship method names (strings) to force delete.

**Returns:**
- `bool` True if the model and all specified relationships were force deleted.

**Example:**
```php
$user->forceDeleteWith(['posts', 'comments']);
```

### restore()
**Description:** Restores a soft-deleted model (sets `deleted_at` to null). Also available on QueryBuilder to restore multiple records.

**Example:**
```php
$user = User::query()->onlyTrashed()->first();
$user->restore();
// Or restore multiple:
User::query()->onlyTrashed()->where('role', 'subscriber')->restore();
```

### trashed()
**Description:** Returns `true` if the model is soft deleted.

**Example:**
```php
if ($user->trashed()) { /* ... */ }
```

### withTrashed()
**Description:** Query builder method to include soft-deleted records in results.

**Example:**
```php
$users = User::query()->withTrashed()->get();
```

### onlyTrashed()
**Description:** Query builder method to return only soft-deleted records.

**Example:**
```php
$trashed = User::query()->onlyTrashed()->get();
```

---

### Blueprint::softDeletes($column = 'deleted_at')
**Description:** Adds a nullable DATETIME column for soft deletes (Eloquent-style shortcut). Use this in your schema to enable soft deletes for your model.
**Example:**
```php
$table->softDeletes(); // Adds 'deleted_at' DATETIME NULL
$table->softDeletes('removed_at'); // Adds 'removed_at' DATETIME NULL
```

---

## Batch Creation and Saving

### createMany(array $records)
- Create and save multiple records in a single transaction.
- Rolls back if any save fails.
- Returns array of created model instances.

### saveMany(array $models)
- Save multiple model instances in a single transaction.
- Rolls back if any save fails.
- Returns array of saved model instances.

---

### distinct()
Set the query to return only distinct (unique) results, just like Eloquent.

**Usage:**
```php
$users = User::query()->distinct()->get();
```
- You can also disable it by passing `false`: `$query->distinct(false)`
- Works with all other query builder methods.

---
