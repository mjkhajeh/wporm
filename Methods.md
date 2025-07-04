# WPORM Model Methods Documentation

This document describes all public and static methods of the `MJ\WPORM\Model` class, with a brief description and a simple usage example for each.

---

## Table of Contents
- [Global Scopes](#global-scopes)
- [Constructor](#constructor)
- [Query Methods](#query-methods)
- [Retrieval Methods](#retrieval-methods)
- [Persistence Methods](#persistence-methods)
- [Relationship Methods](#relationship-methods)
- [Utility Methods](#utility-methods)
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
**Description:** Create a new model instance. If primary key is present in attributes, fetches from DB.

**Example:**
```php
$user = new User(['id' => 1]);
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

---

## Relationship Methods

### hasOne($related, $foreignKey = null, $localKey = null)
**Description:** Define a one-to-one relationship.

**Example:**
```php
$profile = $user->hasOne(Profile::class);
```

### hasMany($related, $foreignKey = null, $localKey = null)
**Description:** Define a one-to-many relationship.

**Example:**
```php
$posts = $user->hasMany(Post::class);
```

### belongsTo($related, $foreignKey = null, $ownerKey = null)
**Description:** Define an inverse one-to-one or many relationship.

**Example:**
```php
$user = $profile->belongsTo(User::class);
```

### belongsToMany($related, $pivotTable = null, $foreignPivotKey = null, $relatedPivotKey = null)
**Description:** Define a many-to-many relationship.

**Example:**
```php
$roles = $user->belongsToMany(Role::class);
```

### hasManyThrough($related, $through, $firstKey = null, $secondKey = null, $localKey = null)
**Description:** Define a has-many-through relationship.

**Example:**
```php
$comments = $user->hasManyThrough(Comment::class, Post::class);
```

---

## Relationship Existence Filtering

### whereHas($relation, $constraint = null)
- Filter models where the given relation exists and matches the constraint closure.
- Example: `$query->whereHas('posts', function($q) { $q->where('published', 1); })`

### orWhereHas($relation, $constraint = null)
- OR version of whereHas.

### has($relation, $operator = '>=', $count = 1)
- Filter models with a number of related records matching the operator and count.
- Example: `$query->has('posts', '>=', 5)`
- Operator and count are optional (defaults to ">= 1").

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

**Example:**
```php
$array = $user->toArray();
```

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

You can use the static `DB::table()` method to run queries on any table, not just models. This is useful for quick updates, inserts, or selects on tables without a model class.

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
