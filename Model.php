<?php
namespace MJ\WPORM;

/**
 * Class Model
 *
 * @method string getTable()   Get the table name for the model instance
 * @method static string tableName()   Get the table name for the model statically
 */
abstract class Model implements \ArrayAccess {
	protected $table;
	protected $primaryKey = 'id';
	protected $fillable = [];
	protected $guarded = ['id'];
	protected $schema = '';
	protected $casts = [];
	protected $timestamps = true;
	protected $attributes = [];
	protected $original = [];
	protected $exists = false;
	protected static $booted = [];
	protected static $globalScopes = [];
	protected $createdAtColumn = 'created_at';
	protected $updatedAtColumn = 'updated_at';
    protected $_eagerLoaded = [];
	protected $softDeletes = false;
	protected $deletedAtColumn = 'deleted_at';

    /**
     * Get the deleted_at column as a DateTime instance (if set and not null).
     * @return \DateTimeInterface|null
     */
    public function getDeletedAtAttribute() {
        $column = $this->deletedAtColumn;
        $value = $this->attributes[$column] ?? null;
        if ($value) {
            try {
                return new \DateTime($value);
            } catch (\Exception $e) {
                return null;
            }
        }
        return null;
    }

    /**
     * Set the deleted_at column from a DateTime, timestamp, or string.
     * @param \DateTimeInterface|int|string|null $value
     * @return void
     */
    public function setDeletedAtAttribute($value) {
        $column = $this->deletedAtColumn;
        if ($value instanceof \DateTimeInterface) {
            $this->attributes[$column] = $value->format('Y-m-d H:i:s');
        } elseif (is_numeric($value)) {
            $this->attributes[$column] = date('Y-m-d H:i:s', (int)$value);
        } elseif (is_string($value)) {
            $this->attributes[$column] = date('Y-m-d H:i:s', strtotime($value));
        } elseif ($value === null) {
            $this->attributes[$column] = null;
        }
    }

	// Register a global scope
	public static function addGlobalScope($identifier, callable $scope) {
		static::$globalScopes[static::class][$identifier] = $scope;
	}

	// Remove a global scope
	public static function removeGlobalScope($identifier) {
		unset(static::$globalScopes[static::class][$identifier]);
	}

	// Get all global scopes for this model
	public static function getGlobalScopes() {
		return static::$globalScopes[static::class] ?? [];
	}

	// Apply global scopes to a query builder
	public static function applyGlobalScopes(QueryBuilder $query) {
		foreach (static::getGlobalScopes() as $scope) {
			$scope($query);
		}
		return $query;
	}

