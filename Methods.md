# WPORM Model Methods Documentation

This document describes all public and static methods of the `MJ\WPORM\Model` class, with a brief description and a simple usage example for each.

---

## Table of Contents
- [Global Scopes](#global-scopes)
- [Constructor](#constructor)
- [Query Methods](#query-methods)
- [Functional Chaining: tap() and pipe()](#functional-chaining-tap-and-pipe)
- [Subquery Support](#subquery-support)
- [Combining Queries](#combining-queries)
- [Raw SQL Expressions](#raw-sql-expressions)
- [Retrieval Methods](#retrieval-methods)
- [Aggregates & Utility Methods](#aggregates--utility-methods)
- [Batch Processing](#batch-processing)
- [Persistence Methods](#persistence-methods)
- [Relationship Methods](#relationship-methods)
- [Relationship Existence Filtering](#relationship-existence-filtering)
- [Eager Loading](#eager-loading)
- [Eager Loading Counts: withCount()](#eager-loading-counts-withcount)
- [Aggregate Sub-Selects: withSum(), withAvg(), withMin(), withMax()](#aggregate-sub-selects-withsum-withavg-withmin-withmax)
- [Utility Methods](#utility-methods)
- [$dispatchesEvents and EventDispatcher](#dispatchesevents-and-eventdispatcher)
- [Observers](#observers)
- [Mass Assignment Protection](#mass-assignment-protection)
- [$touches — Auto-Update Parent Timestamps](#touches--auto-update-parent-timestamps)
- [Hidden & Visible Attributes](#hidden--visible-attributes)
- [JSON Where Clauses](#json-where-clauses)
- [Transactions](#transactions)
- [Raw Table Queries with DB::table()](#raw-table-queries-with-dbtable)
- [Pagination](#pagination)
- [Soft Deletes](#soft-deletes)
- [Prunable / MassPrunable Traits](#prunable--massprunable-traits)
- [Query Logging & Debugging](#query-logging--debugging)
- [Collection Methods](#collection-methods)

---

## Global Scopes

### addGlobalScope($identifier, $scope)
**Description:** Register a global scope to be applied to all queries for this model. Accepts a closure, a `ScopeInterface` instance, or a class-string that implements `ScopeInterface`.

**Example:**
```php
// Closure
User::addGlobalScope('active', function($query) {
    $query->where('active', 1);
});

// Scope class instance
User::addGlobalScope('active', new ActiveScope());

// Scope class-string (instantiated automatically)
User::addGlobalScope('active', ActiveScope::class);
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

## Query Scopes as Classes (ScopeInterface)

WPORM supports reusable, testable scope classes via the `ScopeInterface`. Unlike `scope*()` methods (which live on the model), scope classes are standalone, shareable across models, and easy to unit test.

### Defining a Scope Class

Create a class implementing `ScopeInterface` (or extending `Scope`):

```php
use MJ\WPORM\Scopes\ScopeInterface;
use MJ\WPORM\QueryBuilder;
use MJ\WPORM\Model;

class ActiveScope implements ScopeInterface {
    public function apply(QueryBuilder $query, Model $model): void {
        $query->where('active', true);
    }
}

// Or extend the abstract base class
use MJ\WPORM\Scopes\Scope;

class RecentScope extends Scope {
    public function apply(QueryBuilder $query, Model $model): void {
        $query->where('created_at', '>=', date('Y-m-d H:i:s', strtotime('-30 days')));
    }
}
```

### Registering Globally

Scope classes can be registered as global scopes:

```php
// By class-string (auto-instantiated)
User::addGlobalScope('active', ActiveScope::class);

// By instance
User::addGlobalScope('active', new ActiveScope());

// All queries now include WHERE active = 1
$users = User::get();
```

### Applying Ad-Hoc

Use `applyScope()` on the query builder for one-off scopes without global registration:

```php
// Apply a scope class instance
$users = User::query()->applyScope(new ActiveScope())->get();

// Apply a class-string
$users = User::query()->applyScope(ActiveScope::class)->get();

// Apply a closure
$users = User::query()->applyScope(function($query, $model) {
    $query->where('age', '>=', 18);
})->get();
```

### ScopeInterface API

| Method | Description |
|---|---|
| `ScopeInterface::apply(QueryBuilder $query, Model $model): void` | Apply constraints to the query |
| `Scope` (abstract) | Convenience base class implementing `ScopeInterface` |
| `Model::addGlobalScope($id, $scope)` | Register a scope globally (closure, instance, or class-string) |
| `QueryBuilder::applyScope($scope)` | Apply a scope ad-hoc (closure, instance, or class-string) |
| `Model::removeGlobalScope($id)` | Remove a registered global scope |
| `Model::getGlobalScopes()` | Get all registered scopes for this model |

### Scoping a Different Model

Scope classes can be applied to any model's query, not just the one they were designed for:

```php
// Apply ActiveScope to a Post query
$posts = Post::query()->applyScope(new ActiveScope())->get();

// Or register it globally on Post
Post::addGlobalScope('active', ActiveScope::class);
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

### orderBy($column, $direction = 'asc')
**Description:** Add an ORDER BY clause. The `$direction` parameter must be `'asc'` or `'desc'` (case-insensitive); any other value throws an `\InvalidArgumentException`.

**Example:**
```php
$users = User::query()->orderBy('name', 'desc')->get();
$users = User::query()->orderBy('created_at')->get(); // defaults to 'asc'
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

### limit($limit)
**Description:** Set the maximum number of records to return. The `$limit` argument must be an integer; passing a non-integer value (e.g. a float like `1.9`) throws an `\InvalidArgumentException`.

**Example:**
```php
$users = User::query()->limit(10)->get();
```

### offset($offset)
**Description:** Set the number of records to skip before returning results. The `$offset` argument must be an integer; passing a non-integer value throws an `\InvalidArgumentException`.

**Example:**
```php
$users = User::query()->limit(10)->offset(20)->get();
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

### orHaving($column, $operator = null, $value = null)
**Description:** Add an OR HAVING clause to the query. Usage is similar to having().

**Example:**
```php
$users = User::query()
    ->groupBy('country')
    ->having('count(*)', '>', 10)
    ->orHaving('count(*)', '<', 2)
    ->get();
```

### orHavingBetween($column, array $values)
**Description:** Add an OR HAVING ... BETWEEN ... AND ... clause to the query.

**Example:**
```php
$users = User::query()
    ->groupBy('country')
    ->havingBetween('count(*)', [5, 20])
    ->orHavingBetween('count(*)', [50, 100])
    ->get();
```

---

## Subquery Support

WPORM supports Eloquent-style subqueries (subselects and derived tables) across SELECT, FROM, and WHERE clauses. Subqueries can be expressed as a `QueryBuilder` instance, a `Closure` that receives a fresh builder, or a raw SQL string.

---

### from($table, ?string $alias = null)
**Description:** Overloaded Eloquent-style `from()`. Two modes:

- **Plain string, no alias** — change the query's target table mid-chain. Clears any previously set `fromSub()` derived table and reverts to a plain `FROM` clause with the new table name.
- **Subquery form (Closure, QueryBuilder, or raw SQL string) + alias** — identical to `fromSub()`. Uses the compiled subquery as a derived table aliased as `$alias`. Throws `\InvalidArgumentException` if alias is omitted for a non-string subquery.

`from($rawSql, $alias)` (string + alias) treats the string as a raw SQL expression, matching Eloquent's behaviour.

**Examples:**
```php
// Change table
$query = User::query()->from('admins')->where('active', 1)->get();
DB::table('orders')->from('invoices')->where('paid', 1)->get();

// Derived table — Closure form
User::query()
    ->from(function($q) {
        $q->from('orders')
          ->select(['user_id', 'SUM(total) as revenue'])
          ->groupBy('user_id');
    }, 'order_totals')
    ->where('revenue', '>', 500)
    ->get();

// Derived table — QueryBuilder form
$sub = Order::query()
    ->select(['user_id', 'SUM(total) as revenue'])
    ->groupBy('user_id');
User::query()->from($sub, 'order_totals')->where('revenue', '>', 500)->get();

// Derived table — raw SQL string form
DB::table(
    'SELECT user_id, SUM(total) as revenue FROM orders GROUP BY user_id',
    'order_totals'
)->where('revenue', '>', 100)->get();
```

---

### createSub($query): array
**Description:** Compile a `QueryBuilder`, `Closure`, or raw SQL string into a `[$sql, $bindings]` pair. Used internally by all subquery methods; available publicly for advanced use.

**Example:**
```php
[$sql, $bindings] = User::query()->createSub(function($q) {
    $q->from('orders')->selectRaw('COUNT(*)')->whereColumn('user_id', 'users.id');
});
```

---

### fromSub($query, string $alias)
**Description:** Use a subquery as the FROM table (derived table), Eloquent-style. Equivalent to `from($query, $alias)` — kept for explicitness and API compatibility. All subsequent query builder calls on the same instance treat the alias as a real table. Works with `where()`, `orderBy()`, `limit()`, `count()`, `get()`, `first()`, etc.

**Examples:**
```php
// Closure form
$result = DB::table(function($q) {
    $q->from('orders')
      ->select(['user_id', 'SUM(total) as total'])
      ->groupBy('user_id');
}, 'order_totals')
->where('total', '>', 100)
->orderBy('total', 'desc')
->get();

// QueryBuilder form
$sub = Order::query()
    ->select(['user_id', 'SUM(total) as total'])
    ->groupBy('user_id');

$result = DB::table($sub, 'order_totals')
    ->where('total', '>', 100)
    ->get();

// On an existing query
$users = User::query()
    ->fromSub(function($q) {
        $q->from('users')->where('active', 1)->select('*');
    }, 'active_users')
    ->orderBy('name')
    ->get();
```

---

### selectSub($query, string $alias)
**Description:** Add a subquery to the SELECT clause. The subquery result is aliased as a column on each returned row. Chainable with `select()` and `selectRaw()`.

**Examples:**
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

// Accessing the scalar values
foreach ($users as $user) {
    echo $user->post_count;   // number of posts
    echo $user->order_total;  // sum of orders
}
```

---

### whereSub(string $column, string $operator, $query)
**Description:** Add a `WHERE column OPERATOR (subquery)` clause. Works with any comparison operator: `=`, `!=`, `<`, `>`, `<=`, `>=`, `IN`, `NOT IN`, etc.

**Examples:**
```php
// WHERE id IN (subquery)
User::query()->whereSub('id', 'IN', function($q) {
    $q->from('role_user')->select('user_id')->where('role_id', 1);
})->get();

// WHERE total > (SELECT AVG(total) FROM orders)
Order::query()->whereSub('total', '>', function($q) {
    $q->from('orders')->selectRaw('AVG(total)');
})->get();

// With an existing QueryBuilder
$admins = DB::table('role_user')->select('user_id')->where('role_id', 1);
User::query()->whereSub('id', 'IN', $admins)->get();
```

---

### orWhereSub(string $column, string $operator, $query)
**Description:** OR version of `whereSub`.

**Example:**
```php
User::query()
    ->where('is_superadmin', 1)
    ->orWhereSub('id', 'IN', function($q) {
        $q->from('role_user')->select('user_id')->where('role_id', 2);
    })
    ->get();
```

---

### whereInSub(string $column, $query)
**Description:** Shorthand for `whereSub($column, 'IN', $query)`.

**Example:**
```php
User::query()->whereInSub('id', function($q) {
    $q->from('role_user')->select('user_id')->where('role_id', 1);
})->get();
```

---

### whereNotInSub(string $column, $query)
**Description:** Shorthand for `whereSub($column, 'NOT IN', $query)`.

**Example:**
```php
User::query()->whereNotInSub('id', function($q) {
    $q->from('banned_users')->select('user_id');
})->get();
```

---

### orWhereInSub(string $column, $query)
**Description:** OR version of `whereInSub`.

**Example:**
```php
User::query()
    ->where('role', 'admin')
    ->orWhereInSub('id', function($q) {
        $q->from('role_user')->select('user_id')->where('role_id', 5);
    })
    ->get();
```

---

### orWhereNotInSub(string $column, $query)
**Description:** OR version of `whereNotInSub`.

**Example:**
```php
User::query()
    ->where('active', 1)
    ->orWhereNotInSub('id', function($q) {
        $q->from('suspended_users')->select('user_id');
    })
    ->get();
```

---

## Combining Queries

### union($query)
**Description:** Combine this query with another query using SQL `UNION` (duplicate rows across the combined result sets are removed), Eloquent-style. Accepts either an already-built `QueryBuilder` instance — e.g. another query, possibly on a different model/table, as long as both sides select the same number of columns — or a `Closure` that receives a fresh query builder for the **same model** to build the second branch inline. Any number of `union()`/`unionAll()` calls can be chained; each is appended in the order it was added.

The outer query's own `orderBy()`/`latest()`/`oldest()`/`limit()`/`offset()` (if set) apply to the **combined** result set, exactly like Eloquent/Laravel's query builder — not to either branch individually. If a branch itself carries its own `orderBy()`/`limit()`/`offset()`, that branch is automatically wrapped in parentheses so its own ordering/limiting is preserved rather than being ambiguously merged into the outer query.

`get()`, `first()`, `paginate()`, `simplePaginate()`, `count()`, `exists()`/`doesntExist()`, `pluck()`, and the aggregates (`sum()`/`avg()`/`average()`/`min()`/`max()`) all operate on the combined result set when union branches are present.

**Examples:**
```php
// Combine with another already-built query
$highVotes = User::query()->where('votes', '>', 100);
$lowVotes  = User::query()->where('votes', '<', 10);
$users = $highVotes->union($lowVotes)->get();

// Combine using a closure (builds a fresh query for the same model)
$users = User::query()
    ->where('votes', '>', 100)
    ->union(function ($query) {
        $query->where('votes', '<', 10);
    })
    ->get();

// Chain multiple unions
$users = User::query()->where('role', 'admin')
    ->union(User::query()->where('role', 'editor'))
    ->union(User::query()->where('role', 'owner'))
    ->orderBy('name') // applies to the combined result set
    ->get();

// Works with aggregates, pagination, and existence checks too
$total = User::query()->where('votes', '>', 100)
    ->union(User::query()->where('votes', '<', 10))
    ->count(); // counts rows in the combined result set

$page = User::query()->where('votes', '>', 100)
    ->union(User::query()->where('votes', '<', 10))
    ->paginate(15);
```

### unionAll($query)
**Description:** Same as `union()`, but combines using SQL `UNION ALL` — duplicate rows across the combined result sets are **kept** rather than removed. Identical method signature and usage to `union()`.

**Example:**
```php
$users = User::query()->where('votes', '>', 100)
    ->unionAll(User::query()->where('country', 'US'))
    ->get(); // a user matching both conditions appears twice
```

**Notes:**
- Both sides of a `UNION`/`UNION ALL` must select the same number of columns — this is a SQL requirement, not specific to WPORM. When using the closure form, WPORM builds the second branch against the same model's default `*` selection unless you call `select()` inside the closure, so column counts match automatically in the common case.
- `union()`/`unionAll()` apply only to read queries (`get()`, `first()`, `count()`, `pluck()`, `exists()`, aggregates, pagination). They are not meaningful for, and are not applied to, `update()`/`delete()`.
- Soft-delete scoping (`withTrashed()`/`onlyTrashed()`, or the default "exclude soft-deleted rows" behavior) is applied independently to the outer query and to each union branch, exactly as if `get()` had been called on each one separately.

---

## Raw SQL Expressions

These methods let you drop down to raw SQL for the SELECT, WHERE, GROUP BY, and HAVING clauses when the fluent builder doesn't (or can't cleanly) express what you need — e.g. SQL functions, computed columns, or vendor-specific syntax. Bindings use the same `%s`-style placeholders as the rest of WPORM and are passed straight through to `$wpdb->prepare()`, so they're just as safe as the regular query builder methods. Placeholders are substituted in the order their clause appears in the final SQL (SELECT → WHERE → GROUP BY → HAVING → ORDER BY), so you don't need to worry about binding order across mixed raw/non-raw calls.

### selectRaw($sql, array $bindings = [])
**Description:** Add a raw SQL expression to the SELECT clause, with optional bindings. Can be combined with `select()` and/or multiple `selectRaw()` calls — all are concatenated into the final SELECT list. If `select()` is never called, the default `*` selection is kept alongside the raw expression(s).

**Example:**
```php
$products = Product::query()
    ->selectRaw('COUNT(*) as total')
    ->get();

$products = Product::query()
    ->select('name')
    ->selectRaw('price * %s as adjusted_price', [1.1])
    ->get();
```

### whereRaw($sql, array $bindings = [])
**Description:** Add a raw SQL WHERE clause, with optional bindings.

**Example:**
```php
$products = Product::query()
    ->whereRaw('price > %s', [100])
    ->get();

$orders = Order::query()
    ->whereRaw('YEAR(created_at) = %s AND MONTH(created_at) = %s', [2025, 6])
    ->get();
```

### orWhereRaw($sql, array $bindings = [])
**Description:** Add a raw SQL OR WHERE clause, with optional bindings.

**Example:**
```php
$products = Product::query()
    ->where('featured', true)
    ->orWhereRaw('price > %s', [1000])
    ->get();
```

### groupByRaw($sql, array $bindings = [])
**Description:** Add a raw SQL GROUP BY expression, with optional bindings. Useful for grouping by SQL expressions/functions rather than plain column names.

**Example:**
```php
$dailyTotals = Order::query()
    ->selectRaw('DATE(created_at) as day, SUM(total) as total')
    ->groupByRaw('DATE(created_at)')
    ->get();
```

### havingRaw($sql, array $bindings = [])
**Description:** Add a raw SQL HAVING clause, with optional bindings.

**Example:**
```php
$users = User::query()
    ->groupBy('country')
    ->havingRaw('COUNT(*) > %s', [5])
    ->get();
```

### orHavingRaw($sql, array $bindings = [])
**Description:** Add a raw SQL OR HAVING clause, with optional bindings.

**Example:**
```php
$users = User::query()
    ->groupBy('country')
    ->having('count(*)', '>', 10)
    ->orHavingRaw('SUM(votes) > %s', [1000])
    ->get();
```

> Note: `orderByRaw()` (raw SQL ORDER BY with optional bindings) is documented above under [Query Methods](#orderbyrawsql-array-bindings).

---

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
**Description:** Find a record by primary key, or multiple records by an array of primary keys (Eloquent-style). A single id returns a `Model` or `null`; an array of ids returns a `Collection` (missing ids are simply omitted, same as Eloquent). Triggers `retrieved()` event on each instance found.

**Example:**
```php
$user = User::find(1);          // Model|null
$users = User::find([1, 2, 3]); // Collection (only the ids that exist)
```

### findOrFail($id)
**Description:** Find a record by primary key, or throw a `MJ\WPORM\ModelNotFoundException` if no record matches (Eloquent-style). Runs the exact same single query as `find()` — it does not perform an extra existence check first — and still triggers the `retrieved()` event when a record is found. Available both as a static method on the model and as an instance method on the query builder, so it works mid-chain (e.g. after `with()`, `withTrashed()`).

Also accepts an array of ids, just like `find()`: all matching models are returned as a `Collection`, but if **any** of the requested ids was not found, the exception is thrown listing **every** missing id (not just the first one).

**Example:**
```php
try {
    $user = User::findOrFail(1);
} catch (\MJ\WPORM\ModelNotFoundException $e) {
    // $e->getModel() === User::class
    // $e->getIds()   === 1
    wp_die('User not found', '', ['response' => 404]);
}

// Also available on the query builder, mid-chain:
$user = User::with('posts')->findOrFail(1);
$user = User::query()->withTrashed()->findOrFail(1);

// Array of ids: returns a Collection, or throws listing every missing id
try {
    $users = User::findOrFail([1, 2, 3]);
} catch (\MJ\WPORM\ModelNotFoundException $e) {
    // $e->getIds() === [2, 3] if only id 1 existed
}
```

### firstOrFail($attributes = [])
**Description:** Get the first record matching the given attributes (or, with no arguments, the first record overall), or throw a `MJ\WPORM\ModelNotFoundException` if nothing matches. The static `Model::firstOrFail($attributes)` form builds a `where($attributes)` query for you; the query-builder instance form, `->firstOrFail()`, takes no arguments and simply fails if the already-built query returns no rows — letting you express arbitrarily complex constraints before failing.

**Example:**
```php
// With attributes — first record matching the given conditions
try {
    $user = User::firstOrFail(['email' => 'foo@bar.com']);
} catch (\MJ\WPORM\ModelNotFoundException $e) {
    // handle not-found
}

// Without attributes — first record overall, or throw
$user = User::firstOrFail();

// Equivalent, built on the query builder directly:
$user = User::query()->where('email', 'foo@bar.com')->firstOrFail();

// Works with any query constraints, not just simple equality:
$user = User::query()
    ->where('active', true)
    ->orderBy('created_at', 'desc')
    ->firstOrFail();
```

### Collection::firstOrFail()
**Description:** Get the first item in an already-fetched `Collection`, or throw a `MJ\WPORM\ModelNotFoundException` if the collection is empty (Eloquent-style). Useful after in-memory operations (`filter()`, `map()`, `slice()`, etc.) where the query already ran and a query-builder-level `firstOrFail()` is no longer an option.

**Example:**
```php
$activeAdmins = User::query()->where('role', 'admin')->get()
    ->filter(fn($u) => $u->active);

try {
    $admin = $activeAdmins->firstOrFail();
} catch (\MJ\WPORM\ModelNotFoundException $e) {
    // no active admins found
}
```

**Notes (all methods above):**
- `MJ\WPORM\ModelNotFoundException` extends PHP's built-in `\RuntimeException`, so it can be caught broadly (`catch (\RuntimeException $e)`) or specifically.
- `getModel()` returns the fully-qualified model class name that was queried; `getIds()` returns the id(s) passed to `findOrFail()` (a single value, or an array when any ids were missing from an array lookup; `null` for `firstOrFail()`).
- None of these methods issue any additional queries beyond what `find()`/`first()`/`get()` already perform — the "OrFail" behavior is purely a check on the already-fetched result plus a throw, so there is no performance cost over the non-failing variants.

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

### create(array $attributes = [])
**Description:** Instantiate a new model with the given attributes, save it, and return the instance — a one-line insert + return model (Eloquent-style). Attributes are mass-assigned through the same `$fillable`/`$guarded` rules as `new Model([...])`; anything not allowed through mass assignment is silently skipped. Equivalent to `new static($attributes)` followed by `->save()`, just shorter.

**Example:**
```php
$user = User::create(['name' => 'Foo', 'email' => 'foo@bar.com']);
echo $user->id; // newly-inserted primary key
```

**Notes:**
- Returns the model instance regardless of whether the underlying `save()` succeeded; check `$user->exists` if you need to confirm the insert happened.
- Runs a single `INSERT` query (via `save()` → `insert()`), the same as manually constructing and saving the model — no extra queries.

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

## Batch Processing

These methods process query results in pages instead of loading the entire
result set into memory at once — essential for iterating over large tables
(Eloquent-style `chunk()`/`each()`). Both are built on the same `limit()`/
`offset()` mechanism as `paginate()`, so they respect whatever `where()`/
`join()`/soft-delete scoping is already on the query.

### chunk($count, callable $callback)
**Description:** Run the query in pages of `$count` records, invoking `$callback` once per page with a `Collection` of up to `$count` models and the current page number (`function(Collection $chunk, int $page)`). Returning `false` from the callback stops processing early. Returns `false` if iteration was stopped early, `true` otherwise.

**Example:**
```php
User::query()->where('active', true)->chunk(100, function($users) {
    foreach ($users as $user) {
        // process $user
    }
});

// Stop early once a condition is met
Order::query()->chunk(200, function($orders, $page) {
    foreach ($orders as $order) {
        if ($order->total > 1000000) {
            return false; // stops chunk() entirely
        }
    }
});
```

### each(callable $callback, $count = 1000)
**Description:** Like `chunk()`, but invokes `$callback` once per individual model instead of once per page — `function(Model $item, int $index)`, where `$index` is a running zero-based counter across the whole result set. Internally fetches records in pages of `$count` for memory efficiency. Returning `false` from the callback stops processing early. Returns `false` if iteration was stopped early, `true` otherwise.

**Example:**
```php
User::query()->where('active', true)->each(function($user, $index) {
    // process one $user at a time
});

// Custom chunk size for the underlying paged fetches (default 1000)
User::query()->each(function($user) {
    // ...
}, 500);
```

### cursor()
**Description:** Execute the query and return a `Generator` that yields models one at a time. The SQL query executes once, but models are hydrated lazily as you iterate — only one model is in memory at a time. This is ideal for huge result sets where you want `foreach` simplicity without the memory overhead of loading everything into a Collection upfront. Available as both a QueryBuilder instance method and a static Model method.

**Example:**
```php
// QueryBuilder instance method
foreach (User::query()->where('active', true)->cursor() as $user) {
    // process $user one at a time
}

// Static method on Model
foreach (User::cursor() as $user) {
    // ...
}

// Combine with other query methods
foreach (Post::query()->where('published', true)->orderBy('created_at')->cursor() as $post) {
    // each $post is yielded individually
}

// Use with generator functions
function processLargeExport(): \Generator {
    foreach (User::cursor() as $user) {
        yield $user->toArray();
    }
}
```

**cursor() vs chunk()/each():**

| | `cursor()` | `chunk()` / `each()` |
|---|---|---|
| Query execution | Single query | Multiple paginated queries |
| Memory model | One model at a time | One page at a time |
| Early stop | `break` out of `foreach` | Return `false` from callback |
| Best for | Simple iteration over huge sets | Complex per-page logic or early-stop |

**Notes:**
- The generator executes the full query upfront (one `SELECT`), then yields hydrated models lazily.
- Does **not** support eager loading (`with()`) — relations are not loaded on cursor models.
- Respects soft-delete scoping and `where()`/`join()` conditions on the query.

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

### getWasRecentlyCreated()
**Description:** Check if this model was created by the most recent `save()` call (INSERT), not an update. Returns `true` only if the last save triggered an INSERT. The flag is automatically reset on the next `save()` call. Useful for distinguishing between creation and update logic in save hooks or after-save workflows.

**Example:**
```php
$user = new User(['name' => 'John', 'email' => 'john@example.com']);
$user->save();
$user->wasRecentlyCreated; // true (or use $user->getWasRecentlyCreated())

$user->name = 'Jane';
$user->save();
$user->wasRecentlyCreated; // false

// Common use case: send welcome email only on creation
$user->save();
if ($user->wasRecentlyCreated) {
    Mail::to($user)->send(new WelcomeEmail($user));
}
```

**Notes:**
- Resets to `false` at the start of every `save()` call.
- Only set to `true` by `insert()` — updates, `_forceSetAttribute()`, and direct attribute assignment do not affect it.
- Works with `firstOrCreate()`, `updateOrCreate()`, and similar methods that call `save()` internally.

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

### hasOneOfMany($related, $foreignKey = null, $localKey = null)
**Description:** Define a one-to-one relationship that returns a single record from a many relationship based on ordering. Use with `latestOfMany()`, `oldestOfMany()`, `largestOfMany()`, or `smallestOfMany()` to specify which record to return.

**Example:**
```php
// Get the latest post for a user
public function latestPost() {
    return $this->hasOneOfMany(Post::class)->latestOfMany();
}

// Get the oldest post
public function oldestPost() {
    return $this->hasOneOfMany(Post::class)->oldestOfMany();
}

// Get the largest order
public function largestOrder() {
    return $this->hasOneOfMany(Order::class)->largestOfMany('total');
}

// Get the smallest order
public function smallestOrder() {
    return $this->hasOneOfMany(Order::class)->smallestOfMany('total');
}

// Access as property (returns Model or null)
$latestPost = $user->latestPost;

// Chain with other query methods
$latestPublishedPost = $user->latestPost()
    ->where('published', true)
    ->first();
```

**OfMany methods:**

| Method | Description |
|---|---|
| `latestOfMany($column = 'created_at')` | Order by column descending (most recent/largest) |
| `oldestOfMany($column = 'created_at')` | Order by column ascending (oldest/smallest) |
| `largestOfMany($column)` | Order by column descending |
| `smallestOfMany($column)` | Order by column ascending |

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

### Pivot Model Customization

WPORM supports Eloquent-style pivot customization for `belongsToMany` relationships.

#### withPivot()

Select additional pivot table columns to be accessible on the related model via `$model->pivot`:

```php
// Single column
$tags = $post->tags()->withPivot('order')->get();
foreach ($tags as $tag) {
    echo $tag->pivot->order;
}

// Multiple columns
$tags = $post->tags()->withPivot('order', 'active')->get();

// Chain with other methods
$tags = $post->tags()
    ->withPivot('order', 'active')
    ->where('active', true)
    ->get();
```

#### withTimestamps()

Include pivot table timestamps (`created_at`, `updated_at`) automatically:

```php
$tags = $post->tags()->withTimestamps()->get();
foreach ($tags as $tag) {
    echo $tag->pivot->created_at;
    echo $tag->pivot->updated_at;
}

// Combine with withPivot()
$tags = $post->tags()
    ->withPivot('order')
    ->withTimestamps()
    ->get();
```

#### using() — Custom Pivot Class

Use a custom pivot class for additional logic:

```php
use MJ\WPORM\Pivot;

class TagPost extends Pivot {
    public function isPriority(): bool {
        return ($this->order ?? 0) < 10;
    }
}

// Use the custom class
$tags = $post->tags()->using(TagPost::class)->get();
foreach ($tags as $tag) {
    if ($tag->pivot->isPriority()) {
        // ...
    }
}
```

**Pivot API:**

| Method | Description |
|---|---|
| `$model->pivot` | Access the Pivot instance on a related model |
| `->withPivot(...$columns)` | Select additional pivot columns |
| `->withTimestamps()` | Include pivot created_at/updated_at |
| `->using($class)` | Use a custom Pivot subclass |

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

### hasOneThrough($related, $through, $firstKey = null, $secondKey = null, $localKey = null)
**Description:** Define a one-to-one relationship through an intermediate model. Similar to `hasManyThrough`, but returns a single related record instead of a collection. Useful when a model has one distant related model via an intermediary.

**Parameters (matching `hasManyThrough`):**
- `$firstKey` — FK **on the through table** that points back to *this* model. Defaults to `{this_model}_id`.
- `$secondKey` — FK **on the related table** that points to the through table. Defaults to `{through_model}_id`.
- `$localKey` — PK on *this* model. Defaults to `$primaryKey`.

**Example:**
```php
// Country hasOneThrough Capital, through Land:
// land.country_id   -> $firstKey  (FK on Land -> Country)
// capitals.land_id  -> $secondKey (FK on Capital -> Land)
public function capital() {
    return $this->hasOneThrough(Capital::class, Land::class, 'country_id', 'land_id');
}

// Access as property (resolves automatically):
$capital = $country->capital;
```

**Notes:**
- Access as a property (`$country->capital`) returns a single Model or `null`
- Call `->first()` explicitly to resolve: `$country->capital()->first()`
- Supports eager loading, `withCount`, `whereHas`, and all other query builder features

### morphOne($related, $name, $type = null, $id = null, $localKey = null)
**Description:** Define a polymorphic one-to-one relationship. Defined on the *owning* model (e.g. `Post`). The related table stores the owning model's class (or morph-map alias) in a `{$name}_type` column and its primary key in a `{$name}_id` column. Returns a lazy, chainable `QueryBuilder` — call `->first()` to resolve it, or access it as a property (e.g. `$post->image`) to resolve it automatically.

**Parameters:**
- `$name` — The morph name, e.g. `'imageable'`.
- `$type` — (Optional) Override for the type column name. Defaults to `{$name}_type`.
- `$id` — (Optional) Override for the id column name. Defaults to `{$name}_id`.
- `$localKey` — (Optional) PK on *this* model. Defaults to `$primaryKey`.

**Example:**
```php
class Post extends Model {
    public function image() {
        return $this->morphOne(Image::class, 'imageable');
    }
}

$image = $post->image; // Image|null, via property access
$image = $post->image()->first(); // equivalent
```

### morphMany($related, $name, $type = null, $id = null, $localKey = null)
**Description:** Define a polymorphic one-to-many relationship. Same column conventions and parameters as `morphOne()`, but resolves to a `Collection` instead of a single model.

**Example:**
```php
class Post extends Model {
    public function comments() {
        return $this->morphMany(Comment::class, 'commentable');
    }
}

$comments = $post->comments; // Collection, via property access
$comments = $post->comments()->get(); // equivalent
```

### morphTo($name, $type = null, $id = null)
**Description:** Define the inverse of a polymorphic relationship. Defined on the *related* (child) model (e.g. `Comment`). Resolves to whichever model class is named in this row's own `{$name}_type` column, using its own `{$name}_id` as the lookup key against that class's primary key. Unlike every other relationship method, `$name` is **required** — PHP cannot reliably recover the calling method's own name at runtime, so (unlike Eloquent's reflection-based version) it must be passed explicitly.

**Parameters:**
- `$name` — The morph name, e.g. `'commentable'`. Required.
- `$type` — (Optional) Override for the type column name. Defaults to `{$name}_type`.
- `$id` — (Optional) Override for the id column name. Defaults to `{$name}_id`.

**Example:**
```php
class Comment extends Model {
    public function commentable() {
        return $this->morphTo('commentable');
    }
}

$owner = $comment->commentable; // Post|Video|null, depending on commentable_type
```

If the row's `*_type` value is empty, unmapped, or names a non-existent class, the relation resolves to `null` (or an empty `Collection` if accidentally used where many results were expected) rather than throwing.

---

### Model::morphMap(array $map, $replace = false)
**Description:** Register short string aliases for morph "type" column values, so the database stores e.g. `'post'` instead of the fully-qualified class name `App\Models\Post`. Static, shared globally across all models — call once during bootstrap. Merges into the existing map by default; pass `true` as the second argument to replace it entirely.

**Example:**
```php
use MJ\WPORM\Model;

Model::morphMap([
    'post'  => Post::class,
    'video' => Video::class,
]);
```

### Model::getMorphMap()
**Description:** Get the currently registered morph map as an associative array (`alias => class`).

**Example:**
```php
$map = Model::getMorphMap();
```

### Model::getMorphedModel($morphClass)
**Description:** Resolve a morph "type" column value to a concrete class name — the registered alias's target class if `$morphClass` is a known alias, otherwise `$morphClass` itself (treated as an already-fully-qualified class name). Used internally by `morphTo()` and `with()` eager loading; safe to call directly when working with raw `*_type` values.

**Example:**
```php
$class = Model::getMorphedModel('post'); // Post::class, if mapped — otherwise 'post' unchanged
```

### $model->getMorphClass()
**Description:** Get the value this model instance should be stored as in a morph "type" column when it is the owning side of a polymorphic relation — its registered morph-map alias if one exists, otherwise its fully-qualified class name. Used internally by `morphOne()`/`morphMany()`; rarely needed directly.

**Example:**
```php
$type = $post->getMorphClass(); // 'post' if mapped, otherwise Post::class
```

---

## Relationship Existence Filtering

### whereHas($relation, $constraint = null)
- Filter models where the given relation exists and matches the constraint closure.
- Supported for all relationship types: `hasOne`, `hasMany`, `belongsTo`, `belongsToMany`, `hasManyThrough`, `morphOne`, `morphMany`.
- Example: `$query->whereHas('posts', function($q) { $q->where('published', 1); })`
- `morphTo` relations are filterable from a single resolved row's context but are not meaningful to use in bulk `whereHas()`/`has()` query construction — see the note in the [Readme](./Readme.md#relationship-existence-filtering-wherehas-orwherehas-has).

### orWhereHas($relation, $constraint = null)
- OR version of whereHas.

### has($relation, $operator = '>=', $count = 1)
- Filter models with a number of related records matching the operator and count.
- Implemented as a correlated `COUNT(*)` subquery, so the comparison is enforced exactly (not just an existence check).
- Example: `$query->has('posts', '>=', 5)`
- Operator and count are optional (defaults to ">= 1").
- Supported for `hasOne`, `hasMany`, `belongsTo`, `belongsToMany`, `hasManyThrough`, `morphOne`, `morphMany`.

---

## Eager Loading

### with($relations)
**Description:** Eager-load one or more relations alongside the main query, avoiding N+1 queries. Accepts a relation name, an array of relation names, or an associative array mapping relation names to constraint closures (or an options array — see below). Works for all relationship types (`hasOne`, `hasMany`, `belongsTo`, `belongsToMany`, `hasManyThrough`, `morphOne`, `morphMany`, `morphTo`). Runs exactly one extra query per relation for every type except `morphTo`, regardless of how many parent rows were loaded; `morphTo` runs one batched query per distinct related class present in the result set (since different rows may point to different model types), which is still N+1-free in practice.

**Example:**
```php
$users = User::with('posts')->get();
$users = User::with(['posts', 'profile'])->get();
$users = User::with(['posts' => function($q) {
    $q->where('published', 1);
}])->get();

// Polymorphic relations work the same way
$posts = Post::with('comments')->get();
$comments = Comment::with('commentable')->get();
```

- `hasOne`/`belongsTo`/`hasOneThrough`/`morphOne`/`morphTo` relations resolve to a single model (or `null`).
- `hasMany`/`belongsToMany`/`hasManyThrough`/`morphMany` relations resolve to a `Collection`.

See [Per-relation global-scope control](./Readme.md#per-relation-global-scope-control-eager-loads) in the Readme for disabling global scopes on a specific eager-loaded relation.

---

## Eager Loading Counts: withCount()

### withCount($relations)
**Description:** Eager-load the *count* of one or more relations onto every result, without loading the relation's actual records (Eloquent-style). Adds a `{relation}_count` integer attribute to each model (e.g. `posts_count` for `withCount('posts')`), computed via exactly one grouped `COUNT(*) ... GROUP BY` query per relation — never one query per row. Available both as a static method on the model and as a `QueryBuilder` instance method, so it works mid-chain alongside `where()`, `with()`, `withTrashed()`, etc.

Accepts the same shapes as `with()`:
- A single relation name: `withCount('posts')`
- An array of relation names: `withCount(['posts', 'comments'])`
- An associative array mapping a relation name to a constraint closure: `withCount(['posts' => function($q) { $q->where('published', 1); }])`

A custom output attribute name is supported via `"relation as alias"` syntax (with or without a constraint), useful when counting the same relation more than once under different constraints:

```php
withCount('posts as published_posts_count')
withCount(['posts as published_posts_count' => function($q) { $q->where('published', 1); }])
```

**Supported relationship types:** `hasOne`, `hasMany`, `belongsTo`, `belongsToMany`, `hasManyThrough`, `morphOne`, `morphMany`. `morphTo` is not supported (the related model class varies per row, so a single grouped count query isn't possible — the same limitation Eloquent has); counts requested on a `morphTo` relation resolve to `0`.

**Examples:**
```php
// Single relation
$users = User::withCount('posts')->get();
foreach ($users as $user) {
    echo $user->posts_count;
}

// Multiple relations — one extra query per relation
$users = User::withCount(['posts', 'comments'])->get();

// Constrained count
$users = User::withCount(['posts' => function($q) {
    $q->where('published', 1);
}])->get();

// Same relation, multiple aliased counts
$users = User::withCount([
    'posts',
    'posts as published_posts_count' => function($q) {
        $q->where('published', 1);
    },
])->get();

// Mid-chain, combined with where()/with()
$users = User::query()
    ->where('active', true)
    ->withCount('posts')
    ->with('profile')
    ->get();
```

**Notes:**
- The resulting count is a plain integer **attribute**, not a relation — it's present directly on `$model->{alias}` and included automatically in `toArray()`/`toJson()` output (subject to `$hidden`/`$visible`, like any other attribute). It does not populate or require accessing the relation itself (e.g. `$user->posts` is unaffected and, if also accessed, triggers its own separate query as usual).
- Respects the related model's soft-delete scope (soft-deleted related rows are excluded from the count unless the constraint closure calls `withTrashed()` on the count subquery).
- A relation with zero matches yields `0`, not `null`.

---

## Aggregate Sub-Selects: withSum(), withAvg(), withMin(), withMax()

### withSum($relations, $column)
### withAvg($relations, $column)
### withMin($relations, $column)
### withMax($relations, $column)

**Description:** Eager-load a single aggregate value (SUM, AVG, MIN, or MAX) of a column from one or more relations onto every result, without loading the relation's actual records. Adds a `{relation}_{function}` float attribute to each model (e.g. `orders_sum_total` for `withSum('orders', 'total')`), computed via exactly one grouped query per relation — never one query per row. Available both as a static method on the model and as a `QueryBuilder` instance method.

The second argument is the column on the related table to aggregate.

Accepts the same shapes as `withCount()`:
- A single relation name: `withSum('orders', 'total')`
- An array of relation names: `withSum(['orders', 'payments'], 'amount')`
- An associative array mapping a relation name to a constraint closure: `withSum(['orders' => function($q) { $q->where('status', 'completed'); }], 'total')`

A custom output attribute name is supported via `"relation as alias"` syntax:

```php
withSum('orders as completed_total', 'total')
withSum(['orders as completed_total' => function($q) { $q->where('status', 'completed'); }], 'total')
```

**Supported relationship types:** `hasOne`, `hasMany`, `belongsTo`, `belongsToMany`, `hasManyThrough`, `morphOne`, `morphMany`. `morphTo` is not supported — aggregates resolve to `null`.

**Examples:**
```php
// Single relation
$users = User::withSum('orders', 'total')->get();
foreach ($users as $user) {
    echo $user->orders_sum_total;
}

// Multiple relations — one extra query per relation
$users = User::withSum(['orders', 'payments'], 'amount')->get();

// Average
$users = User::withAvg('reviews', 'rating')->get();

// Constrained aggregate
$users = User::withSum(['orders' => function($q) {
    $q->where('status', 'completed');
}], 'total')->get();

// Same relation, multiple aliased aggregates
$users = User::withSum([
    'orders',
    'orders as completed_orders_sum' => function($q) {
        $q->where('status', 'completed');
    },
], 'total')->get();

// Mid-chain, combined with where()/with()
$users = User::query()
    ->where('active', true)
    ->withSum('orders', 'total')
    ->with('profile')
    ->get();
```

**Notes:**
- The resulting value is a plain float **attribute**, not a relation — it's present directly on `$model->{alias}` and included in `toArray()`/`toJson()` output (subject to `$hidden`/`$visible`).
- Returns `null` when no related rows match (unlike `withCount()` which returns `0`).
- Respects the related model's soft-delete scope.

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
**Description:** Convert the model to a JSON string (Eloquent-style serialization). Internally calls `toArray()`, so it respects `$hidden`/`$visible` (and any runtime `makeHidden()`/`makeVisible()` overrides) the same way. `$options` is passed straight through to `json_encode()` (e.g. `JSON_PRETTY_PRINT`).

If the underlying `json_encode()` call fails (e.g. malformed UTF-8 in an attribute, or a `NAN`/`INF` float produced by a cast), a `\JsonException` is thrown — mirroring Eloquent's `JsonEncodingException` — instead of silently returning `false`, so encoding problems are caught immediately rather than producing an empty/corrupt payload downstream.

**Example:**
```php
$json = $user->toJson();
$pretty = $user->toJson(JSON_PRETTY_PRINT);

try {
    $json = $user->toJson();
} catch (\JsonException $e) {
    // handle/log the encoding failure
}
```

> `Collection` also has a `toJson()` method (same exception-on-failure behavior) that JSON-encodes the result of `Collection::toArray()`, so hidden attributes stay hidden for lists of models too.

### __toString()
**Description:** Convert the model to its string representation, Eloquent-style. Returns the same output as `toJson()`, so a model can be used directly in string contexts — `echo $user;`, string interpolation, logging, etc. — without calling `toJson()` explicitly. Subject to the same `\JsonException` on encoding failure as `toJson()`.

**Example:**
```php
echo $user; // same as echo $user->toJson();
$log = "Created user: {$user}";
```

> `Collection` also implements `__toString()` for the same purpose — `echo $users;` is equivalent to `echo $users->toJson();`.

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

### fresh($with = [])
**Description:** Re-fetch a fresh copy of the model from the database by primary key, returning a **new** instance — the current instance is left completely untouched (Eloquent-style). Bypasses global scopes (`newQueryWithoutScopes()`-equivalent), since refetching "this exact row" shouldn't be hidden by an unrelated global scope. Accepts an optional relation name or array of relation names to eager-load on the fresh instance, exactly like `with()`. Returns `null` if the row no longer exists (e.g. it was hard-deleted, or soft-deleted and excluded by the default scope).

**Example:**
```php
$user = User::find(1);
// ... another process updates this row ...

$freshUser = $user->fresh();        // new instance with current DB values
$freshUser = $user->fresh('posts'); // also eager-loads the posts relation
$freshUser = $user->fresh(['posts', 'profile']);

$user->name;      // still the OLD value — fresh() never mutates $user
$freshUser->name; // the current value

if ($user->fresh() === null) {
    // the row has been deleted since $user was loaded
}
```

**Notes:**
- Issues exactly one query (`where($primaryKey, ...)->first()`), plus one additional query per eager-loaded relation, same cost as a normal `with()` call.
- If the model has no primary key value set (e.g. a `new Model([...])` that was never saved), returns `null` without querying.

### refresh()
**Description:** Re-fetch the model's attributes from the database by primary key and overwrite them onto the **current** instance in place (Eloquent-style). Unlike `fresh()`, this mutates `$this` and returns it for chaining, rather than returning a separate instance. Bypasses global scopes for the same reason as `fresh()`. Any previously eager-loaded relations on the instance are cleared, since they may now be stale — re-access them via property access or `with()` after refreshing if you need them again. Throws `MJ\WPORM\ModelNotFoundException` if the row no longer exists.

**Example:**
```php
$user = User::find(1);
// ... another process updates this row, e.g. via increment() elsewhere ...

$user->refresh();      // $user now reflects the current DB row
echo $user->name;      // updated value
$user === $user->refresh(); // true — returns $this, useful for chaining

try {
    $user->refresh();
} catch (\MJ\WPORM\ModelNotFoundException $e) {
    // the row was deleted (or soft-deleted) elsewhere
}
```

**Notes (fresh() / refresh()):**
- Both query strictly by primary key and ignore global scopes.
- Neither applies `withTrashed()` — if the model is soft-deleted, `fresh()` returns `null` and `refresh()` throws, matching Eloquent's own `fresh()`/`refresh()` behavior.
- `fresh()` is non-destructive (returns a new object, current instance untouched); `refresh()` is in-place (mutates `$this`).
- If the model has no primary key value, `fresh()` returns `null` and `refresh()` throws `ModelNotFoundException` immediately, without querying.

### replicate(array $except = [])
**Description:** Create a new, unsaved copy of the model with all attributes carried over except the primary key, timestamp columns, and soft-delete column (Eloquent-style). This is the standard way to duplicate a record — modify the clone, then `save()`. Accepts an optional array of additional attribute names to exclude from the clone. All attributes are copied directly, bypassing `$fillable`/`$guarded` mass-assignment protection, since the source data is already trusted.

**Example:**
```php
$post = Post::find(1);
echo $post->title;  // "Original Post"
echo $post->id;     // 1

$clone = $post->replicate();
echo $clone->title; // "Original Post"
echo $clone->id;    // null
echo $clone->exists; // false

$clone->title = 'Cloned Post';
$clone->save();

// Exclude additional attributes
$clone = $post->replicate(['slug', 'meta']);
$clone->title = 'Clean Clone';
$clone->save();
```

**Notes:**
- The new instance is **not saved** — call `save()` to persist it.
- The primary key, `created_at`, `updated_at`, and soft-delete column are always excluded, even if you don't pass `$except`.
- Relations are **not** copied — only scalar attributes.
- `$clone->exists` is `false` and `$clone->{$pk}` is `null`, so the next `save()` triggers an INSERT.

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

## $touches — Auto-Update Parent Timestamps

### $touches
**Description:** An array of relationship names that should have their `updated_at` timestamp updated when this model is saved. When a child model is saved, any parent listed in `$touches` will automatically have its `updated_at` set to the current time. This mirrors Eloquent's `$touches` behavior.

**Example:**
```php
class Comment extends Model {
    protected $touches = ['post'];

    public function post() {
        return $this->belongsTo(Post::class);
    }
}

// When a comment is saved, the parent post's updated_at is also updated
$comment = Comment::find(1);
$comment->body = 'Updated comment';
$comment->save();

// The parent Post's updated_at is now current
$post = Post::find($comment->post_id);
echo $post->updated_at; // reflects the time the comment was saved
```

**Multiple relationships:**
```php
class Comment extends Model {
    protected $touches = ['post', 'author'];

    public function post() {
        return $this->belongsTo(Post::class);
    }

    public function author() {
        return $this->belongsTo(User::class);
    }
}
```

**getTouches()**
**Description:** Get the relationships that should be touched when this model is saved.

**Example:**
```php
$comment = new Comment;
$comment->getTouches(); // ['post']
```

**Notes:**
- Only works with `belongsTo` relationships (parent models)
- The parent model must have `timestamps = true` (default)
- Touching happens after a successful save (insert or update)
- Prevents infinite loops — only one level of touching

**touchWithEvents()**
**Description:** Like `touch()`, but fires the full lifecycle event pipeline (`saving`/`saved`/`updating`/`updated`) so that registered listeners and observers are notified. Use this for user-initiated timestamp updates where event side-effects are expected.

**Example:**
```php
$post->touchWithEvents(); // fires saving → updating → updated → saved
```

**Notes:**
- Do **not** use inside `$touches` callbacks — it will cause infinite recursion on circular references
- `touch()` (no events) is used internally by `$touches` to avoid that recursion

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

### boot() / booted()

**Description:** WPORM uses a boot lifecycle matching Eloquent. Override `booted()` in your model to register event closures. The model class is booted once, on first use.

**Example:**
```php
class User extends Model {
    protected static function booted() {
        static::creating(function ($user) {
            $user->slug = Str::slug($user->name);
        });

        static::saving(function ($user) {
            $user->email = strtolower($user->email);
        });

        static::retrieved(function ($user) {
            // Runs after model is fetched from DB
        });
    }
}
```

### registerModelEvent($event, $callback)

**Description:** Register a model event callback programmatically. Typically called from `booted()` via the static helper methods (`static::creating(fn)`, `static::saving(fn)`, etc.).

**Supported events:**
`retrieved`, `creating`, `created`, `updating`, `updated`, `saving`, `saved`, `deleting`, `deleted`, `softDeleting`, `softDeleted`, `restoring`, `restored`.

**Before-events** (`saving`, `creating`, `updating`, `deleting`, `softDeleting`, `restoring`) halt the operation if a callback returns `false`. After-events are informational only.

---

## $dispatchesEvents and EventDispatcher

WPORM provides Eloquent-style `$dispatchesEvents` and a standalone `EventDispatcher` for wiring model lifecycle events to listener classes or callables — no Laravel/Illuminate dependency required.

### $dispatchesEvents

**Description:** Map model lifecycle event names (lowercase) to listener class-strings or callables. The listener class must expose a `handle(\MJ\WPORM\Events\ModelEvent $event)` method. Set on the model class.

**Supported event names:**
`retrieved`, `creating`, `created`, `updating`, `updated`, `saving`, `saved`, `deleting`, `deleted`, `softDeleting`, `softDeleted`, `restoring`, `restored`.

**Before-hooks** (`saving`, `creating`, `updating`, `deleting`, `softDeleting`, `restoring`) halt the operation if a listener returns `false`. After-hooks (`saved`, `created`, `updated`, `deleted`, `softDeleted`, `restored`, `retrieved`) are informational only.

**Example:**
```php
use MJ\WPORM\Events\Creating;
use MJ\WPORM\Events\Deleted;

class LogUserCreating {
    public function handle(Creating $event) {
        error_log('Creating user: ' . $event->model->email);
    }
}

class CleanupUserData {
    public function handle(Deleted $event) {
        // e.g. delete related files, revoke tokens, etc.
        wp_delete_user_meta($event->model->id, 'auth_token');
    }
}

class User extends Model {
    protected $fillable = ['name', 'email'];
    protected $hidden   = ['password'];

    public $dispatchesEvents = [
        'creating' => LogUserCreating::class,
        'deleted'  => CleanupUserData::class,
    ];
}
```

### EventDispatcher::listen($eventClass, $listener)

**Description:** Register a global listener for an event class. Fires for every model that raises that event, regardless of which model class it is. Callable or class-string accepted.

**Example:**
```php
use MJ\WPORM\EventDispatcher;
use MJ\WPORM\Events\Creating;
use MJ\WPORM\Events\Saved;

// Closure listener
EventDispatcher::listen(Creating::class, function(Creating $event) {
    error_log(get_class($event->model) . ' is being created');
});

// Class-string listener (must have handle() method)
EventDispatcher::listen(Saved::class, \App\Listeners\AuditLog::class);

// [ClassName, 'method'] listener
EventDispatcher::listen(Creating::class, [\App\Listeners\Validator::class, 'handle']);
```

### EventDispatcher::forget($eventClass = null)

**Description:** Remove all global listeners for an event class, or all listeners when called with no argument.

**Example:**
```php
EventDispatcher::forget(\MJ\WPORM\Events\Creating::class); // remove Creating listeners
EventDispatcher::forget(); // remove all global listeners
```

### EventDispatcher::getListeners($eventClass)

**Description:** Return all registered global listeners for an event class.

**Example:**
```php
$listeners = EventDispatcher::getListeners(\MJ\WPORM\Events\Creating::class);
```

### Event classes

All event classes live in the `MJ\WPORM\Events` namespace and extend `MJ\WPORM\Events\ModelEvent`. Each carries a `$model` property referencing the model instance that fired the event.

| Event class | Short-name key | When fired |
|---|---|---|
| `Events\Retrieved` | `retrieved` | after fetch from DB |
| `Events\Creating` | `creating` | before INSERT |
| `Events\Created` | `created` | after INSERT |
| `Events\Updating` | `updating` | before UPDATE |
| `Events\Updated` | `updated` | after UPDATE |
| `Events\Saving` | `saving` | before INSERT or UPDATE |
| `Events\Saved` | `saved` | after INSERT or UPDATE |
| `Events\Deleting` | `deleting` | before hard DELETE |
| `Events\Deleted` | `deleted` | after hard DELETE |
| `Events\SoftDeleting` | `softDeleting` | before soft delete |
| `Events\SoftDeleted` | `softDeleted` | after soft delete |
| `Events\Restoring` | `restoring` | before restore |
| `Events\Restored` | `restored` | after restore |

**Halting an operation from a listener:**

Return `false` from any before-hook listener to cancel the operation. `save()`, `delete()`, and `restore()` return `false` when halted.

```php
// Cancel save if validation fails
EventDispatcher::listen(\MJ\WPORM\Events\Saving::class, function($event) {
    if (empty($event->model->email)) {
        return false; // aborts save()
    }
});
```

**Creating a custom event listener class:**
```php
use MJ\WPORM\Events\Creating;

class ValidateBeforeCreate {
    public function handle(Creating $event): void {
        $model = $event->model;
        if (empty($model->email)) {
            throw new \InvalidArgumentException('Email required');
        }
    }
}
```

**Combining $dispatchesEvents with global listeners:**

Both fire on every event. `$dispatchesEvents` mapping fires first, then global listeners. Either can halt a before-hook by returning `false`.

---

## Observers

Observers provide an Eloquent-style way to listen for model lifecycle events. An observer class defines methods matching event names (`creating`, `created`, `updating`, `updated`, etc.) that receive the model directly as their first argument — not the event object. This keeps observer code clean and focused on the model.

### Model::observe($observer)

**Description:** Register an observer class or instance for this model. The observer's lifecycle methods will be called automatically when the corresponding event fires.

Accepts:
- A class name (instantiated once per event dispatch): `User::observe(UserObserver::class)`
- An observer instance: `User::observe(new UserObserver())`

**Example:**
```php
class UserObserver {
    public function creating($user) {
        $user->slug = sanitize_title($user->name);
    }

    public function created($user) {
        wp_mail($user->email, 'Welcome!', 'Thanks for signing up.');
    }

    public function updated($user) {
        if ($user->isDirty('email')) {
            // Send verification email
        }
    }

    public function deleted($user) {
        wp_delete_user_meta($user->id, 'auth_token');
    }
}

// Register in your plugin bootstrap or service provider
User::observe(UserObserver::class);
```

**Halting from an observer:** Return `false` from a before-hook method to cancel the operation:

```php
class PreventDuplicate {
    public function creating($model) {
        // Check for duplicates
        $exists = User::query()->where('email', $model->email)->exists();
        if ($exists) {
            return false; // aborts save()
        }
    }
}
```

### Model::getObservers()

**Description:** Return all registered observers for this model class.

**Example:**
```php
$observers = User::getObservers();
// ['App\Observers\UserObserver' => 'App\Observers\UserObserver']
```

### Model::forgetObservers($observerClass = null)

**Description:** Remove a specific observer class, or all observers for this model when called with null.

**Example:**
```php
User::forgetObservers(UserObserver::class); // remove one
User::forgetObservers();                    // remove all for User
```

### Model::flushAllObservers()

**Description:** Remove all observers from all model classes. Useful in test teardown.

**Example:**
```php
// In PHPUnit tearDown()
User::flushAllObservers();
```

**Supported observer methods:**

| Method | Fires when |
|---|---|
| `retrieved` | after fetch from DB |
| `saving` | before INSERT or UPDATE |
| `saved` | after INSERT or UPDATE |
| `creating` | before INSERT |
| `created` | after INSERT |
| `updating` | before UPDATE |
| `updated` | after UPDATE |
| `deleting` | before hard DELETE |
| `deleted` | after hard DELETE |
| `softDeleting` | before soft delete |
| `softDeleted` | after soft delete |
| `restoring` | before restore |
| `restored` | after restore |

**Dispatch order:** When multiple mechanisms are active for the same event, they fire in this order:
1. `$dispatchesEvents` class mapping
2. Global `EventDispatcher::listen()` listeners
3. Registered observers

Any before-hook can halt the operation by returning `false`.

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
**Description:** Set an attribute value (for array access). Respects `$fillable`/`$guarded` mass-assignment protection, just like direct property assignment.

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

## Transactions

### DB::transaction(Closure $callback, int $attempts = 1)
**Description:** Execute a Closure within a database transaction (Eloquent-style). Commits automatically when the callback returns without throwing; rolls back and re-throws on any exception. Pass `$attempts > 1` to retry automatically on MySQL deadlock (1213) or lock-wait timeout (1205). The callback's return value is forwarded to the caller.

**Example:**
```php
use MJ\WPORM\DB;

// Basic — commit on success, rollback + re-throw on exception
$user = DB::transaction(function() {
    $u = User::create(['name' => 'Alice', 'email' => 'alice@example.com']);
    Profile::create(['user_id' => $u->id]);
    return $u;
});

// With deadlock retry (up to 3 attempts)
DB::transaction(function() {
    Inventory::query()->where('product_id', 42)->decrement('stock');
    Order::create(['product_id' => 42, 'qty' => 1]);
}, 3);
```

### QueryBuilder::transaction(Closure $callback, int $attempts = 1)
**Description:** Same semantics as `DB::transaction()`, available on any `QueryBuilder` instance for inline use in a chain.

**Example:**
```php
User::query()->transaction(function() {
    User::create(['name' => 'Bob']);
    Log::create(['action' => 'user_created']);
});
```

### beginTransaction() / commit() / rollBack()
**Description:** Low-level manual transaction control on a `QueryBuilder` instance. Prefer `DB::transaction()` for most cases — it guarantees cleanup even when a non-`\Exception` `\Throwable` is thrown. Use these only when you need to manage the transaction boundary explicitly across multiple steps.

**Example:**
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
**Description:** Permanently deletes the model from the database, even if soft deletes are enabled. Fires `deleting` and `deleted` lifecycle events (both `fireModelEvent()` and direct method hooks), so observers and listeners are notified just like a regular `delete()`.

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

## Prunable / MassPrunable Traits

WPORM provides two traits for automatic cleanup of old records, similar to Eloquent's Prunable and MassPrunable.

### Prunable

The `Prunable` trait processes records **one at a time**, firing model events (deleting/deleted) for each. Use this when you need to run logic during pruning or have event-driven workflows.

```php
use MJ\WPORM\Prunable;

class AuditLog extends Model {
    use Prunable;

    public function prunable() {
        // Prune records older than 90 days
        return static::query()->where('created_at', '<', now()->subDays(90));
    }
}

// Run the pruning
$pruned = AuditLog::prune();
echo "Pruned {$pruned} records";
```

**Prunable API:**

| Method | Description |
|---|---|
| `prunable()` | (abstract) Return a QueryBuilder defining which records to prune |
| `prune()` | (static) Delete matching records one at a time, firing model events |

**Notes:**
- Each deletion fires `deleting` and `deleted` events
- Returns the number of records pruned
- Uses `cursor()` internally for memory efficiency

### MassPrunable

The `MassPrunable` trait processes records **in chunks** using direct SQL DELETE queries. Model events are **not** fired. Use this for large datasets where performance is critical.

```php
use MJ\WPORM\MassPrunable;

class AuditLog extends Model {
    use MassPrunable;

    public function prunable() {
        return static::query()->where('created_at', '<', now()->subDays(90));
    }
}

// Run the mass pruning (default chunk size: 1000)
$pruned = AuditLog::prune();

// Custom chunk size
$pruned = AuditLog::prune(5000);
```

**MassPrunable API:**

| Method | Description |
|---|---|
| `prunable()` | (abstract) Return a QueryBuilder defining which records to prune |
| `prune(int $chunkSize = 1000)` | (static) Delete matching records in chunks via direct SQL |

**Notes:**
- Model events are **not** fired (faster, but no event hooks)
- Processes in configurable chunks (default: 1000)
- Uses `pluck()` + `whereIn()` for efficient batch deletion
- Returns the total number of records pruned

### Prunable vs MassPrunable

| | `Prunable` | `MassPrunable` |
|---|---|---|
| Processing | One record at a time | In chunks (default: 1000) |
| Model events | Yes (`deleting`, `deleted`) | No |
| Memory usage | Low (uses cursor) | Low (uses pluck + chunk) |
| Best for | Event-driven logic, small datasets | Large datasets, performance-critical |

---

## Batch Creation and Saving

### createMany(array $records)
- Create and save multiple records inside a single transaction (backed by `DB::transaction()` internally).
- Rolls back automatically if any save fails; the exception is re-thrown.
- Returns array of created model instances.

### saveMany(array $models)
- Save multiple model instances inside a single transaction (backed by `DB::transaction()` internally).
- Rolls back automatically if any save fails; the exception is re-thrown.
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

### tap($callback)
**Description:** Pass the query builder instance to the given callback for side-effects, then return the builder unchanged (Eloquent-style). The callback's return value is always discarded. Designed for inline debugging, logging, conditional decoration, or applying a set of constraints from a helper method — without breaking the fluent chain.

Also available on `Collection` and `Model` — works identically, receiving the collection or model instance instead of the builder. On a `Model` instance, `tap()`/`pipe()` are defined directly on the class (not proxied through `__call()`), so they always operate on the model itself rather than a freshly-built query.

**Example:**
```php
$users = User::query()
    ->where('active', true)
    ->tap(function($q) {
        error_log('[Debug] SQL: ' . $q->toSql());
    })
    ->orderBy('name')
    ->get();

// Also accepts any callable (method reference, invokable class, etc.):
$query->tap([$this, 'applyDefaultScopes'])->get();

// On a Collection — inspect without breaking the chain:
$emails = User::query()->get()
    ->tap(fn($c) => error_log('Count: ' . $c->count()))
    ->pluck('email');

// On a Model instance — inspect/log a single model without breaking the chain:
$user = User::create(['name' => 'Jane'])
    ->tap(fn($u) => error_log("Created user #{$u->id}"));
```

### pipe($callback)
**Description:** Pass the query builder instance to the given callback and return whatever the callback returns (Eloquent-style). Unlike `tap()`, the callback's return value IS used — `pipe()` terminates or transforms the fluent chain. Useful for handing the builder off to a repository-level function or a reusable scope object and returning its result inline, without leaving the chain.

Also available on `Collection` and `Model` — works identically, receiving the collection or model instance instead of the builder.

**Example:**
```php
// Execute a scope and return the Collection:
$users = User::query()
    ->where('active', true)
    ->pipe(function($q) {
        return $q->orderBy('name')->get();
    });

// Inject repository-level logic mid-chain:
$result = User::query()
    ->pipe([$userRepo, 'applySearchFilters'])
    ->paginate(20);

// On a Collection — delegate to another layer:
$dto = User::query()->get()
    ->filter(fn($u) => $u->active)
    ->pipe([$userPresenter, 'toDto']);

// On a Model instance — hand off to a presenter/transformer:
$dto = User::find(1)->pipe(fn($u) => $userPresenter->toDto($u));
```

**Key differences between `tap()` and `pipe()`:**
- `tap($cb)` — always returns `$this` (the builder, collection, or model); callback return value is ignored. Use for side-effects.
- `pipe($cb)` — returns whatever the callback returns. Use to produce a result or hand off to another layer.

---

## Collection Methods

`MJ\WPORM\Collection` is the Eloquent-style collection returned by `get()`, `all()`, `find([...])`, and every other multi-row query method. Beyond the methods documented inline in the [Readme](./Readme.md#collections) (`all()`, `first()`, `firstOrFail()`, `last()`, `count()`, `isEmpty()`, `toArray()`, `toJson()`, `__toString()`, `filter()`, `map()`, `transform()`, `tap()`, `pipe()`, `pluck()`, `contains()`, `slice()`, `reverse()`, `after()`), this section documents the additional Eloquent-parity methods.

All transformation methods (`sortBy()`, `groupBy()`, `keyBy()`, `unique()`, `flatMap()`, `values()`, `keys()`, `diff()`, `intersect()`, `merge()`, `mapToGroups()`) return a **new** `Collection` and leave the original untouched, exactly like `map()`/`filter()`. The mutating methods (`push()`, `pull()`, `put()`) modify the collection in place, exactly like `transform()`. Where a `$key` parameter is accepted, you can pass either a string column name (read via array access for plain arrays, or property access for objects/models) or a callable that receives the item and returns the value to use.

### each(callable $callback)
**Description:** Iterate over every item, invoking `$callback($item, $key)`. Returning `false` from the callback stops iteration early (mirrors `QueryBuilder::each()`). Returns `$this` for chaining, though it's primarily intended for side-effects.

**Example:**
```php
$users->each(function ($user, $key) {
    error_log("User #{$key}: {$user->name}");
});
```

### reduce(callable $callback, $initial = null)
**Description:** Reduce the collection to a single value via `$callback($carry, $item)`, starting from `$initial`.

**Example:**
```php
$total = $orders->reduce(fn($carry, $order) => $carry + $order->total, 0);
```

### flatMap(callable $callback)
**Description:** Map each item via `$callback($item, $key)`, then flatten the results by one level into a new `Collection`. If the callback returns an array (or another `Collection`) per item, all of those values are merged into a single flat list.

**Example:**
```php
$tags = $posts->flatMap(fn($post) => $post->tags); // flattens each post's tag array into one list
```

### sortBy($key, $descending = false)
**Description:** Sort the collection by a column name or callback result, preserving keys. Pass `true` as the second argument (or use `sortByDesc()`) for descending order.

**Example:**
```php
$sorted = $users->sortBy('name');
$sorted = $users->sortBy(fn($user) => $user->profile->rank);
$sorted = $users->sortBy('votes', true); // descending
```

### sortByDesc($key)
**Description:** Shorthand for `sortBy($key, true)`.

**Example:**
```php
$topVoted = $users->sortByDesc('votes');
```

### groupBy($key)
**Description:** Group items by a column name or callback result. Returns a `Collection` of `Collection`s, keyed by each distinct grouping value.

**Example:**
```php
$byRole = $users->groupBy('role');
// ['admin' => Collection[...], 'editor' => Collection[...]]

$byYear = $orders->groupBy(fn($o) => date('Y', strtotime($o->created_at)));
```

### keyBy($key)
**Description:** Re-key the collection by a column name or callback result. If multiple items share the same key value, the last one wins (matching Eloquent).

**Example:**
```php
$byEmail = $users->keyBy('email'); // ['a@test.com' => User, ...]
```

### unique($key = null)
**Description:** Get the unique items in the collection. Without `$key`, uniqueness is determined by object identity for objects, by value for scalars, and by JSON encoding for arrays — so two different model instances with the same attributes are NOT deduplicated. With a `$key` (column name or callback), only the first item per distinct extracted value is kept.

**Example:**
```php
$unique = $collection->unique();
$uniqueByEmail = $users->unique('email');
```

### values()
**Description:** Reset the collection's keys to sequential integers, discarding the original keys. Useful after `groupBy()`, `keyBy()`, `filter()`, or `unique()` leave non-sequential/string keys.

**Example:**
```php
$reindexed = $users->filter(fn($u) => $u->active)->values();
```

### keys()
**Description:** Get a `Collection` of the collection's keys.

**Example:**
```php
$ids = $usersById->keys(); // Collection of the keyed-by values
```

### diff($items) / intersect($items)
**Description:** Get the items that are NOT present (`diff`) or ARE present (`intersect`) in the given array or `Collection`. Uses strict (`===`) comparison, so `1` and `"1"` are treated as different values.

**Example:**
```php
$onlyInA = $collectionA->diff($collectionB);
$inBoth  = $collectionA->intersect($collectionB);
```

### merge($items)
**Description:** Merge the given array or `Collection` into this one, using PHP's `array_merge()` semantics (numeric keys renumbered/appended, string keys overwritten).

**Example:**
```php
$combined = $defaults->merge($overrides);
```

### push($value) / pull($key, $default = null) / put($key, $value)
**Description:** Mutating helpers, like `transform()`. `push()` appends a value. `pull()` removes and returns an item by key (or `$default` if not set). `put()` sets an item by key (equivalent to `$collection[$key] = $value`). All three mutate the collection in place and `push()`/`put()` return `$this` for chaining.

**Example:**
```php
$collection->push($newUser);
$removed = $collection->pull('temp_key');
$collection->put('admin', $adminUser);
```

### implode($glue, $key = null)
**Description:** Join the collection's items into a single string with `$glue`, optionally extracting a column/key from each item first.

**Example:**
```php
$csv = $tags->implode(', ');                 // plain scalar items
$names = $users->implode(', ', 'name');       // extract a column first
```

### when($value, callable $callback, ?callable $default = null) / unless($value, callable $callback, ?callable $default = null)
**Description:** Conditionally invoke a callback against the collection, Eloquent-style. `when()` runs `$callback($this, $value)` if `$value` is truthy (or `$default` otherwise); `unless()` is the inverse. If the callback doesn't return a value, the collection itself is returned unchanged, so these are always safe mid-chain.

**Example:**
```php
$result = $collection->when($isAdmin, fn($c) => $c->where('role', 'admin'));
$result = $collection->unless($includeInactive, fn($c) => $c->filter(fn($u) => $u->active));
```

### firstWhere($key, $operator = null, $value = null)
**Description:** Get the first item matching a simple condition, using the same 2-arg (`'key', $value`) or 3-arg (`'key', $operator, $value`) forms as `QueryBuilder::where()`.

**Example:**
```php
$admin = $users->firstWhere('role', 'admin');
$cheap = $products->firstWhere('price', '<', 100);
```

### mapToGroups(callable $callback)
**Description:** Map each item to a single `[groupKey => value]` pair via the callback, then group all values under their respective group keys. Unlike `groupBy()` (which groups by an existing column), `mapToGroups()` lets the callback compute both the group key and the stored value in one pass.

**Example:**
```php
$byRole = $users->mapToGroups(fn($u) => [$u->role => $u->name]);
// ['admin' => Collection['Alice', 'Bob'], 'editor' => Collection['Carol']]
```

### sum($key = null) / avg($key = null) / average($key = null) / min($key = null) / max($key = null)
**Description:** In-memory aggregate helpers over the collection's already-fetched items (as opposed to `QueryBuilder::sum()`/`avg()`/etc., which aggregate in SQL before fetching). All accept an optional column name or callback to extract the value to aggregate from each item; without it, items themselves are used directly (for collections of plain numbers). `avg()`/`min()`/`max()` return `null` for an empty collection; `sum()` returns `0`. `average()` is an alias for `avg()`.

**Example:**
```php
$total = $orders->sum('total');
$total = $orders->sum(fn($o) => $o->total * $o->qty);

$avgPrice = $products->avg('price');
$cheapest = $products->min('price');
$mostExpensive = $products->max('price');
```

---

## Query Logging & Debugging

WPORM provides a centralized query logging system for debugging and profiling, similar to Eloquent's query log.

### DB::enableQueryLog()
**Description:** Enable query logging. All subsequent queries will be recorded with their SQL, bindings, execution time, and connection info.

**Example:**
```php
DB::enableQueryLog();

// Run queries...
User::where('active', true)->get();
Post::where('published', true)->limit(10)->get();

// Get logged queries
$queries = DB::getQueryLog();
```

### DB::disableQueryLog()
**Description:** Disable query logging.

**Example:**
```php
DB::disableQueryLog();
```

### DB::isQueryLogging()
**Description:** Check if query logging is enabled.

**Example:**
```php
if (DB::isQueryLogging()) {
    // Logging is active
}
```

### DB::listen(callable $callback)
**Description:** Register a listener that fires for every executed query. The callback receives three arguments: `$sql` (the SQL string), `$bindings` (bound parameter values), and `$time` (execution time in milliseconds).

**Example:**
```php
// Log all queries to error_log
DB::listen(function($sql, $bindings, $time) {
    error_log("[Query] {$time}ms: {$sql}");
});

// Collect queries for analysis
$queries = [];
DB::listen(function($sql, $bindings, $time) use (&$queries) {
    $queries[] = compact('sql', 'bindings', 'time');
});

// Performance monitoring
DB::listen(function($sql, $bindings, $time) {
    if ($time > 100) {
        error_log("[SLOW QUERY] {$time}ms: {$sql}");
    }
});
```

### DB::getQueryLog()
**Description:** Get all logged queries. Returns an array of query entries, each containing `query`, `bindings`, `time`, and `connection`.

**Example:**
```php
$queries = DB::getQueryLog();
foreach ($queries as $q) {
    echo "{$q['time']}ms: {$q['query']}\n";
    print_r($q['bindings']);
}
```

### DB::queryCount()
**Description:** Get the total number of logged queries.

**Example:**
```php
echo "Executed " . DB::queryCount() . " queries";
```

### DB::queryTime()
**Description:** Get the total execution time of all logged queries (in milliseconds).

**Example:**
```php
echo "Total query time: " . DB::queryTime() . "ms";
```

### DB::flushQueryLog()
**Description:** Clear the query log.

**Example:**
```php
DB::flushQueryLog();
```

### QueryLogger Class
For advanced usage, you can use the `QueryLogger` class directly:

```php
use MJ\WPORM\QueryLogger;

// Enable logging
QueryLogger::enableQueryLog();

// Register listener
QueryLogger::listen(function($sql, $bindings, $time) {
    // Custom handling
});

// Get stats
$count = QueryLogger::count();
$totalTime = QueryLogger::totalTime();
```

---

