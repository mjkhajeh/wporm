# WPORM Blueprint Column Types Documentation

This document lists all column types supported by the `Blueprint` class in WPORM, with a title, description, and a simple usage example for each. Use these methods in your migration or schema definition to add columns to your tables.

---

## String Types

### string($column, $length = 255)
**Description:** Adds a variable-length string (VARCHAR) column.
**Example:**
```php
$table->string('name');
```

### text($column)
**Description:** Adds a TEXT column for long-form text.
**Example:**
```php
$table->text('description');
```

### longText($column)
**Description:** Adds a LONGTEXT column for very large text data.
**Example:**
```php
$table->longText('content');
```

### mediumText($column)
**Description:** Adds a MEDIUMTEXT column for medium-length text data.
**Example:**
```php
$table->mediumText('summary');
```

### tinyText($column)
**Description:** Adds a TINYTEXT column for small text data.
**Example:**
```php
$table->tinyText('note');
```

### char($column, $length = 1)
**Description:** Adds a fixed-length CHAR column.
**Example:**
```php
$table->char('code', 4);
```

---

## Integer Types

### integer($column)
**Description:** Adds an INT column.
**Example:**
```php
$table->integer('age');
```

### bigInteger($column)
**Description:** Adds a BIGINT column.
**Example:**
```php
$table->bigInteger('views');
```

### smallInteger($column)
**Description:** Adds a SMALLINT column.
**Example:**
```php
$table->smallInteger('rank');
```

### mediumInteger($column)
**Description:** Adds a MEDIUMINT column.
**Example:**
```php
$table->mediumInteger('score');
```

### tinyInteger($column)
**Description:** Adds a TINYINT column.
**Example:**
```php
$table->tinyInteger('flag');
```

### unsignedInteger($column)
**Description:** Adds an unsigned INT column.
**Example:**
```php
$table->unsignedInteger('count');
```

### unsignedBigInteger($column)
**Description:** Adds an unsigned BIGINT column.
**Example:**
```php
$table->unsignedBigInteger('total');
```

### unsignedSmallInteger($column)
**Description:** Adds an unsigned SMALLINT column.
**Example:**
```php
$table->unsignedSmallInteger('level');
```

### unsignedTinyInteger($column)
**Description:** Adds an unsigned TINYINT column.
**Example:**
```php
$table->unsignedTinyInteger('status');
```

### unsignedMediumInteger($column)
**Description:** Adds an unsigned MEDIUMINT column.
**Example:**
```php
$table->unsignedMediumInteger('points');
```

---

## Auto-Increment & Primary Key Types

### increments($column)
**Description:** Adds an auto-incrementing INT UNSIGNED primary key column.
**Example:**
```php
$table->increments('id');
```

### bigIncrements($column)
**Description:** Adds an auto-incrementing BIGINT UNSIGNED primary key column.
**Example:**
```php
$table->bigIncrements('id');
```

### smallIncrements($column)
**Description:** Adds an auto-incrementing SMALLINT UNSIGNED primary key column.
**Example:**
```php
$table->smallIncrements('id');
```

### mediumIncrements($column)
**Description:** Adds an auto-incrementing MEDIUMINT UNSIGNED primary key column.
**Example:**
```php
$table->mediumIncrements('id');
```

### tinyIncrements($column)
**Description:** Adds an auto-incrementing TINYINT UNSIGNED primary key column.
**Example:**
```php
$table->tinyIncrements('id');
```

### unsignedBigIncrements($column)
**Description:** Adds an auto-incrementing BIGINT UNSIGNED primary key column.
**Example:**
```php
$table->unsignedBigIncrements('id');
```

### unsignedSmallIncrements($column)
**Description:** Adds an auto-incrementing SMALLINT UNSIGNED primary key column.
**Example:**
```php
$table->unsignedSmallIncrements('id');
```

### unsignedTinyIncrements($column)
**Description:** Adds an auto-incrementing TINYINT UNSIGNED primary key column.
**Example:**
```php
$table->unsignedTinyIncrements('id');
```

### unsignedIntegerIncrements($column)
**Description:** Adds an auto-incrementing INT UNSIGNED primary key column.
**Example:**
```php
$table->unsignedIntegerIncrements('id');
```

### unsignedIntegerBigIncrements($column)
**Description:** Adds an auto-incrementing BIGINT UNSIGNED primary key column.
**Example:**
```php
$table->unsignedIntegerBigIncrements('id');
```