	public function __construct(array $attributes = []) {
		global $wpdb;
		static $tableChecked = [];
		$table = $this->getTable();

		static::bootIfNotBooted();

		if (!isset($tableChecked[$table])) {
			$this->up(new Blueprint($table, false, $wpdb));
			$this->createTableIfNotExists();
			$tableChecked[$table] = true;
		}

		// If attributes contain the primary key, fetch from DB
		if (!empty($attributes) && isset($attributes[$this->primaryKey])) {
			$row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE {$this->primaryKey} = %s LIMIT 1",
					$attributes[$this->primaryKey]
				),
				ARRAY_A
			);
			$this->fill($attributes);
			if ($row) {
				$this->original = $row;
				$this->exists = true;
			}
		} else {
			$this->fill($attributes);
		}
	}

	public static function bootIfNotBooted() {
		$class = static::class;
		if (!isset(static::$booted[$class])) {
			if (method_exists($class, 'boot')) {
				forward_static_call([$class, 'boot']);
			}
			static::$booted[$class] = true;
		}
	}

	protected function createTableIfNotExists() {
		global $wpdb;

		if (!property_exists($this, 'schema') || empty($this->schema)) {
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table = $this->getTable();
		if( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) !== $table ) {
			$charsetCollate = $wpdb->get_charset_collate();
	
			$sql = "CREATE TABLE {$table} (
{$this->schema}
) $charsetCollate;";
	
			dbDelta($sql);
		}
	}

	public function up(Blueprint $blueprint) {}

	public function down(SchemaBuilder $schema) {
		global $wpdb;
		$table = $wpdb->prefix . $this->getTable();
		$schema->drop($table);
	}

	public function fill(array $attributes) {
		foreach ($attributes as $key => $value) {
			if (
				(empty($this->fillable) || in_array($key, $this->fillable))
			) {
				$this->__set($key, $value);
			}
		}
		return $this;
	}

	public function __get($key) {
        // Eager loaded relations
        if (isset($this->_eagerLoaded[$key])) {
            return $this->_eagerLoaded[$key];
        }
		$method = 'get' . ucfirst($key) . 'Attribute';
		if (method_exists($this, $method)) {
			return $this->$method();
		}
		if (method_exists($this, $key)) {
			$result = $this->$key();
			// If the relationship method returns a QueryBuilder, resolve it to a Collection
			if ($result instanceof \MJ\WPORM\QueryBuilder) {
				return $result->get();
			}
			return $result;
		}
		// Fix: Use array_key_exists to allow empty values (like 0) to be returned
		if (array_key_exists($key, $this->attributes)) {
			$value = $this->castGet($key, $this->attributes[$key]);
			return $value;
		}
		if (property_exists($this, $key)) {
			return $this->$key;
		}
		return null;
	}

	public function __set($key, $value) {
		$method = 'set' . ucfirst($key) . 'Attribute';
		if (method_exists($this, $method)) {
			return $this->$method($value);
		}
		if (
			(empty($this->fillable) || in_array($key, $this->fillable))
		) {
			$this->attributes[$key] = $this->castSet($key, $value);
		}
	}

	public function __call($method, $parameters) {
        // Proxy query builder methods to QueryBuilder for fluent API
        $query = static::query();
        if (method_exists($query, $method)) {
            return $query->$method(...$parameters);
        }
        // Handle dynamic scopes: scopeXyz()
        if (strpos($method, 'scope') === 0 && method_exists($this, $method)) {
            return $this->$method(...$parameters);
        }
        throw new \BadMethodCallException("Method {$method} does not exist.");
    }

	protected function castGet($key, $value) {
    if (!isset($this->casts[$key])) return $value;
    $cast = $this->casts[$key];
    // Only instantiate if not a built-in type
    switch ($cast) {
        case 'int':
        case 'integer':
            return (int) $value;
        case 'float':
        case 'double':
            return (float) $value;
        case 'bool':
        case 'boolean':
            return (bool) $value;
        case 'array':
            return is_array($value) ? $value : json_decode($value, true);
        case 'json':
            return json_decode($value, true);
        case 'datetime':
            return $value ? new \DateTime($value) : null;
        case 'timestamp':
            return $value ? (new \DateTime())->setTimestamp((int)$value) : null;
        default:
            if (class_exists($cast) && !in_array($cast, ['int','integer','float','double','bool','boolean','array','json','datetime','timestamp'])) {
                $castInstance = new $cast();
                if ($castInstance instanceof \MJ\WPORM\Casts\CastableInterface) {
                    return $castInstance->get($value);
                }
            }
            return $value;
    }
}

