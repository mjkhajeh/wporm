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