### unsignedIntegerMediumIncrements($column)
**Description:** Adds an auto-incrementing MEDIUMINT UNSIGNED primary key column.
**Example:**
```php
$table->unsignedIntegerMediumIncrements('id');
```

---

## Boolean Type

### boolean($column)
**Description:** Adds a TINYINT(1) column for boolean values.
**Example:**
```php
$table->boolean('is_active');
```

---

## Floating Point & Decimal Types

### float($column, $precision = 8, $scale = 2)
**Description:** Adds a FLOAT column with precision and scale.
**Example:**
```php
$table->float('price', 8, 2);
```

### double($column, $precision = 16, $scale = 8)
**Description:** Adds a DOUBLE column with precision and scale.
**Example:**
```php
$table->double('amount', 16, 8);
```

### decimal($column, $precision = 16, $scale = 2)
**Description:** Adds a DECIMAL column with precision and scale.
**Example:**
```php
$table->decimal('balance', 16, 2);
```

---

## Date & Time Types

### date($column)
**Description:** Adds a DATE column.
**Example:**
```php
$table->date('birthdate');
```

### time($column)
**Description:** Adds a TIME column.
**Example:**
```php
$table->time('start_time');
```

### datetime($column)
**Description:** Adds a DATETIME column.
**Example:**
```php
$table->datetime('created_at');
```

### datetimeTz($column)
**Description:** Adds a DATETIME WITH TIME ZONE column.
**Example:**
```php
$table->datetimeTz('event_time');
```

### timestamp($column)
**Description:** Adds a TIMESTAMP column.
**Example:**
```php
$table->timestamp('updated_at');
```

### timestampTz($column)
**Description:** Adds a TIMESTAMP WITH TIME ZONE column.
**Example:**
```php
$table->timestampTz('expires_at');
```

### timestampTzWithDefault($column)
**Description:** Adds a TIMESTAMP WITH TIME ZONE column with default CURRENT_TIMESTAMP.
**Example:**
```php
$table->timestampTzWithDefault('created_at');
```

### timestampWithDefault($column)
**Description:** Adds a TIMESTAMP column with default CURRENT_TIMESTAMP.
**Example:**
```php
$table->timestampWithDefault('created_at');
```

### dateTimeWithDefault($column)
**Description:** Adds a DATETIME column with default CURRENT_TIMESTAMP.
**Example:**
```php
$table->dateTimeWithDefault('created_at');
```

### dateTimeTzWithDefault($column)
**Description:** Adds a DATETIME WITH TIME ZONE column with default CURRENT_TIMESTAMP.
**Example:**
```php
$table->dateTimeTzWithDefault('created_at');
```

### dateTimeTzWithDefaultCurrentOnUpdate($column)
**Description:** Adds a DATETIME WITH TIME ZONE column with default CURRENT_TIMESTAMP and ON UPDATE CURRENT_TIMESTAMP.
**Example:**
```php
$table->dateTimeTzWithDefaultCurrentOnUpdate('updated_at');
```

### dateTimeWithDefaultCurrentOnUpdate($column)
**Description:** Adds a DATETIME column with default CURRENT_TIMESTAMP and ON UPDATE CURRENT_TIMESTAMP.
**Example:**
```php
$table->dateTimeWithDefaultCurrentOnUpdate('updated_at');
```

### timeTz($column)
**Description:** Adds a TIME WITH TIME ZONE column.
**Example:**
```php
$table->timeTz('event_time');
```

### timeTzWithDefault($column)
**Description:** Adds a TIME WITH TIME ZONE column with default CURRENT_TIMESTAMP.
**Example:**
```php
$table->timeTzWithDefault('event_time');
```

### timeTzWithDefaultCurrentOnUpdate($column)
**Description:** Adds a TIME WITH TIME ZONE column with default CURRENT_TIMESTAMP and ON UPDATE CURRENT_TIMESTAMP.
**Example:**
```php
$table->timeTzWithDefaultCurrentOnUpdate('event_time');
```

### timeWithDefault($column)
**Description:** Adds a TIME column with default CURRENT_TIMESTAMP.
**Example:**
```php
$table->timeWithDefault('event_time');
```

### year($column)
**Description:** Adds a YEAR column.
**Example:**
```php
$table->year('graduation_year');
```

---

## Binary & JSON Types

### binary($column)
**Description:** Adds a BLOB column for binary data.
**Example:**
```php
$table->binary('data');
```

### binaryText($column)
**Description:** Adds a BINARY column for binary data.
**Example:**
```php
$table->binaryText('data');
```