protected function castSet($key, $value) {
    if (!isset($this->casts[$key])) return $value;
    $cast = $this->casts[$key];
    // Only instantiate if not a built-in type
    switch ($cast) {
        case 'int':
        case 'integer':
            return (int) $value;
        case 'float':
        case 'double':
            return (float) $value;
        case 'bool':
        case 'boolean':
            return (bool) $value;
        case 'array':
        case 'json':
            return json_encode($value);
        case 'datetime':
            if ($value instanceof \DateTime) {
                return $value->format('Y-m-d H:i:s');
            } elseif (is_numeric($value)) {
                return date('Y-m-d H:i:s', (int)$value);
            } elseif (is_string($value)) {
                return date('Y-m-d H:i:s', strtotime($value));
            }
            return $value;
        case 'timestamp':
            return $value instanceof \DateTime ? $value->getTimestamp() : (is_numeric($value) ? (int)$value : strtotime($value));
        default:
            if (class_exists($cast) && !in_array($cast, ['int','integer','float','double','bool','boolean','array','json','datetime','timestamp'])) {
                $castInstance = new $cast();
                if ($castInstance instanceof \MJ\WPORM\Casts\CastableInterface) {
                    return $castInstance->set($value);
                }
            }
            return $value;
    }
}

	/**
	 * Called after a model is retrieved from the database (get/first/find).
	 * Override in your model to add custom logic.
	 *
	 * @return void
	 */
	protected function retrieved() {}

    /**
     * Called before a model is soft deleted (softDeletes only).
     * Override in your model to add custom logic.
     * @return void
     */
    protected function softDeleting() {}

    /**
     * Called after a model is soft deleted (softDeletes only).
     * Override in your model to add custom logic.
     * @return void
     */
    protected function softDeleted() {}

    /**
     * Called before a model is restored from soft delete.
     * Override in your model to add custom logic.
     * @return void
     */
    protected function restoring() {}

    /**
     * Called after a model is restored from soft delete.
     * Override in your model to add custom logic.
     * @return void
     */
    protected function restored() {}

	public static function query($applyGlobalScopes = true) {
		$instance = new static;
		$query = new \MJ\WPORM\QueryBuilder($instance, $applyGlobalScopes);
		if ($instance->softDeletes && !$query->withTrashed && !$query->onlyTrashed) {
			$query->whereNull($instance->deletedAtColumn);
		}
		return $query;
	}

	public static function newQuery($applyGlobalScopes = true) {
		return static::query($applyGlobalScopes);
	}

	public static function all() {
		$results = static::query()->get();
		foreach ($results as $instance) {
			if (method_exists($instance, 'retrieved')) {
				$instance->retrieved();
			}
		}
		return $results;
	}

	public static function find($id) {
		$instance = new static;
		$pk = $instance->primaryKey;
		$result = static::query()->where($pk, $id)->first();
		if ($result && method_exists($result, 'retrieved')) {
			$result->retrieved();
		}
		return $result;
	}

	// Add a wrapper for triggering retrieved() after get/first
	public static function getWithEvent($query) {
		$results = $query->get();
		foreach ($results as $instance) {
			if (method_exists($instance, 'retrieved')) {
				$instance->retrieved();
			}
		}
		return $results;
	}

	public static function firstWithEvent($query) {
		$instance = $query->first();
		if ($instance && method_exists($instance, 'retrieved')) {
			$instance->retrieved();
		}
		return $instance;
	}

	/**
	 * updateOrCreate: Find a record matching attributes, update it or create a new one.
	 *
	 * @param array $attributes
	 * @param array $values
	 * @param bool $applyGlobalScopes
	 * @return static
	 */
	public static function updateOrCreate(array $attributes, array $values = [], $applyGlobalScopes = true) {
		$instance = static::query($applyGlobalScopes)->where($attributes)->first();
		if ($instance) {
			$instance->fill($values);
			$instance->save();
			return $instance;
		}
		$instance = new static(array_merge($attributes, $values));
		$instance->save();
		return $instance;
	}

	/**
	 * firstOrCreate: Return the first record matching attributes or create it.
	 *
	 * @param array $attributes
	 * @param array $values
	 * @param bool $applyGlobalScopes
	 * @return static
	 */
	public static function firstOrCreate(array $attributes, array $values = [], $applyGlobalScopes = true) {
		$instance = static::query($applyGlobalScopes)->where($attributes)->first();
		if ($instance) {
			return $instance;
		}
		$instance = new static(array_merge($attributes, $values));
		$instance->save();
		return $instance;
	}

	/**
	 * firstOrNew: Return the first record matching attributes or instantiate a new one (not saved).
	 *
	 * @param array $attributes
	 * @param array $values
	 * @param bool $applyGlobalScopes
	 * @return static
	 */
	public static function firstOrNew(array $attributes, array $values = [], $applyGlobalScopes = true) {
		$instance = static::query($applyGlobalScopes)->where($attributes)->first();
		if ($instance) {
			return $instance;
		}
		return new static(array_merge($attributes, $values));
	}

	public function save() {
		return $this->exists ? $this->update() : $this->insert();
	}

	protected function insert() {
		if (method_exists($this, 'creating')) { // Event
			$this->creating();
		}
		global $wpdb;
		if ($this->timestamps) {
			$now = current_time('mysql');
			$this->attributes[$this->createdAtColumn] = $now;
			$this->attributes[$this->updatedAtColumn] = $now;
		}
		$wpdb->insert($this->getTable(), $this->attributes);
		$this->exists = true;
		// Set the correct primary key after insert
		$pk = $this->primaryKey;
		$this->attributes[$pk] = $wpdb->insert_id;
		$this->$pk = $wpdb->insert_id;
		return true;
	}

	protected function update() {
		if (method_exists($this, 'updating')) { // Event
			$this->updating();
		}
		global $wpdb;
		if ($this->timestamps) {
			$this->attributes[$this->updatedAtColumn] = current_time('mysql');
		}
		$pk = $this->primaryKey;
		// Prevent undefined array key warning by checking if PK is set
        if (!isset($this->attributes[$pk]) && isset($this->$pk)) {
            $this->attributes[$pk] = $this->$pk;
        }
        if (!isset($this->attributes[$pk])) {
            // Cannot update without a primary key value
            return false;
        }
		$wpdb->update($this->getTable(), $this->attributes, [$pk => $this->attributes[$pk]]);
		return true;
	}

	public function delete() {
        if ($this->softDeletes) {
            if (method_exists($this, 'softDeleting')) {
                $this->softDeleting();
            }
            global $wpdb;
            $this->attributes[$this->deletedAtColumn] = current_time('mysql');
            $pk = $this->primaryKey;
            $wpdb->update($this->getTable(), [$this->deletedAtColumn => $this->attributes[$this->deletedAtColumn]], [$pk => $this->attributes[$pk]]);
            $this->exists = true;
            if (method_exists($this, 'softDeleted')) {
                $this->softDeleted();
            }
            return true;
        }
		if (method_exists($this, 'deleting')) { // Event
			$this->deleting();
		}
		global $wpdb;
		$wpdb->delete($this->getTable(), [$this->primaryKey => $this->attributes[$this->primaryKey]]);
		$this->exists = false;
		return true;
	}

	public function trashed() {
    return $this->softDeletes && !empty($this->attributes[$this->deletedAtColumn]);
}

