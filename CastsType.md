# WPORM Cast Types Documentation

This document lists all supported cast types in WPORM, with a title and description for each. Casts allow you to automatically convert attribute values to and from specific data types when getting or setting them on your model.

---

## Built-in Cast Types

### int / integer
**Description:**
Casts the attribute to an integer when accessed and ensures it is stored as an integer in the database.

---

### float / double
**Description:**
Casts the attribute to a floating-point number when accessed and ensures it is stored as a float in the database.

---

### bool / boolean
**Description:**
Casts the attribute to a boolean (`true` or `false`) when accessed and ensures it is stored as a boolean-compatible value in the database.

---

### array
**Description:**
Casts the attribute to an array when accessed. When saving, the array is JSON-encoded for storage in the database.

---

### json
**Description:**
Casts the attribute to an array when accessed (by decoding JSON). When saving, the value is JSON-encoded. Useful for storing structured data.

---

### datetime
**Description:**
Casts the attribute to a `DateTime` object when accessed. When saving, the value is formatted as a MySQL datetime string (`Y-m-d H:i:s`).

---

### timestamp
**Description:**
Casts the attribute to a `DateTime` object (from a Unix timestamp) when accessed. When saving, the value is stored as a Unix timestamp (integer).

---

## Custom Casts

### Class-based Casts
**Description:**
You can define your own cast class by implementing the `MJ\WPORM\Casts\CastableInterface`. This allows for custom logic when getting and setting attribute values.

**Example:**
```php
use MJ\WPORM\Casts\CastableInterface;

class MyCustomCast implements CastableInterface {
    public function get($value) {
        // Custom logic for getting
        return ...;
    }
    public function set($value) {
        // Custom logic for setting
        return ...;
    }
}
```

**Usage:**
```php
protected $casts = [
    'my_field' => MyCustomCast::class,
];
```

---

## Notes
- Casts are defined in your model's `$casts` property.
- If a cast type is not recognized, the attribute is returned as-is.
- For more advanced usage, see the main `Readme.md` and `Methods.md`.
