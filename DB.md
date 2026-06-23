# DB::table() - Raw Table Query Builder

WPORM provides a static `DB::table()` method for running queries on any database table, similar to Laravel's Eloquent. This is useful for working with tables that do not have a dedicated model class, or for quick, direct queries.

`DB::table()` accepts either a plain table name (string) or a subquery (Closure or `QueryBuilder` instance) for derived-table queries — see [Derived Tables](#derived-tables-subquery-as-from) below.

## Usage

### Update Multiple Rows
```php
use MJ\WPORM\DB;

DB::table('post')
    ->whereIn('id', [3, 4, 5])
    ->update(['title' => 'Updated Title']);
```

### Select Rows
```php
$rows = DB::table('custom_table')
    ->where('status', 'active')
    ->get();
```

### Insert a Row
`QueryBuilder` does not expose a dedicated `insert()` method. For raw inserts, use `$wpdb` directly with the table name, or use `upsert()` / `insertOrIgnore()`-style helpers via a model. For example:
```php
global $wpdb;
$wpdb->insert($wpdb->prefix . 'custom_table', [
    'name' => 'Example',
    'status' => 'active',
]);
```

### Upsert Rows
`DB::table()` supports `upsert()` just like models do — useful for inserting new rows or updating existing ones by a unique key in a single query:
```php
DB::table('custom_table')->upsert([
    ['email' => 'a@test.com', 'name' => 'Alice', 'votes' => 1],
    ['email' => 'b@test.com', 'name' => 'Bob', 'votes' => 2],
], ['email'], ['name', 'votes']);
```

### Delete Rows
```php
DB::table('custom_table')->where('status', 'inactive')->delete();
```

### Derived Tables (Subquery as FROM)

Pass a `Closure` or `QueryBuilder` as the first argument, and an alias string as the second argument, to query a derived table (subquery) instead of a physical table. All regular query builder methods work against the alias.

```php
use MJ\WPORM\DB;

// Closure form — aggregate orders per user, then filter/sort the result
$result = DB::table(function($q) {
    $q->from('orders')
      ->select(['user_id', 'COUNT(*) as order_count', 'SUM(total) as revenue'])
      ->groupBy('user_id');
}, 'order_stats')
->where('revenue', '>', 500)
->orderBy('revenue', 'desc')
->get();

// QueryBuilder form — reuse an existing query as a derived table
$sub = DB::table('orders')
    ->select(['user_id', 'SUM(total) as revenue'])
    ->groupBy('user_id');

$topSpenders = DB::table($sub, 'totals')
    ->where('revenue', '>', 1000)
    ->orderBy('revenue', 'desc')
    ->limit(10)
    ->get();
```

> A string alias is **required** when a Closure or QueryBuilder is passed — `DB::table()` will throw `\InvalidArgumentException` if omitted.

## Notes
- `DB::table($table)` (plain string) returns a `QueryBuilder` for the named table. `DB::table($queryOrClosure, $alias)` returns a `QueryBuilder` whose FROM clause is the compiled subquery aliased as `$alias`.
- All standard query builder methods work on both forms: `where`, `whereIn`, `whereSub`, `whereInSub`, `selectSub`, `update`, `upsert`, `get`, `delete`, `count`, `paginate`, `orderBy`, `limit`, etc.
- The underlying anonymous model includes `timestamps`, `softDeletes`, `fillable`, `createdAtColumn`, and `updatedAtColumn` properties (all disabled/empty by default) so features that read them work without errors. Timestamps and soft deletes are off unless you use a real model.
- No model events, attribute casting, or relationships are available when using `DB::table()`.
- Table names passed as strings are **not** automatically prefixed; provide the full table name (e.g. `$wpdb->prefix . 'posts'`) or the bare name if WPORM prefixing is not needed for that table.

## When to Use
- For quick queries on tables without a model class.
- For migrations, maintenance scripts, or admin utilities.
- When you do not need model features like casting, events, or relationships.