public function restore() {
    if ($this->softDeletes && $this->trashed()) {
        if (method_exists($this, 'restoring')) {
            $this->restoring();
        }
        global $wpdb;
        $pk = $this->primaryKey;
        $this->attributes[$this->deletedAtColumn] = null;
        $wpdb->update($this->getTable(), [$this->deletedAtColumn => null], [$pk => $this->attributes[$pk]]);
        $this->exists = true;
        if (method_exists($this, 'restored')) {
            $this->restored();
        }
        return true;
    }
    return false;
}

public function forceDelete() {
    if ($this->softDeletes) {
        global $wpdb;
        $pk = $this->primaryKey;
        $wpdb->delete($this->getTable(), [$pk => $this->attributes[$pk]]);
        $this->exists = false;
        return true;
    }
    return $this->delete();
}

/**
     * Force delete the model and all specified relationships.
     * Usage: $model->forceDeleteWith(['posts', 'comments'])
     *
     * @param array $relations Array of relationship method names to force delete
     * @return bool
     */
    public function forceDeleteWith(array $relations = []) {
        foreach ($relations as $relation) {
            if (method_exists($this, $relation)) {
                $related = $this->$relation();
                if ($related instanceof \MJ\WPORM\Model) {
                    $related->forceDelete();
                } elseif ($related instanceof \MJ\WPORM\Collection) {
                    foreach ($related as $item) {
                        if ($item instanceof \MJ\WPORM\Model) {
                            $item->forceDelete();
                        }
                    }
                }
            }
        }
        return $this->forceDelete();
    }

	/**
	 * Get the table name for the model (static context).
	 * @return string
	 */
	public static function tableName()
	{
		$instance = new static;
		return $instance->getTable();
	}

	/**
	 * Get the table name for the model (instance context).
	 * @return string
	 */
	public function getTable()
	{
		global $wpdb;
		// Only add prefix if not already present
		if (isset($this->table) && strpos($this->table, $wpdb->prefix) === 0) {
			return $this->table;
		}
		if (isset($this->table)) {
			return $wpdb->prefix . $this->table;
		}
		return $wpdb->prefix . strtolower(class_basename(static::class));
	}

	// Relationships
	/**
	 * @template T of Model
	 * @param class-string<T> $related
	 * @param string|null $foreignKey
	 * @param string|null $localKey
	 * @return QueryBuilder<T>
	 */
	public function hasOne($related, $foreignKey = null, $localKey = null) {
		$instance = new $related;
		$foreignKey = $foreignKey ?: strtolower(class_basename(static::class)) . '_id';
		$localKey = $localKey ?: $this->primaryKey;
		return $related::query()->where($foreignKey, $this->$localKey);
	}

	/**
     * @template T of Model
     * @param class-string<T> $related
     * @param string|null $foreignKey
     * @param string|null $localKey
     * @return QueryBuilder<T>
     */
    public function hasMany($related, $foreignKey = null, $localKey = null) {
        $instance = new $related;
        $foreignKey = $foreignKey ?: strtolower(class_basename(static::class)) . '_id';
        $localKey = $localKey ?: $this->primaryKey;
        return $related::query()->where($foreignKey, $this->$localKey);
    }

    /**
     * @template T of Model
     * @param class-string<T> $related
     * @param string|null $pivotTable
     * @param string|null $foreignPivotKey
     * @param string|null $relatedPivotKey
     * @return QueryBuilder<T>
     */
    public function belongsToMany($related, $pivotTable = null, $foreignPivotKey = null, $relatedPivotKey = null) {
        global $wpdb;
        $relatedInstance = new $related;
        $pivotTable = $pivotTable ?: $this->getTable() . '_' . $relatedInstance->getTable();
        $foreignPivotKey = $foreignPivotKey ?: strtolower(class_basename(static::class)) . '_id';
        $relatedPivotKey = $relatedPivotKey ?: strtolower(class_basename($related)) . '_id';
        $relatedTable = $relatedInstance->getTable();
        $primaryKey = $this->primaryKey;
        $query = $related::query();
        $query->join($pivotTable, "$relatedTable.id", '=', "$pivotTable.$relatedPivotKey")
              ->where("$pivotTable.$foreignPivotKey", $this->$primaryKey);
        return $query;
    }

    /**
     * @template T of Model
     * @template Through of Model
     * @param class-string<T> $related
     * @param class-string<Through> $through
     * @param string|null $firstKey
     * @param string|null $secondKey
     * @param string|null $localKey
     * @return QueryBuilder<T>
     */
    public function hasManyThrough($related, $through, $firstKey = null, $secondKey = null, $localKey = null) {
        global $wpdb;
        $throughInstance = new $through;
        $relatedInstance = new $related;
        $firstKey = $firstKey ?: strtolower(class_basename($through)) . '_id';
        $secondKey = $secondKey ?: strtolower(class_basename($related)) . '_id';
        $localKey = $localKey ?: $this->primaryKey;
        $relatedTable = $relatedInstance->getTable();
        $throughTable = $throughInstance->getTable();
        $query = $related::query();
        $query->join($throughTable, "$relatedTable.$secondKey", '=', "$throughTable.id")
              ->where("$throughTable.$firstKey", $this->$localKey);
        return $query;
    }

	/**
     * Define an inverse one-to-one or many relationship (belongsTo).
     *
     * @template T of Model
     * @param class-string<T> $related
     * @param string|null $foreignKey
     * @param string|null $ownerKey
     * @return T|null
     */
    public function belongsTo($related, $foreignKey = null, $ownerKey = null) {
        $instance = new $related;
        $foreignKey = $foreignKey ?: strtolower(class_basename($related)) . '_id';
        $ownerKey = $ownerKey ?: $instance->primaryKey;
        $foreignValue = $this->attributes[$foreignKey] ?? null;
        if ($foreignValue === null) return null;
        return $related::query()->where($ownerKey, $foreignValue)->first();
    }

	public function newFromBuilder(array $attributes) {
		$instance = new static;
		foreach ($attributes as $key => $value) {
			$instance->attributes[$key] = $value;
			// Set the property for the primary key if present
			if ($key === $instance->primaryKey) {
				$instance->{$instance->primaryKey} = $value;
			}
		}
		$instance->original = $attributes;
		$instance->exists = true;
		return $instance;
	}    

	public function toArray() {
        $attributes = [];
        foreach ($this->attributes as $key => $value) {
            $attributes[$key] = $this->castGet($key, $value);
        }
        // Add already eager loaded relations only (avoid infinite loop)
        foreach ($this->_eagerLoaded as $relation => $data) {
            if (is_array($data) || $data instanceof \MJ\WPORM\Collection) {
                $attributes[$relation] = [];
                foreach ($data as $item) {
                    $attributes[$relation][] = method_exists($item, 'toArray') ? $item->toArray() : $item;
                }
            } elseif (is_object($data) && method_exists($data, 'toArray')) {
                $attributes[$relation] = $data->toArray();
            } else {
                $attributes[$relation] = $data;
            }
        }
        return $attributes;
    }

	public function getOriginal($key = null) {
		return $key ? ($this->original[$key] ?? null) : $this->original;
	}

	public function isDirty($attribute = null) {
		$changes = $this->getChanges();
	
		if ($attribute) {
			return array_key_exists($attribute, $changes);
		}
	
		return !empty($changes);
	}

	public function getChanges() {
		$changes = [];
		foreach ($this->attributes as $key => $value) {
			if (!array_key_exists($key, $this->original) || $value !== $this->original[$key]) {
				$changes[$key] = $value;
			}
		}
		return $changes;
	}    

	public function offsetExists($offset): bool {
		return isset($this->attributes[$offset]);
	}

	public function offsetGet($offset): mixed {
		return $this->__get($offset);
	}

	public function offsetSet($offset, $value): void {
		$this->__set($offset, $value);
	}

	public function offsetUnset($offset): void {
		unset($this->attributes[$offset]);
	}

    /**
     * Query scope: Include soft-deleted records in results.
     * Usage: Model::withTrashed()->get()
     * @param \MJ\WPORM\QueryBuilder|null $query
     * @return \MJ\WPORM\QueryBuilder
     */
    public static function withTrashed($query = null) {
        $instance = new static;
        $query = $query ?: static::query();
        if ($instance->softDeletes) {
            $query->withTrashed = true;
        }
        return $query;
    }

    /**
     * Query scope: Only soft-deleted records.
     * Usage: Model::onlyTrashed()->get()
     * @param \MJ\WPORM\QueryBuilder|null $query
     * @return \MJ\WPORM\QueryBuilder
     */
    public static function onlyTrashed($query = null) {
        $instance = new static;
        $query = $query ?: static::query();
        if ($instance->softDeletes) {
            $query->onlyTrashed = true;
            $query->whereNotNull($instance->deletedAtColumn);
        }
        return $query;
    }

    /**
     * Query scope: Exclude soft-deleted records (default behavior).
     * Usage: Model::withoutTrashed()->get()
     * @param \MJ\WPORM\QueryBuilder|null $query
     * @return \MJ\WPORM\QueryBuilder
     */
    public static function withoutTrashed($query = null) {
        $instance = new static;
        $query = $query ?: static::query();
        if ($instance->softDeletes) {
            $query->withTrashed = false;
            $query->onlyTrashed = false;
            $query->whereNull($instance->deletedAtColumn);
        }
        return $query;
    }

    /**
     * Start a query with eager loading (Eloquent-style static with()).
     * Usage: Model::with('relation')->get(), Model::with(['rel1', 'rel2'])->first()
     * @param array|string $relations
     * @return \MJ\WPORM\QueryBuilder
     */
    public static function with($relations) {
        return static::query()->with($relations);
    }

    public function __isset($key) {
        // Eager loaded relations
        if (isset($this->_eagerLoaded[$key])) {
            return true;
        }
        $method = 'get' . ucfirst($key) . 'Attribute';
        if (method_exists($this, $method)) {
            return true;
        }
        if (method_exists($this, $key)) {
            return true;
        }
        if (array_key_exists($key, $this->attributes)) {
            // Allow empty values to be considered set
            return true;
        }
        if (property_exists($this, $key)) {
            return isset($this->$key);
        }
        return false;
    }
}