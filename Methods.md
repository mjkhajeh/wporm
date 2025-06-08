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

## Utility Methods

### fill(array $attributes)
**Description:** Fill the model with an array of attributes.

**Example:**
```php
$user->fill(['name' => 'Baz']);
```

### toArray()
**Description:** Convert the model's attributes to an array.

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
