# DB::table() - Raw Table Query Builder

WPORM provides a static `DB::table()` method for running queries on any database table, similar to Laravel's Eloquent. This is useful for working with tables that do not have a dedicated model class, or for quick, direct queries.

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

## Notes
- The `DB::table()` method returns a `QueryBuilder` instance for the specified table.
- You can use all standard query builder methods: `where`, `whereIn`, `update`, `upsert`, `get`, `delete`, etc.
- The table behind `DB::table()` includes `timestamps`, `softDeletes`, `fillable`, `createdAtColumn`, and `updatedAtColumn` properties (disabled/empty by default) so query builder features that read them — like `upsert()`'s automatic timestamp handling, or soft-delete scoping — work without errors. Timestamps and soft deletes are off unless you opt in by using a real model instead.
- No model events, attribute casting, or relationships are available when using `DB::table()`.
- Table names are not automatically prefixed; provide the full table name as needed.

## When to Use
- For quick queries on tables without a model class.
- For migrations, maintenance scripts, or admin utilities.
- When you do not need model features like casting, events, or relationships.