### mediumBinaryText($column)
**Description:** Adds a MEDIUMBLOB column for medium binary data.
**Example:**
```php
$table->mediumBinaryText('data');
```

### longBinaryText($column)
**Description:** Adds a LONGBLOB column for large binary data.
**Example:**
```php
$table->longBinaryText('data');
```

### json($column)
**Description:** Adds a JSON column.
**Example:**
```php
$table->json('meta');
```

### jsonb($column)
**Description:** Adds a JSONB column (for PostgreSQL compatibility).
**Example:**
```php
$table->jsonb('meta');
```

---

## Special Types

### enum($column, $values)
**Description:** Adds an ENUM column with allowed values.
**Example:**
```php
$table->enum('status', ['draft', 'published', 'archived']);
```

### set($column, $values)
**Description:** Adds a SET column with allowed values.
**Example:**
```php
$table->set('tags', ['news', 'tech', 'sports']);
```

### ipAddress($column)
**Description:** Adds a VARCHAR(45) column for IP addresses.
**Example:**
```php
$table->ipAddress('ip');
```

### macAddress($column)
**Description:** Adds a VARCHAR(17) column for MAC addresses.
**Example:**
```php
$table->macAddress('mac');
```

### uuid($column)
**Description:** Adds a CHAR(36) column for UUIDs.
**Example:**
```php
$table->uuid('uuid');
```

### uuidBinary($column)
**Description:** Adds a BINARY(16) column for binary UUIDs.
**Example:**
```php
$table->uuidBinary('uuid_bin');
```

### binaryUuid($column)
**Description:** Adds a BINARY(16) column for binary UUIDs.
**Example:**
```php
$table->binaryUuid('uuid_bin');
```

### ulid($column)
**Description:** Adds a CHAR(26) column for ULIDs.
**Example:**
```php
$table->ulid('ulid');
```

### point($column)
**Description:** Adds a POINT column for spatial data.
**Example:**
```php
$table->point('location');
```

### polygon($column)
**Description:** Adds a POLYGON column for spatial data.
**Example:**
```php
$table->polygon('area');
```

### geography($column)
**Description:** Adds a GEOGRAPHY column for spatial data.
**Example:**
```php
$table->geography('region');
```

---

## Foreign Key Types

### foreignId($column, $refTable, $refColumn = 'id', $onDelete = 'CASCADE', $onUpdate = 'CASCADE')
**Description:** Adds a BIGINT UNSIGNED column and a foreign key constraint.
**Example:**
```php
$table->foreignId('user_id', 'users');
```

### foreignIdFor($column, $refTable, $refColumn = 'id', $onDelete = 'CASCADE', $onUpdate = 'CASCADE')
**Description:** Alias for `foreignId()`.
**Example:**
```php
$table->foreignIdFor('post_id', 'posts');
```

### foreignUlid($column, $refTable, $refColumn = 'id', $onDelete = 'CASCADE', $onUpdate = 'CASCADE')
**Description:** Adds a CHAR(26) column and a foreign key constraint for ULIDs.
**Example:**
```php
$table->foreignUlid('ulid', 'other_table');
```

### foreignUuid($column, $refTable, $refColumn = 'id', $onDelete = 'CASCADE', $onUpdate = 'CASCADE')
**Description:** Adds a CHAR(36) column and a foreign key constraint for UUIDs.
**Example:**
```php
$table->foreignUuid('uuid', 'other_table');
```

---

## Helper Types

### id($column = 'id')
**Description:** Adds an auto-incrementing primary key column named 'id'.
**Example:**
```php
$table->id();
```

---

## Timestamps & Soft Deletes

### timestamps()
**Description:** Adds `created_at` and `updated_at` TIMESTAMP columns.
**Example:**
```php
$table->timestamps();
```

### softDeletes($column = 'deleted_at')
**Description:** Adds a nullable DATETIME column for soft deletes (Eloquent-style shortcut). Use this for enabling soft deletes on your model.
**Example:**
```php
$table->softDeletes(); // Adds 'deleted_at' DATETIME NULL
$table->softDeletes('removed_at'); // Adds 'removed_at' DATETIME NULL
```

### softDeletesTz()
**Description:** Adds a nullable `deleted_at` TIMESTAMP WITH TIME ZONE column for soft deletes.
**Example:**
```php
$table->softDeletesTz();
```

---

For more advanced usage, see the main `Readme.md` and `Methods.md`.
