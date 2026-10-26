# SchemaBuilder

`MJ\WPORM\SchemaBuilder` inspects and changes database tables through WordPress's
`$wpdb` connection. It can create tables, alter them with a `Blueprint`, and
inspect tables, views, columns, indexes, and foreign keys. It is a schema helper,
not a migration runner: your plugin or theme decides when and how often to run
schema changes.

For column types, modifiers, indexes, and foreign-key definitions available on
the `Blueprint` callback, see [Blueprint and column types](./Blueprint.md).
For the complete method reference, see [Methods.md](./Methods.md#schemabuilder).

## Create a SchemaBuilder

Pass the active WordPress database connection, or omit it to use the global
`$wpdb`:

```php
use MJ\WPORM\Blueprint;
use MJ\WPORM\SchemaBuilder;

global $wpdb;

$schema = new SchemaBuilder($wpdb);
// Or: $schema = new SchemaBuilder();
```

`SchemaBuilder` uses the connection's `$wpdb->prefix`. For schema methods,
provide a bare table name such as `orders`; an already-prefixed name such as
`wp_orders` is also accepted and will not be prefixed a second time.

## Create a table

Pass a callback that defines columns and constraints on the supplied
`Blueprint`:

```php
$schema->create('parts', function (Blueprint $table): void {
    $table->id();
    $table->string('name', 150);
    $table->unsignedInteger('product_id');
    $table->integer('quantity')->default(1);
    $table->unique('name');
    $table->index(['product_id', 'quantity']);
});
```

`create()` builds a complete `CREATE TABLE` statement using the prefixed table
name and `$wpdb->get_charset_collate()`, then passes it to WordPress's
`dbDelta()`. You provide only the table name and its definitions. `dbDelta()`
can create the table or reconcile compatible changes to an existing table; it
does not replace a deliberate migration strategy for every schema change.

The method loads WordPress's `wp-admin/includes/upgrade.php`, so it must run in
a WordPress request where `ABSPATH` is defined. If the callback produces an
empty blueprint, no SQL is issued.

Example activation hook:

```php
register_activation_hook(__FILE__, function (): void {
    $schema = new \MJ\WPORM\SchemaBuilder();

    $schema->create('parts', function (\MJ\WPORM\Blueprint $table): void {
        $table->id();
        $table->string('name');
    });
});
```

## Alter a table

Use `table()` to define additions, modifications, indexes, foreign keys, or
drop/rename-column commands:

```php
use MJ\WPORM\Blueprint;

$schema->table('parts', function (Blueprint $table): void {
    $table->string('sku', 64)->nullable();
    $table->index('sku');
});
```

Each column becomes an `ALTER TABLE ... ADD` or `MODIFY` statement. Indexes,
foreign keys, and drop-column commands are emitted as separate alterations.
`table()` executes the generated statements in order and throws a
`\RuntimeException` if one fails. It does not wrap them in a transaction.

### Modify an existing column

Call `change()` on the column definition to generate a `MODIFY` statement:

```php
$schema->table('parts', function (Blueprint $table): void {
    $table->string('name', 150)
        ->nullable()
        ->default('Unnamed part')
        ->change();
});
```

`change()` describes the column's complete new definition. Repeat each
attribute you want to retain, such as `nullable()`, `default()`, or
`autoIncrement()`; attributes omitted from the new definition may be removed.
Index changes are separate from column changes.

### Position new columns

Use the column modifier `after()` or the Blueprint callback form to place
columns on MySQL or MariaDB:

```php
$schema->table('users', function (Blueprint $table): void {
    $table->string('nickname')->nullable()->after('display_name');

    $table->after('password', function (Blueprint $table): void {
        $table->string('address_line1');
        $table->string('address_line2');
        $table->string('city');
    });
});
```

The callback form chains each new column after the preceding declared column.
See [Blueprint's `after()` documentation](./Blueprint.md#aftercolumn).

## Inspect schema

All inspection methods accept bare or already-prefixed table names. Column
lookups are case-insensitive.

```php
if ($schema->hasTable('users') && $schema->hasColumn('users', 'email')) {
    $type = $schema->getColumnType('users', 'email');
}

$columnNames = $schema->getColumnListing('users');
$columns = $schema->getColumns('users');

$indexNames = $schema->getIndexListing('users');
$indexes = $schema->getIndexes('users');

$foreignKeys = $schema->getForeignKeys('orders');
```

### Tables and views

- `hasTable($table)` returns whether the named object is a base table.
- `hasView($view)` returns whether the named object is a view.

A view is not considered a table by `hasTable()`.

### Columns

- `hasColumn($table, $column)` checks for one column.
- `hasColumns($table, $columns)` checks whether every requested column exists.
  An empty list returns `true`.
- `getColumnListing($table)` returns column names in database ordinal order.
- `getColumnType($table, $column)` returns the normalized data type, such as
  `varchar`.
- `getColumnType($table, $column, true)` returns the full type definition,
  such as `varchar(150)`.
- `getColumns($table)` returns an ordered list of column metadata.

`getColumnType()` throws `\InvalidArgumentException` when the requested column
does not exist. Each item returned by `getColumns()` has this shape:

```php
[
    'name' => 'email',
    'type' => 'varchar(255)',       // Full database type, lowercased.
    'type_name' => 'varchar',       // Normalized type name, lowercased.
    'collation' => 'utf8mb4_unicode_ci', // May be null.
    'nullable' => false,
    'default' => null,              // Database default; may be null.
    'auto_increment' => false,
    'comment' => null,              // Empty comments are returned as null.
    'generation' => null,           // Or ['type' => 'virtual'|'stored',
                                    //     'expression' => '...'].
]
```

### Indexes

- `getIndexes($table)` returns index metadata.
- `getIndexListing($table)` returns index names.
- `hasIndex($table, $index, $type = null)` checks an index by name or columns.

An index can be matched by its case-insensitive name or by its exact ordered
column list. When matching columns, order matters. The optional type accepts
`primary`, `unique`, or the database index type (for example, `btree`).
`getIndexes()` returns entries with `name` (lowercased), `columns`, `type`
(lowercased), `unique`, and `primary`:

```php
$schema->hasIndex('users', 'email_unique', 'unique');
$schema->hasIndex('users', ['tenant_id', 'email'], 'unique');

$indexes = $schema->getIndexes('users');
// Example entry:
[
    'name' => 'email_unique',
    'columns' => ['email'],
    'type' => 'btree',
    'unique' => true,
    'primary' => false,
]
```

### Foreign keys

`getForeignKeys($table)` returns one entry per foreign-key constraint, with
local and referenced columns kept in their declared order:

```php
[
    'name' => 'orders_user_id_foreign',
    'columns' => ['user_id'],
    'foreign_schema' => 'wordpress',
    'foreign_table' => 'wp_users',
    'foreign_columns' => ['ID'],
    'on_update' => 'cascade',
    'on_delete' => 'cascade',
]
```

## Conditional schema changes

The `whenTable...` helpers call `table()` only when their condition is met. The
callback receives a `Blueprint`; if its condition is false, no alteration is
run.

```php
$schema->whenTableDoesntHaveColumn('users', 'nickname', function (Blueprint $table): void {
    $table->string('nickname')->nullable();
});

$schema->whenTableHasColumn('users', 'legacy_email', function (Blueprint $table): void {
    $table->dropColumn('legacy_email');
});

$schema->whenTableDoesntHaveIndex('users', ['email'], function (Blueprint $table): void {
    $table->unique('email', 'users_email_unique');
});

$schema->whenTableHasIndex('users', ['legacy_code'], function (Blueprint $table): void {
    $table->dropColumn('legacy_code');
}, 'unique');
```

For index conditions, `$index` may be an index name or an exact ordered list of
columns; the optional type follows the same rules as `hasIndex()`.

## Drop and rename

```php
$schema->dropIfExists('temporary_records');
$schema->dropColumns('users', ['nickname', 'legacy_email']);
$schema->rename('old_parts', 'parts');
```

- `dropIfExists($table)` is an alias for `drop($table)`. `drop()` issues
  `DROP TABLE IF EXISTS`.
- `dropColumns($table, $columns)` accepts one column name or an array and
  applies the drops through `table()`.
- `rename($from, $to)` renames a table; both names may be bare or already
  prefixed.

These are destructive operations. Validate names and test schema changes on a
staging database before running them against production. Unlike `create()` and
`table()`, `drop()` and `rename()` do not check the query result or surface
`$wpdb->last_error` themselves.

## Errors and database support

Schema inspection queries throw `\RuntimeException` if `$wpdb` reports an
error or does not return an array. `getColumnType()` throws
`\InvalidArgumentException` for an unknown column. `create()` and `table()`
surface database failures as `\RuntimeException`; inspect `$wpdb->last_error`
for the database message.

Schema inspection reads the current database's `information_schema`. Creation
uses WordPress `dbDelta()`; alterations use MySQL/MariaDB `ALTER TABLE`
statements. Test schema operations against the database versions supported by
your WordPress installation.