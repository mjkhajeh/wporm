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
```php
DB::table('custom_table')->insert([
    'name' => 'Example',
    'status' => 'active',
]);
```

### Delete Rows
```php
DB::table('custom_table')->where('status', 'inactive')->delete();
```

## Notes
- The `DB::table()` method returns a `QueryBuilder` instance for the specified table.
- You can use all standard query builder methods: `where`, `whereIn`, `update`, `get`, `delete`, etc.
- No model events, attribute casting, or relationships are available when using `DB::table()`.
- Table names are not automatically prefixed; provide the full table name as needed.

## When to Use
- For quick queries on tables without a model class.
- For migrations, maintenance scripts, or admin utilities.
- When you do not need model features like casting, events, or relationships.
