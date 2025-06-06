# WPORM - Lightweight WordPress ORM

WPORM is a lightweight Object-Relational Mapping (ORM) library for WordPress plugins. It provides an Eloquent-like API for defining models, querying data, and managing database schema, all while leveraging WordPress's native `$wpdb` database layer.

## Features
- **Model-based data access**: Define models for your tables and interact with them using PHP objects.
- **Schema management**: Create and modify tables using a fluent schema builder.
- **Query builder**: Chainable query builder for flexible and safe SQL queries.
- **Attribute casting**: Automatic type casting for model attributes.
- **Relationships**: Define `hasOne`, `hasMany`, `belongsTo`, `belongsToMany`, and `hasManyThrough` relationships.
- **Events**: Hooks for model lifecycle events (creating, updating, deleting).
- **Global scopes**: Add global query constraints to models.

## Installation
1. Place the `ORM` directory in your plugin folder.
2. Include the ORM in your plugin bootstrap:

```php
require_once __DIR__ . '/ORM/Helpers.php';
require_once __DIR__ . '/ORM/Model.php';
require_once __DIR__ . '/ORM/QueryBuilder.php';
require_once __DIR__ . '/ORM/Blueprint.php';
require_once __DIR__ . '/ORM/SchemaBuilder.php';
require_once __DIR__ . '/ORM/ColumnDefinition.php';
```

## Defining a Model
Create a model class extending `WPORM\Model`:

```php
use WPORM\Model;
use WPORM\Schema\Blueprint;

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
        $this->schema = $blueprint->toSql();
    }
}
```

## Schema Management
Create or update tables using the model's `up` method and the `SchemaBuilder`:

```php
use WPORM\Schema\SchemaBuilder;

$schema = new SchemaBuilder($wpdb);
$schema->create('parts', function($table) {
    $table->id();
    $table->integer('part_id');
    $table->integer('product_id');
    $table->integer('qty');
    $table->index('product_id');
});
```

## Basic Usage
### Creating a Record
```php
$part = new Parts(['part_id' => 1, 'product_id' => 2, 'qty' => 10]);
$part->save();
```

### Querying Records
```php
// Get all parts
$all = Parts::all();

// Find by primary key
$part = Parts::find(1);

// Where clause
$parts = Parts::query()->where('qty', '>', 5)->orderBy('qty', 'desc')->get();

// First result
$first = Parts::query()->where('product_id', 2)->first();
```

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

## Attribute Casting
Add a `$casts` property to your model:
```php
protected $casts = [
    'qty' => 'int',
    'meta' => 'json',
];
```

## Relationships
```php
// In Product model
public function parts() {
    return $this->hasMany(Parts::class, 'product_id');
}

// In Parts model
public function product() {
    return $this->belongsTo(Product::class, 'product_id');
}
```

## Custom Attribute Accessors/Mutators
```php
public function getQtyAttribute() {
    return $this->attributes['qty'] * 2;
}

public function setQtyAttribute($value) {
    $this->attributes['qty'] = $value / 2;
}
```

## Transactions
```php
Parts::query()->beginTransaction();
// ...
Parts::query()->commit();
// or
Parts::query()->rollBack();
```

## Custom Queries
You can execute custom SQL queries using the underlying `$wpdb` instance or by extending the model/query builder. For example:

```php
// Using the query builder for a custom select
$results = Parts::query()
    ->select(['part_id', 'SUM(qty) as total_qty'])
    ->where('product_id', 2)
    ->orderBy('total_qty', 'desc')
    ->get();

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

## Extending/Improving
- Add more casts by implementing `WPORM\Casts\CastableInterface`.
- Add more schema types in `Blueprint` as needed.
- Add more model events as needed.

## License
MIT
