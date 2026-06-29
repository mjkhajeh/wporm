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

    /**
     * Get the casts array for this model.
     * @return array
     */
    public function getCasts() {
        return $this->casts;
    }

    /**
     * Get whether soft deletes are enabled for this model.
     * @return bool
     */
    public function getSoftDeletes() {
        return $this->softDeletes;
    }

    /**
     * Get the soft delete type ('timestamp' or 'boolean').
     * @return string
     */
    public function getSoftDeleteType() {
        return $this->softDeleteType;
    }

    /**
     * Get the deleted_at column name.
     * @return string
     */
    public function getDeletedAtColumn() {
        return $this->deletedAtColumn;
    }

    /**
     * Get whether timestamps are enabled for this model.
     * @return bool
     */
    public function getTimestamps() {
        return $this->timestamps;
    }

    /**
     * Get the created_at column name.
     * @return string
     */
    public function getCreatedAtColumn() {
        return $this->createdAtColumn ?? 'created_at';
    }

    /**
     * Get the updated_at column name.
     * @return string
     */
    public function getUpdatedAtColumn() {
        return $this->updatedAtColumn ?? 'updated_at';
    }

    /**
     * Get the primary key column name.
     * @return string
     */
    public function getPrimaryKey() {
        return $this->primaryKey ?? 'id';
    }

    /**
     * Set a single eager loaded relation value.
     * @param string $relation
     * @param mixed $value
     */
    public function setEagerLoaded(string $relation, $value) {
        $this->_eagerLoaded[$relation] = $value;
    }

    /**
     * Merge multiple eager loaded relations.
     * @param array $map
     */
    public function setEagerLoadedMany(array $map) {
        foreach ($map as $k => $v) {
            $this->_eagerLoaded[$k] = $v;
        }
    }
	protected $softDeletes = false;
	protected $deletedAtColumn = 'deleted_at';
	protected $appends = [];

    /**
     * Maps model lifecycle event names to listener classes (Eloquent-style
     * $dispatchesEvents). Each key is the lowercase event short-name; the
     * value is a fully-qualified listener class that exposes a handle() method,
     * or any callable understood by EventDispatcher.
     *
     * Supported keys: retrieved, creating, created, updating, updated,
     *                 saving, saved, deleting, deleted,
     *                 softDeleting, softDeleted, restoring, restored.
     *
     * Example:
     *   protected $dispatchesEvents = [
     *       'creating' => \App\Listeners\LogUserCreating::class,
     *       'deleted'  => \App\Listeners\CleanupUserData::class,
     *   ];
     *
     * @var array<string, class-string|callable>
     */
    public $dispatchesEvents = [];

    /**
     * Attributes that should be hidden from toArray()/toJson() output
     * (e.g. passwords, tokens, secrets). Eloquent-style $hidden.
     *
     * @var array
     */
    protected $hidden = [];

    /**
     * If non-empty, ONLY these attributes (plus appended ones not excluded)
     * are included in toArray()/toJson() output. Eloquent-style $visible.
     * When both $visible and $hidden are set, $visible is applied first,
     * then $hidden subtracts from what remains.
     *
     * @var array
     */
    protected $visible = [];

    /**
     * Runtime-only hidden attributes added via makeHidden(), merged with $hidden.
     * @var array
     */
    protected $runtimeHidden = [];

    /**
     * Runtime-only visible attributes added via makeVisible(), subtracted from hidden.
     * @var array
     */
    protected $runtimeVisible = [];

    /**
     * The soft delete type for this model ('timestamp' or 'boolean').
     * 'timestamp' = uses deletedAtColumn (default: deleted_at)
     * 'boolean' = uses a boolean flag column (e.g., deleted)
     *
     * @var string
     */
    protected $softDeleteType = 'timestamp';

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
			// Build the schema through the Blueprint API. up() populates the
			// Blueprint object; we read toSql() from it directly so there is
			// no need for the subclass to manually assign $this->schema.
			$blueprint = new Blueprint($table, false, $wpdb);
			$this->up($blueprint);
			$this->createTableIfNotExists($blueprint);
			$tableChecked[$table] = true;
		}

		$this->fill($attributes);
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

	/**
	 * Create the model's table if it does not already exist.
	 *
	 * Schema is sourced exclusively from the Blueprint that up() built.
	 * The legacy $this->schema string is accepted as a fallback so that
	 * existing models which still assign $this->schema = $blueprint->toSql()
	 * inside up() continue to work without any changes.
	 *
	 * @param Blueprint $blueprint The Blueprint instance passed to up().
	 */
	protected function createTableIfNotExists(Blueprint $blueprint) {
		global $wpdb;

		// Prefer the Blueprint the constructor just populated.
		// Fall back to the legacy $this->schema string for back-compat.
		$schemaSql = $blueprint->toSql();
		if (empty($schemaSql)) {
			$schemaSql = $this->schema ?? '';
		}

		if (empty($schemaSql)) {
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table = $this->getTable();
		if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
			$charsetCollate = $wpdb->get_charset_collate();

			$sql = "CREATE TABLE {$table} (
{$schemaSql}
) $charsetCollate;";

			dbDelta($sql);
			if (!empty($wpdb->last_error)) {
				error_log($wpdb->last_error);
			}
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
			if ($this->isFillableAttribute($key)) {
				$this->__set($key, $value);
			}
		}
		return $this;
	}

	protected function isFillableAttribute($key) {
		if (in_array($key, $this->fillable, true)) {
			return true;
		}

		if ($this->isGuardedAttribute($key)) {
			return false;
		}

		return empty($this->fillable);
	}

	protected function isGuardedAttribute($key) {
		if (empty($this->guarded)) {
			return false;
		}

		return in_array('*', $this->guarded, true) || in_array($key, $this->guarded, true);
	}

	public function __get($key) {
        // Eager loaded relations: always return the cached value (even null means "loaded but empty")
        if (array_key_exists($key, $this->_eagerLoaded)) {
            return $this->_eagerLoaded[$key];
        }
		$method = 'get' . Helpers::convert_to_pascal_case($key) . 'Attribute';
		if (method_exists($this, $method)) {
			return $this->$method();
		}
		if (method_exists($this, $key)) {
			$result = $this->$key();
			// If the relationship method returns a QueryBuilder, resolve it based on context
			if ($result instanceof \MJ\WPORM\QueryBuilder) {
                $context = $result->getRelationContext();
                $type = $context['type'] ?? null;
                // Single-result relations
                if ($type === 'belongsTo' || $type === 'hasOne' || $type === 'morphOne' || $type === 'morphTo') {
                    return $result->first();
                }
                // Collection relations (hasMany, belongsToMany, hasManyThrough, morphMany)
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
		if (!$this->isFillableAttribute($key)) {
			return;
		}

		$this->setAttributeDirectly($key, $value);
	}

	protected function setAttributeDirectly($key, $value) {
		$method = 'set' . Helpers::convert_to_pascal_case($key) . 'Attribute';
		if (method_exists($this, $method)) {
			return $this->$method($value);
		}
		$this->attributes[$key] = $this->castSet($key, $value);
	}

	public function __call($method, $parameters) {
        // Handle dynamic scopes: scopeXyz() — cheap check before building a query
        if (strpos($method, 'scope') === 0 && method_exists($this, $method)) {
            $query = static::query();
            return $this->$method($query, ...$parameters);
        }
        // Proxy query builder methods to QueryBuilder for fluent API
        if (method_exists(\MJ\WPORM\QueryBuilder::class, $method)) {
            $query = static::query();
            return $query->$method(...$parameters);
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
            if (empty($value)) return [];
            return is_array($value) ? $value : json_decode($value, true);
        case 'json':
            if (empty($value)) return [];
            return json_decode($value, true);
        case 'datetime':
            return $value ? new \DateTime($value) : null;
        case 'timestamp':
            return $value ? (new \DateTime())->setTimestamp((int)$value) : null;
        default:
            $cast_input = '';
            if( is_array( $cast ) ) {
                $cast_input = $cast[1];
                $cast = $cast[0];
            }
            if (class_exists($cast) && !in_array($cast, ['int','integer','float','double','bool','boolean','array','json','datetime','timestamp'])) {
                $castInstance = new $cast( $cast_input );
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
            if (empty($value)) return '[]';
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
            $cast_input = '';
            if( is_array( $cast ) ) {
                $cast_input = $cast[1];
                $cast = $cast[0];
            }
            if (class_exists($cast) && !in_array($cast, ['int','integer','float','double','bool','boolean','array','json','datetime','timestamp'])) {
                $castInstance = new $cast( $cast_input );
                if ($castInstance instanceof \MJ\WPORM\Casts\CastableInterface) {
                    return $castInstance->set($value);
                }
            }
            return $value;
    }
}

	/**
	 * Called after a model is retrieved from the database (get/first/find).
	 * Override in your model to add custom logic. The base implementation fires
	 * the 'retrieved' event via $dispatchesEvents / EventDispatcher automatically.
	 *
	 * @return void
	 */
	public function retrieved() {
		$this->fireModelEvent('retrieved');
	}

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
		return $query;
	}

	public static function newQuery($applyGlobalScopes = true) {
		return static::query($applyGlobalScopes);
	}

	public static function all() {
		return static::query()->get();
	}

	/**
	 * Find a model by its primary key, or multiple models by an array of
	 * primary keys (Eloquent-style).
	 *
	 * Usage: $user  = User::find(1);          // single id  -> Model|null
	 *        $users = User::find([1, 2, 3]);   // array of ids -> Collection
	 *
	 * @param mixed $id A single primary key value, or an array of values.
	 * @return static|\MJ\WPORM\Collection|null Model|null for a single id,
	 *         Collection for an array of ids.
	 */
	public static function find($id) {
		if (is_array($id)) {
			return static::query()->find($id);
		}

		$instance = new static;
		$pk = $instance->primaryKey;
		return static::query()->where($pk, $id)->first();
	}

	/**
	 * Find a model by its primary key or throw a ModelNotFoundException
	 * if no record matches (Eloquent-style). Same single query as find();
	 * only the not-found behavior differs.
	 *
	 * When given an array of ids, all matching models are returned as a
	 * Collection, but if ANY requested id was not found, a
	 * ModelNotFoundException is thrown listing every missing id.
	 *
	 * Usage: $user  = User::findOrFail(1);        // throws if id 1 doesn't exist
	 *        $users = User::findOrFail([1, 2, 3]); // throws if any of 1, 2, 3 are missing
	 *
	 * @param mixed $id A single primary key value, or an array of values.
	 * @return static|\MJ\WPORM\Collection
	 * @throws ModelNotFoundException
	 */
	public static function findOrFail($id) {
		if (is_array($id)) {
			$instance = new static;
			$pk = $instance->primaryKey;
			$result = static::find($id);
			$foundIds = [];
			foreach ($result as $model) {
				$foundIds[] = $model->$pk;
			}
			$missingIds = array_values(array_diff($id, $foundIds));
			if (!empty($missingIds)) {
				throw (new ModelNotFoundException())->setModel(static::class, $missingIds);
			}
			return $result;
		}

		$result = static::find($id);
		if ($result === null) {
			throw (new ModelNotFoundException())->setModel(static::class, $id);
		}
		return $result;
	}

	/**
	 * Get the first record matching the given attributes, or throw a
	 * ModelNotFoundException if nothing matches (Eloquent-style).
	 *
	 * Usage: $user = User::firstOrFail(['email' => $email]);
	 *        $user = User::query()->where('email', $email)->firstOrFail();
	 *
	 * @param array $attributes
	 * @return static
	 * @throws ModelNotFoundException
	 */
	public static function firstOrFail(array $attributes = []) {
		if (empty($attributes)) {
			throw (new ModelNotFoundException())->setModel(static::class);
		}
		$result = static::query()->where($attributes)->first();
		if ($result === null) {
			throw (new ModelNotFoundException())->setModel(static::class);
		}
		return $result;
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
	 * create: Instantiate a new model with the given (mass-assignable)
	 * attributes, save it, and return the resulting instance — a one-line
	 * insert + return model, Eloquent-style.
	 *
	 * Mass assignment still goes through the constructor/fill()/__set()
	 * pipeline, so $fillable/$guarded are enforced exactly as they are for
	 * `new static($attributes)`. Any attribute not allowed through mass
	 * assignment is silently skipped (consistent with the rest of WPORM's
	 * mass-assignment behavior), rather than throwing.
	 *
	 * Usage:
	 *   $user = User::create(['name' => 'Jane', 'email' => 'jane@example.com']);
	 *
	 * @param array $attributes
	 * @return static The newly created (and already-saved) model instance.
	 */
	public static function create(array $attributes = []) {
		$instance = new static($attributes);
		$instance->save();
		return $instance;
	}

    /**
     * Insert a record, ignoring duplicate key errors (Eloquent-style).
     * Usage: Model::insertOrIgnore(['col' => 'val', ...])
     * Returns true if insert succeeded or was ignored, false on other errors.
     */
    public static function insertOrIgnore(array $attributes)
    {
        $instance = new static;
        $table = $instance->getTable();
        // If $attributes is a list of records (array of arrays)
        if (isset($attributes[0]) && is_array($attributes[0])) {
            $columns = array_keys($attributes[0]);
            $rows = $attributes;
        } else {
            $columns = array_keys($attributes);
            $rows = [$attributes];
        }
        if ($instance->timestamps) {
            $now = current_time('mysql');
            if (!in_array($instance->createdAtColumn, $columns)) {
                $columns[] = $instance->createdAtColumn;
            }
            if (!in_array($instance->updatedAtColumn, $columns)) {
                $columns[] = $instance->updatedAtColumn;
            }
            foreach( $rows as $row_index => $row ) {
                if( !isset( $rows[$row_index][$instance->createdAtColumn] ) ) {
                    $rows[$row_index][$instance->createdAtColumn] = $now;
                }
                if (!isset($rows[$row_index][$instance->updatedAtColumn])) {
                    $rows[$row_index][$instance->updatedAtColumn] = $now;
                }
            }
        }
        $placeholdersRow = '(' . implode(', ', array_fill(0, count($columns), '%s')) . ')';
        $allPlaceholders = implode(', ', array_fill(0, count($rows), $placeholdersRow));
        $allValues = [];
        foreach ($rows as $row) {
            foreach ($columns as $col) {
                $allValues[] = $row[$col] ?? null;
            }
        }

        $quotedColumns = array_map([Helpers::class, 'quoteIdentifier'], $columns);

        $sql = 'INSERT IGNORE INTO ' . $table . ' (' . implode(', ', $quotedColumns) . ') VALUES ' . $allPlaceholders;

        global $wpdb;
        $result = $wpdb->query($wpdb->prepare($sql, ...$allValues));
        return $result !== false;
    }

    /**
     * Insert or update multiple records in a single query (Eloquent-style upsert).
     *
     * Uses MySQL INSERT ... ON DUPLICATE KEY UPDATE syntax.
     *
     * @param array $values Array of records to upsert (each record is an associative array).
     * @param array|string $uniqueBy Column(s) that uniquely identify records (used for ON DUPLICATE KEY).
     * @param array|null $update Columns to update on duplicate. If null, all columns except $uniqueBy are updated.
     * @return int|false Number of affected rows or false on failure.
     *
     * Usage:
     *   Model::upsert([
     *       ['email' => 'a@test.com', 'name' => 'Alice', 'votes' => 1],
     *       ['email' => 'b@test.com', 'name' => 'Bob', 'votes' => 2],
     *   ], ['email'], ['name', 'votes']);
     */
    public static function upsert(array $values, $uniqueBy, $update = null)
    {
        $instance = new static;
        $table = $instance->getTable();

        if (empty($values)) {
            return 0;
        }

        // Normalize to array of arrays
        if (!isset($values[0]) || !is_array($values[0])) {
            $values = [$values];
        }

        $uniqueBy = (array) $uniqueBy;

        // Determine columns from first record
        $columns = array_keys($values[0]);

        // Add timestamps if enabled
        if ($instance->timestamps) {
            $now = current_time('mysql');
            if (!in_array($instance->createdAtColumn, $columns)) {
                $columns[] = $instance->createdAtColumn;
            }
            if (!in_array($instance->updatedAtColumn, $columns)) {
                $columns[] = $instance->updatedAtColumn;
            }
            foreach ($values as $index => $row) {
                if (!isset($values[$index][$instance->createdAtColumn])) {
                    $values[$index][$instance->createdAtColumn] = $now;
                }
                if (!isset($values[$index][$instance->updatedAtColumn])) {
                    $values[$index][$instance->updatedAtColumn] = $now;
                }
            }
        }

        // If update columns not specified, update all columns except the unique key columns
        if ($update === null) {
            $update = array_values(array_diff($columns, $uniqueBy));
        }

        if (empty($update)) {
            // Nothing to update on duplicate — fall back to INSERT IGNORE behavior
            return static::insertOrIgnore($values);
        }

        // Build placeholders
        $placeholdersRow = '(' . implode(', ', array_fill(0, count($columns), '%s')) . ')';
        $allPlaceholders = implode(', ', array_fill(0, count($values), $placeholdersRow));

        $allValues = [];
        foreach ($values as $row) {
            foreach ($columns as $col) {
                $allValues[] = $row[$col] ?? null;
            }
        }

        // Build ON DUPLICATE KEY UPDATE clause
        $updateParts = [];
        foreach ($update as $col) {
            $quoted = Helpers::quoteIdentifier($col);
            $updateParts[] = $quoted . ' = VALUES(' . $quoted . ')';
        }

        // Always update the updated_at timestamp on duplicate if timestamps are enabled
        if ($instance->timestamps && !in_array($instance->updatedAtColumn, $update)) {
            $quoted = Helpers::quoteIdentifier($instance->updatedAtColumn);
            $updateParts[] = $quoted . ' = VALUES(' . $quoted . ')';
        }

        $quotedColumns = array_map([Helpers::class, 'quoteIdentifier'], $columns);

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES %s ON DUPLICATE KEY UPDATE %s',
            $table,
            implode(', ', $quotedColumns),
            $allPlaceholders,
            implode(', ', $updateParts)
        );

        global $wpdb;
        return $wpdb->query($wpdb->prepare($sql, ...$allValues));
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

	/**
	 * Persist the model. Fires saving/saved (always) plus creating/created
	 * (insert) or updating/updated (update) events via $dispatchesEvents and
	 * any globally-registered EventDispatcher listeners.
	 *
	 * Returns false if any before-hook halts the operation (by returning false).
	 */
	public function save() {
		// saving (before-hook — halts on false)
		if ($this->fireModelEvent('saving') === false) {
			return false;
		}

		$result = $this->exists ? $this->update() : $this->insert();

		if ($result !== false) {
			// saved (after-hook — result not used to halt)
			$this->fireModelEvent('saved');
		}

		return $result;
	}

	protected function insert() {
		// creating (before-hook — halts on false)
		if ($this->fireModelEvent('creating') === false) {
			return false;
		}

		// Legacy hook kept for back-compat — only fire if no dispatcher handles it
		if (method_exists($this, 'creating')
			&& (new \ReflectionMethod($this, 'creating'))->getDeclaringClass()->getName() !== __CLASS__
			&& empty($this->dispatchesEvents['creating'])
			&& empty(EventDispatcher::getListeners(\MJ\WPORM\Events\Creating::class))
		) {
			$this->creating();
		}

		global $wpdb;
		if ($this->timestamps) {
			$now = current_time('mysql');
			$this->attributes[$this->createdAtColumn] = $now;
			$this->attributes[$this->updatedAtColumn] = $now;
		}
		$result = $wpdb->insert($this->getTable(), $this->attributes);
		if ($result === false) {
			return false;
		}
		$this->exists = true;
		$pk = $this->primaryKey;
		$this->attributes[$pk] = $wpdb->insert_id;

		// created (after-hook)
		$this->fireModelEvent('created');

		return true;
	}

	protected function update() {
		// updating (before-hook — halts on false)
		if ($this->fireModelEvent('updating') === false) {
			return false;
		}

		// Legacy hook kept for back-compat — only fire if no dispatcher handles it
		if (method_exists($this, 'updating')
			&& (new \ReflectionMethod($this, 'updating'))->getDeclaringClass()->getName() !== __CLASS__
			&& empty($this->dispatchesEvents['updating'])
			&& empty(EventDispatcher::getListeners(\MJ\WPORM\Events\Updating::class))
		) {
			$this->updating();
		}

		global $wpdb;
		if ($this->timestamps) {
			$this->attributes[$this->updatedAtColumn] = current_time('mysql');
		}
		$pk = $this->primaryKey;
		if (!isset($this->attributes[$pk]) && isset($this->$pk)) {
			$this->attributes[$pk] = $this->$pk;
		}
		if (!isset($this->attributes[$pk])) {
			return false;
		}
		$result = $wpdb->update($this->getTable(), $this->attributes, [$pk => $this->attributes[$pk]]);
		if ($result === false) {
			return false;
		}

		// updated (after-hook)
		$this->fireModelEvent('updated');

		return true;
	}

	/**
	 * Increment a column's value for THIS model's row only (Eloquent-style).
	 * Runs a single atomic `UPDATE ... SET col = col + amount` query scoped to
	 * the model's primary key, and syncs the new value onto the in-memory
	 * attribute so the model reflects the change without a re-fetch.
	 *
	 * Usage:
	 *   $user->increment('votes');
	 *   $user->increment('votes', 5);
	 *   $user->increment('votes', 1, ['last_voted_at' => current_time('mysql')]);
	 *
	 * @param string $column
	 * @param int|float $amount
	 * @param array $extra Additional column => value pairs to set in the same query
	 * @return int|false Number of affected rows, or false if the model has no PK value
	 */
	public function increment($column, $amount = 1, array $extra = []) {
		return $this->incrementOrDecrement($column, $amount, $extra, 1);
	}

	/**
	 * Decrement a column's value for THIS model's row only (Eloquent-style).
	 * See increment() for details — identical behavior, opposite direction.
	 *
	 * @param string $column
	 * @param int|float $amount
	 * @param array $extra Additional column => value pairs to set in the same query
	 * @return int|false Number of affected rows, or false if the model has no PK value
	 */
	public function decrement($column, $amount = 1, array $extra = []) {
		return $this->incrementOrDecrement($column, $amount, $extra, -1);
	}

	/**
	 * Shared implementation for the instance increment()/decrement() methods.
	 * Scopes the update to this model's primary key value via the query builder.
	 *
	 * @param string $column
	 * @param int|float $amount
	 * @param array $extra
	 * @param int $direction 1 for increment, -1 for decrement
	 * @return int|false
	 */
	protected function incrementOrDecrement($column, $amount, array $extra, $direction) {
		$pk = $this->primaryKey;
		if (!isset($this->attributes[$pk]) && isset($this->$pk)) {
			$this->attributes[$pk] = $this->$pk;
		}
		if (!isset($this->attributes[$pk])) {
			// Cannot scope the update without a primary key value
			return false;
		}

		$query = static::query()->where($pk, $this->attributes[$pk]);
		$result = $direction > 0
			? $query->increment($column, $amount, $extra)
			: $query->decrement($column, $amount, $extra);

		// Sync the new value(s) onto the in-memory model so it reflects the change.
		$current = (float) ($this->attributes[$column] ?? 0);
		$newValue = $current + ($direction * $amount);
		// Preserve int type when possible (most counter columns are integers).
		$this->attributes[$column] = (floor($newValue) == $newValue) ? (int) $newValue : $newValue;
		$this->original[$column] = $this->attributes[$column];

		foreach ($extra as $key => $value) {
			$this->attributes[$key] = $value;
			$this->original[$key] = $value;
		}
		if ($this->timestamps && !array_key_exists($this->updatedAtColumn, $extra)) {
			// incrementOrDecrement() on the query builder auto-touches updated_at;
			// mirror that onto the in-memory model too (best-effort, approximate).
			$this->original[$this->updatedAtColumn] = $this->attributes[$this->updatedAtColumn] ?? null;
		}

		return $result;
	}

	public function delete() {
		if ($this->softDeletes) {
			// softDeleting before-hook
			if ($this->fireModelEvent('softDeleting') === false) {
				return false;
			}
			if (method_exists($this, 'softDeleting')) {
				$this->softDeleting();
			}
			global $wpdb;
			$this->attributes[$this->deletedAtColumn] = $this->softDeleteType === 'boolean' ? 1 : current_time('mysql');
			$pk = $this->primaryKey;
			$wpdb->update($this->getTable(), [$this->deletedAtColumn => $this->attributes[$this->deletedAtColumn]], [$pk => $this->attributes[$pk]]);
			$this->exists = true;
			// softDeleted after-hook
			$this->fireModelEvent('softDeleted');
			if (method_exists($this, 'softDeleted')) {
				$this->softDeleted();
			}
			return true;
		}

		// deleting before-hook
		if ($this->fireModelEvent('deleting') === false) {
			return false;
		}
		if (method_exists($this, 'deleting')) {
			$this->deleting();
		}
		global $wpdb;
		$wpdb->delete($this->getTable(), [$this->primaryKey => $this->attributes[$this->primaryKey]]);
		$this->exists = false;
		// deleted after-hook
		$this->fireModelEvent('deleted');
		return true;
	}

	public function trashed() {
		return $this->softDeletes && !empty($this->attributes[$this->deletedAtColumn]);
	}

	/**
	 * Re-fetch a fresh copy of the model from the database, returning a new
	 * instance without modifying the current one (Eloquent-style fresh()).
	 *
	 * Queries by primary key only and bypasses global scopes — matching
	 * Eloquent's newQueryWithoutScopes() — since refetching "this exact row"
	 * should not be hidden by an unrelated global scope. Soft-delete scoping
	 * still applies: if the row has since been soft-deleted (and $withTrashed
	 * isn't used), it simply won't be found.
	 *
	 * Optionally eager-loads relations on the fresh instance, exactly like
	 * Model::with() / QueryBuilder::with().
	 *
	 * Usage:
	 *   $fresh = $user->fresh();             // new instance, $user untouched
	 *   $fresh = $user->fresh('posts');       // with eager-loaded relation
	 *   $fresh = $user->fresh(['posts', 'profile']);
	 *
	 * @param array|string $with Relation(s) to eager-load on the fresh instance.
	 * @return static|null A new model instance, or null if the row no longer exists.
	 */
	public function fresh($with = []) {
		$pk = $this->primaryKey;
		if (!isset($this->attributes[$pk])) {
			return null;
		}

		$query = static::query(false)->where($pk, $this->attributes[$pk]);

		if (!empty($with)) {
			$query->with($with);
		}

		return $query->first();
	}

	/**
	 * Re-fetch the model's attributes from the database and overwrite them
	 * onto the CURRENT instance in place (Eloquent-style refresh()). Unlike
	 * fresh(), this mutates $this and returns it, rather than returning a
	 * separate instance.
	 *
	 * Bypasses global scopes (same rationale as fresh()). Any previously
	 * eager-loaded relations are cleared, since they may now be stale —
	 * re-access them via property access or with() after refreshing.
	 *
	 * Throws ModelNotFoundException if the row no longer exists in the
	 * database (e.g. it was deleted, or soft-deleted and excluded by the
	 * default scope), mirroring Eloquent's refresh() behavior.
	 *
	 * Usage:
	 *   $user->refresh(); // $user now reflects the current DB row
	 *
	 * @return $this
	 * @throws ModelNotFoundException
	 */
	public function refresh() {
		$pk = $this->primaryKey;
		if (!isset($this->attributes[$pk])) {
			throw (new ModelNotFoundException())->setModel(static::class);
		}

		$fresh = static::query(false)->where($pk, $this->attributes[$pk])->first();

		if ($fresh === null) {
			throw (new ModelNotFoundException())->setModel(static::class, $this->attributes[$pk]);
		}

		$this->attributes = $fresh->attributes;
		$this->original = $fresh->attributes;
		$this->exists = true;
		// Eager-loaded relations may now be stale; clear them so accessing
		// a relation property re-resolves it against the refreshed state.
		$this->_eagerLoaded = [];

		return $this;
	}

	public function restore() {
		if ($this->softDeletes && $this->trashed()) {
			// restoring before-hook
			if ($this->fireModelEvent('restoring') === false) {
				return false;
			}
			if (method_exists($this, 'restoring')) {
				$this->restoring();
			}
			global $wpdb;
			$pk = $this->primaryKey;
			$this->attributes[$this->deletedAtColumn] = $this->softDeleteType === 'boolean' ? 0 : null;
			$wpdb->update($this->getTable(), [$this->deletedAtColumn => $this->attributes[$this->deletedAtColumn]], [$pk => $this->attributes[$pk]]);
			$this->exists = true;
			// restored after-hook
			$this->fireModelEvent('restored');
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
            if (!method_exists($this, $relation)) {
                continue;
            }
            $related = $this->$relation();

            // All relationship methods return a QueryBuilder; resolve it based
            // on its relation type (single model vs collection of models).
            if ($related instanceof \MJ\WPORM\QueryBuilder) {
                $context = $related->getRelationContext();
                $type = $context['type'] ?? null;
                if ($type === 'belongsTo' || $type === 'hasOne' || $type === 'morphOne' || $type === 'morphTo') {
                    $resolved = $related->first();
                    if ($resolved instanceof \MJ\WPORM\Model) {
                        $resolved->forceDelete();
                    }
                } else {
                    foreach ($related->get() as $item) {
                        if ($item instanceof \MJ\WPORM\Model) {
                            $item->forceDelete();
                        }
                    }
                }
            } elseif ($related instanceof \MJ\WPORM\Model) {
                $related->forceDelete();
            } elseif ($related instanceof \MJ\WPORM\Collection) {
                foreach ($related as $item) {
                    if ($item instanceof \MJ\WPORM\Model) {
                        $item->forceDelete();
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
	 *
	 * The $table property should always be declared as a bare name (e.g.
	 * 'users') without the WordPress prefix. This method applies
	 * $wpdb->prefix unconditionally to avoid false-positive detection when
	 * a table name coincidentally starts with the prefix string.
	 *
	 * @return string Fully qualified table name including the DB prefix.
	 */
	public function getTable()
	{
		global $wpdb;
		if (isset($this->table)) {
			return $wpdb->prefix . $this->table;
		}
		return $wpdb->prefix . strtolower(Helpers::class_basename(static::class));
	}

	// -------------------------------------------------------------------------
	// Relationships
	// -------------------------------------------------------------------------

	/**
	 * One-to-one relationship.
	 * Returns a QueryBuilder that resolves to a single related model.
	 *
	 * @template T of Model
	 * @param class-string<T> $related
	 * @param string|null $foreignKey  FK on the related table pointing to this model
	 * @param string|null $localKey    PK on this table (default: $primaryKey)
	 * @return QueryBuilder<T>
	 */
	public function hasOne($related, $foreignKey = null, $localKey = null) {
		$foreignKey = $foreignKey ?: strtolower(Helpers::class_basename(static::class)) . '_id';
		$localKey   = $localKey   ?: $this->primaryKey;
		$query = $related::query()->where($foreignKey, $this->$localKey);
		return $query->setRelationContext('hasOne', [
			'foreignKey' => $foreignKey,
			'localKey'   => $localKey,
			'related'    => $related,
		]);
	}

	/**
	 * One-to-many relationship.
	 * Returns a QueryBuilder that resolves to a Collection.
	 *
	 * @template T of Model
	 * @param class-string<T> $related
	 * @param string|null $foreignKey  FK on the related table pointing to this model
	 * @param string|null $localKey    PK on this table (default: $primaryKey)
	 * @return QueryBuilder<T>
	 */
    public function hasMany($related, $foreignKey = null, $localKey = null) {
        $foreignKey = $foreignKey ?: strtolower(Helpers::class_basename(static::class)) . '_id';
        $localKey   = $localKey   ?: $this->primaryKey;
        $query = $related::query()->where($foreignKey, $this->$localKey);
        return $query->setRelationContext('hasMany', [
            'foreignKey' => $foreignKey,
            'localKey'   => $localKey,
            'related'    => $related,
        ]);
    }

	/**
	 * Many-to-many relationship via a pivot table.
	 * Returns a QueryBuilder that resolves to a Collection.
	 *
	 * Pivot table default follows Eloquent convention: alphabetically-sorted
	 * singular model names joined by an underscore (without the DB prefix).
	 *
	 * @template T of Model
	 * @param class-string<T> $related
	 * @param string|null $pivotTable       Name of the pivot table (without prefix)
	 * @param string|null $foreignPivotKey  FK for *this* model on the pivot table
	 * @param string|null $relatedPivotKey  FK for the *related* model on the pivot table
	 * @return QueryBuilder<T>
	 */
    public function belongsToMany($related, $pivotTable = null, $foreignPivotKey = null, $relatedPivotKey = null) {
        global $wpdb;
        $relatedInstance = new $related;

        // Eloquent convention: alphabetically-sorted singular model names, no prefix.
        if ($pivotTable === null) {
            $models = [
                strtolower(Helpers::class_basename(static::class)),
                strtolower(Helpers::class_basename($related)),
            ];
            sort($models);
            $pivotTable = $wpdb->prefix . implode('_', $models);
        } else {
            // If the caller supplied a bare table name, add the prefix.
            if (strpos($pivotTable, $wpdb->prefix) !== 0) {
                $pivotTable = $wpdb->prefix . $pivotTable;
            }
        }

        $foreignPivotKey  = $foreignPivotKey  ?: strtolower(Helpers::class_basename(static::class)) . '_id';
        $relatedPivotKey  = $relatedPivotKey  ?: strtolower(Helpers::class_basename($related)) . '_id';
        $relatedTable     = $relatedInstance->getTable();
        $relatedPrimaryKey = $relatedInstance->getPrimaryKey();
        $localKey         = $this->primaryKey;

        $query = $related::query();
        $query->join(
            $pivotTable,
            "$relatedTable.$relatedPrimaryKey",
            '=',
            "$pivotTable.$relatedPivotKey"
        )->where("$pivotTable.$foreignPivotKey", $this->$localKey);

        return $query->setRelationContext('belongsToMany', [
            'pivotTable'      => $pivotTable,
            'foreignPivotKey' => $foreignPivotKey,
            'relatedPivotKey' => $relatedPivotKey,
            'localKey'        => $localKey,
            'relatedTable'    => $relatedTable,
            'related'         => $related,
        ]);
    }

	/**
	 * Has-many-through relationship.
	 *
	 * Convention (matching Eloquent):
	 *   $firstKey  = FK on the *through* table pointing to *this* model  (e.g. user_id  on posts)
	 *   $secondKey = FK on the *related* table pointing to the *through* model (e.g. post_id on comments)
	 *   $localKey  = PK on *this* table (default: $primaryKey)
	 *
	 * @template T of Model
	 * @template Through of Model
	 * @param class-string<T>       $related
	 * @param class-string<Through> $through
	 * @param string|null $firstKey
	 * @param string|null $secondKey
	 * @param string|null $localKey
	 * @return QueryBuilder<T>
	 */
    public function hasManyThrough($related, $through, $firstKey = null, $secondKey = null, $localKey = null) {
        $throughInstance = new $through;
        $relatedInstance = new $related;

        // $firstKey:  FK on the through table pointing back to this model
        $firstKey  = $firstKey  ?: strtolower(Helpers::class_basename(static::class)) . '_id';
        // $secondKey: FK on the related table pointing to the through table
        $secondKey = $secondKey ?: strtolower(Helpers::class_basename($through)) . '_id';
        $localKey  = $localKey  ?: $this->primaryKey;

        $relatedTable  = $relatedInstance->getTable();
        $throughTable  = $throughInstance->getTable();
        $throughPK     = $throughInstance->getPrimaryKey();

        $query = $related::query();
        $query->join(
            $throughTable,
            "$relatedTable.$secondKey",
            '=',
            "$throughTable.$throughPK"
        )->where("$throughTable.$firstKey", $this->$localKey);

        return $query->setRelationContext('hasManyThrough', [
            'firstKey'     => $firstKey,
            'secondKey'    => $secondKey,
            'localKey'     => $localKey,
            'relatedTable' => $relatedTable,
            'throughTable' => $throughTable,
            'throughPK'    => $throughPK,
            'related'      => $related,
        ]);
    }

	/**
     * Define an inverse one-to-one or many relationship (belongsTo).
     *
     * @template T of Model
     * @param class-string<T> $related
     * @param string|null $foreignKey
     * @param string|null $ownerKey
     * @return QueryBuilder<T>
     */
    public function belongsTo($related, $foreignKey = null, $ownerKey = null) {
        $instance = new $related;
        $foreignKey = $foreignKey ?: strtolower(Helpers::class_basename($related)) . '_id';
        $ownerKey = $ownerKey ?: $instance->getPrimaryKey();
        $foreignValue = $this->attributes[$foreignKey] ?? null;
        $query = $related::query();
        if ($foreignValue === null) {
            $query->whereIn($ownerKey, []);
        } else {
            $query->where($ownerKey, $foreignValue);
        }
        return $query->setRelationContext('belongsTo', [
            'foreignKey'     => $foreignKey,
            'ownerKey'       => $ownerKey,
            'foreignValue'   => $foreignValue,
            'related'        => $related,
            'baseWhereCount' => $query->getWhereCount(),
        ]);
    }

	/**
	 * Define a polymorphic one-to-one relationship.
	 *
	 * The related table carries two columns: a "type" column storing the
	 * owning model's morph class (its morph-map alias if registered via
	 * morphMap(), otherwise its fully-qualified class name), and an "id"
	 * column storing its primary key. Defaults follow Eloquent's convention:
	 * "{name}_type" / "{name}_id", e.g. morphOne(Image::class, 'imageable')
	 * -> imageable_type / imageable_id.
	 *
	 * Returns a lazy, chainable QueryBuilder — call ->first() to resolve it,
	 * or access it as a property (e.g. $post->image) to resolve automatically.
	 *
	 * @template T of Model
	 * @param class-string<T> $related
	 * @param string $name        The morph name, e.g. 'imageable'
	 * @param string|null $type   Override for the "type" column (default: "{name}_type")
	 * @param string|null $id     Override for the "id" column (default: "{name}_id")
	 * @param string|null $localKey PK on this model (default: $primaryKey)
	 * @return QueryBuilder<T>
	 */
	public function morphOne($related, $name, $type = null, $id = null, $localKey = null) {
		$type = $type ?: $name . '_type';
		$id   = $id   ?: $name . '_id';
		$localKey = $localKey ?: $this->primaryKey;

		$morphClass = $this->getMorphClass();

		$query = $related::query()
			->where($type, $morphClass)
			->where($id, $this->$localKey);

		return $query->setRelationContext('morphOne', [
			'morphType'  => $type,
			'morphId'    => $id,
			'morphClass' => $morphClass,
			'localKey'   => $localKey,
			'related'    => $related,
		]);
	}

	/**
	 * Define a polymorphic one-to-many relationship.
	 *
	 * Same column conventions as morphOne() ("{name}_type" / "{name}_id"),
	 * but resolves to a Collection instead of a single model.
	 *
	 * @template T of Model
	 * @param class-string<T> $related
	 * @param string $name
	 * @param string|null $type
	 * @param string|null $id
	 * @param string|null $localKey
	 * @return QueryBuilder<T>
	 */
	public function morphMany($related, $name, $type = null, $id = null, $localKey = null) {
		$type = $type ?: $name . '_type';
		$id   = $id   ?: $name . '_id';
		$localKey = $localKey ?: $this->primaryKey;

		$morphClass = $this->getMorphClass();

		$query = $related::query()
			->where($type, $morphClass)
			->where($id, $this->$localKey);

		return $query->setRelationContext('morphMany', [
			'morphType'  => $type,
			'morphId'    => $id,
			'morphClass' => $morphClass,
			'localKey'   => $localKey,
			'related'    => $related,
		]);
	}

	/**
	 * Define the inverse of a polymorphic relationship.
	 *
	 * Unlike morphOne()/morphMany() (defined on the "owning" model, e.g.
	 * Post), morphTo() is defined on the "child" model (e.g. Comment) and
	 * resolves to whichever model class is named in its own "{name}_type"
	 * column. The related model class is not known until the row's data
	 * has been read, so — unlike every other relationship type — $name
	 * must be passed explicitly (PHP has no cheap, reliable way to recover
	 * the calling relationship method's own name at runtime).
	 *
	 * Returns a lazy, chainable QueryBuilder scoped to the concrete related
	 * model class and id stored on this row — call ->first() to resolve it,
	 * or access it as a property (e.g. $comment->commentable).
	 *
	 * Usage:
	 *   class Comment extends Model {
	 *       public function commentable() {
	 *           return $this->morphTo('commentable');
	 *       }
	 *   }
	 *
	 * @param string $name        The morph name, e.g. 'commentable'.
	 * @param string|null $type   Override for the "type" column (default: "{name}_type")
	 * @param string|null $id     Override for the "id" column (default: "{name}_id")
	 * @return QueryBuilder
	 */
	public function morphTo($name, $type = null, $id = null) {
		$type = $type ?: $name . '_type';
		$id   = $id   ?: $name . '_id';

		$morphClass   = $this->attributes[$type] ?? null;
		$foreignValue = $this->attributes[$id] ?? null;

		// Resolve a morph map alias back to a real class, if one is registered.
		$relatedClass = $morphClass !== null ? static::getMorphedModel($morphClass) : null;

		if ($relatedClass === null || $foreignValue === null || !class_exists($relatedClass)) {
			// No concrete type/id on this row (or an unmapped/unknown type) —
			// return an always-empty, but still chainable, query so callers
			// (including with()/whereHas()) get consistent behavior instead
			// of a null/exception.
			$query = static::query()->whereIn($this->primaryKey, []);
			return $query->setRelationContext('morphTo', [
				'morphType'    => $type,
				'morphId'      => $id,
				'morphClass'   => $morphClass,
				'foreignValue' => $foreignValue,
				'related'      => static::class,
				'unresolved'   => true,
			]);
		}

		$relatedInstance = new $relatedClass;
		$ownerKey = $relatedInstance->getPrimaryKey();

		$query = $relatedClass::query()->where($ownerKey, $foreignValue);

		return $query->setRelationContext('morphTo', [
			'morphType'    => $type,
			'morphId'      => $id,
			'morphClass'   => $morphClass,
			'ownerKey'     => $ownerKey,
			'foreignValue' => $foreignValue,
			'related'      => $relatedClass,
		]);
	}

	/**
	 * Morph map: aliases for morph "type" column values, so the database
	 * stores a short string (e.g. "post") instead of a fully-qualified
	 * class name (e.g. "App\\Models\\Post"). Eloquent-style Relation::morphMap().
	 * Shared across all models (keyed globally, not per-model).
	 *
	 * @var array<string, class-string>
	 */
	protected static $morphMap = [];

	/**
	 * Register morph type aliases. Merges into the existing map by default;
	 * pass $replace = true to overwrite it entirely.
	 *
	 * Usage:
	 *   Model::morphMap(['post' => Post::class, 'video' => Video::class]);
	 *
	 * @param array<string, class-string> $map
	 * @param bool $replace
	 * @return void
	 */
	public static function morphMap(array $map, $replace = false) {
		static::$morphMap = $replace ? $map : array_merge(static::$morphMap, $map);
	}

	/**
	 * Get the currently registered morph map.
	 * @return array<string, class-string>
	 */
	public static function getMorphMap() {
		return static::$morphMap;
	}

	/**
	 * Resolve a morph "type" column value to a concrete class name — either
	 * a registered alias (via morphMap()) or, if not aliased, the value
	 * itself (assumed to already be a fully-qualified class name, matching
	 * Eloquent's default un-mapped behavior).
	 *
	 * @param string $morphClass
	 * @return string
	 */
	public static function getMorphedModel($morphClass) {
		return static::$morphMap[$morphClass] ?? $morphClass;
	}

	/**
	 * Get the value this model should be stored as in a morph "type" column
	 * when it is the owning side of a polymorphic relation — the morph map
	 * alias if this class is registered, otherwise the fully-qualified class
	 * name (Eloquent's default behavior).
	 *
	 * @return string
	 */
	public function getMorphClass() {
		$flipped = array_flip(static::$morphMap);
		return $flipped[static::class] ?? static::class;
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

	/**
	 * Remove an internal/transient attribute (e.g. a pivot-table alias column
	 * selected only for eager-loading bookkeeping) so it does not leak into
	 * toArray()/toJson() output. Safe to call even if the key isn't set.
	 *
	 * @param string $key
	 * @return $this
	 */
	public function forgetAttribute($key) {
		unset($this->attributes[$key]);
		unset($this->original[$key]);
		return $this;
	}

	/**
	 * Set a computed/internal attribute directly into $attributes, bypassing
	 * the $fillable/$guarded mass-assignment guard and any set{Attr}Attribute()
	 * mutator. Used internally by QueryBuilder::loadRelationCount() (i.e.
	 * withCount()) to attach a "{relation}_count" integer onto each model —
	 * a value computed by WPORM itself, not user-supplied input, so the
	 * mass-assignment protections that guard __set() do not apply here
	 * (the same rationale newFromBuilder() uses for hydrating real columns).
	 * Also mirrored into $original so isDirty()/getChanges() don't report
	 * the count as a pending change.
	 *
	 * @param string $key
	 * @param mixed $value
	 * @return $this
	 */
	public function forceSetAttribute($key, $value) {
		$this->attributes[$key] = $value;
		$this->original[$key] = $value;
		return $this;
	}

	public function toArray() {
        $attributes = [];
        foreach ($this->attributes as $key => $value) {
            $attributes[$key] = $this->castGet($key, $value);
        }
        // Add already eager loaded relations only (avoid infinite loop)
        foreach ($this->_eagerLoaded as $relation => $data) {
            if ($data instanceof \MJ\WPORM\Collection) {
                $attributes[$relation] = $data->toArray();
            } elseif (is_array($data)) {
                $attributes[$relation] = array_map(function($item) {
                    return method_exists($item, 'toArray') ? $item->toArray() : (array)$item;
                }, $data);
            } elseif (is_object($data) && method_exists($data, 'toArray')) {
                $attributes[$relation] = $data->toArray();
            } else {
                $attributes[$relation] = $data;
            }
        }
        // Add appended attributes
        foreach ($this->appends as $appended) {
            $method = 'get' . Helpers::convert_to_pascal_case($appended) . 'Attribute';
            if (method_exists($this, $method)) {
                $attributes[$appended] = $this->$method();
            } elseif (property_exists($this, $appended)) {
                $attributes[$appended] = $this->$appended;
            }
        }
        return $this->applyVisibility($attributes);
    }

    /**
     * Apply $visible/$hidden (and runtime overrides) filtering to an attribute array.
     * Mirrors Eloquent: $visible (if set) is applied first as an allow-list, then
     * $hidden subtracts from whatever remains. Runtime overrides from makeHidden()/
     * makeVisible() are layered on top of the model-defined lists.
     *
     * @param array $attributes
     * @return array
     */
    protected function applyVisibility(array $attributes) {
        $visible = !empty($this->visible) ? array_flip($this->visible) : null;
        if ($visible !== null) {
            $attributes = array_intersect_key($attributes, $visible);
        }

        $hidden = array_unique(array_merge($this->hidden, $this->runtimeHidden));
        $hidden = array_diff($hidden, $this->runtimeVisible);

        if (!empty($hidden)) {
            foreach ($hidden as $key) {
                unset($attributes[$key]);
            }
        }

        return $attributes;
    }

    /**
     * Get the attributes that are hidden from array/JSON output.
     * @return array
     */
    public function getHidden() {
        return $this->hidden;
    }

    /**
     * Set the hidden attributes for the model (replaces the current list).
     * @param array $hidden
     * @return $this
     */
    public function setHidden(array $hidden) {
        $this->hidden = $hidden;
        return $this;
    }

    /**
     * Get the attributes that are explicitly visible (allow-list) for array/JSON output.
     * @return array
     */
    public function getVisible() {
        return $this->visible;
    }

    /**
     * Set the visible attributes for the model (replaces the current list).
     * @param array $visible
     * @return $this
     */
    public function setVisible(array $visible) {
        $this->visible = $visible;
        return $this;
    }

    /**
     * Hide the given attribute(s) from array/JSON output for this instance,
     * on top of whatever is already in $hidden (Eloquent-style makeHidden()).
     * Usage: $user->makeHidden('password')->toArray();
     *
     * @param array|string $attributes
     * @return $this
     */
    public function makeHidden($attributes) {
        $attributes = is_array($attributes) ? $attributes : func_get_args();
        $this->runtimeHidden = array_unique(array_merge($this->runtimeHidden, $attributes));
        // If a previously-runtime-visible attribute is now explicitly hidden again, un-reveal it.
        $this->runtimeVisible = array_diff($this->runtimeVisible, $attributes);
        return $this;
    }

    /**
     * Reveal the given attribute(s) in array/JSON output for this instance,
     * even if they're present in $hidden (Eloquent-style makeVisible()).
     * Usage: $user->makeVisible('password')->toArray();
     *
     * @param array|string $attributes
     * @return $this
     */
    public function makeVisible($attributes) {
        $attributes = is_array($attributes) ? $attributes : func_get_args();
        $this->runtimeVisible = array_unique(array_merge($this->runtimeVisible, $attributes));
        $this->runtimeHidden = array_diff($this->runtimeHidden, $attributes);
        return $this;
    }

    /**
     * Convert the model to its JSON representation, respecting $hidden/$visible.
     *
     * Mirrors Eloquent's behavior: if json_encode() fails (e.g. due to
     * malformed UTF-8 in an attribute, or a NAN/INF float from a cast),
     * a \JsonException is thrown rather than silently returning `false`,
     * so encoding failures surface immediately instead of producing a
     * corrupt/empty payload downstream.
     *
     * @param int $options json_encode() options
     * @return string
     * @throws \JsonException
     */
    public function toJson($options = 0) {
        $json = json_encode($this->toArray(), $options);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \JsonException(
                'Error encoding model [' . static::class . '] to JSON: ' . json_last_error_msg()
            );
        }

        return $json;
    }

    /**
     * Convert the model to its string representation (Eloquent-style).
     * Allows a model to be used directly in string contexts, e.g.
     * `echo $user;` or `"User: {$user}"`, producing the same output as
     * `toJson()`.
     *
     * @return string
     */
    public function __toString() {
        return $this->toJson();
    }

	public function getOriginal($key = null) {
		return $key ? ($this->original[$key] ?? null) : $this->original;
	}

	// -------------------------------------------------------------------------
	// Event dispatching
	// -------------------------------------------------------------------------

	/**
	 * Fire a model lifecycle event via $dispatchesEvents + EventDispatcher.
	 *
	 * Maps event short-names to their event classes:
	 *   retrieved, creating, created, updating, updated, saving, saved,
	 *   deleting, deleted, softDeleting, softDeleted, restoring, restored.
	 *
	 * Returns false if any before-hook listener halted the event; returns the
	 * event object (truthy) on success; returns null if no event class exists
	 * for the given name.
	 *
	 * @param string $event  Lowercase event short-name.
	 * @return mixed
	 */
	protected function fireModelEvent(string $event)
	{
		static $eventMap = [
			'retrieved'    => \MJ\WPORM\Events\Retrieved::class,
			'creating'     => \MJ\WPORM\Events\Creating::class,
			'created'      => \MJ\WPORM\Events\Created::class,
			'updating'     => \MJ\WPORM\Events\Updating::class,
			'updated'      => \MJ\WPORM\Events\Updated::class,
			'saving'       => \MJ\WPORM\Events\Saving::class,
			'saved'        => \MJ\WPORM\Events\Saved::class,
			'deleting'     => \MJ\WPORM\Events\Deleting::class,
			'deleted'      => \MJ\WPORM\Events\Deleted::class,
			'softDeleting' => \MJ\WPORM\Events\SoftDeleting::class,
			'softDeleted'  => \MJ\WPORM\Events\SoftDeleted::class,
			'restoring'    => \MJ\WPORM\Events\Restoring::class,
			'restored'     => \MJ\WPORM\Events\Restored::class,
		];

		if (!isset($eventMap[$event])) {
			return null;
		}

		$eventClass = $eventMap[$event];

		// Fast-path: no $dispatchesEvents mapping and no global listeners registered.
		if (empty($this->dispatchesEvents[$event])
			&& empty(EventDispatcher::getListeners($eventClass))
		) {
			return null;
		}

		return EventDispatcher::dispatch(new $eventClass($this));
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

	/**
	 * Pass the model instance to the given callback for side-effects, then
	 * return the model unchanged (Eloquent-style tap()). The callback's
	 * return value is always discarded. Designed for inline debugging,
	 * logging, or inspection without breaking a fluent chain.
	 *
	 * Defined directly on Model (rather than relying on __call() to proxy
	 * to the query builder) so it operates on THIS model instance — the
	 * query-builder-level tap() (e.g. User::query()->tap(...)) remains
	 * available separately and is unaffected.
	 *
	 * Usage:
	 *   $user = User::create(['name' => 'Jane'])
	 *       ->tap(fn($u) => error_log("Created user #{$u->id}"));
	 *
	 * @param callable $callback function(Model $model): void
	 * @return $this
	 */
	public function tap(callable $callback) {
		$callback($this);
		return $this;
	}

	/**
	 * Pass the model instance to the given callback and return whatever the
	 * callback returns (Eloquent-style pipe()). Unlike tap(), the callback's
	 * return value IS used — pipe() terminates or transforms the chain.
	 * Useful for handing the model off to a presenter/transformer and
	 * returning its result inline.
	 *
	 * Usage:
	 *   $dto = User::find(1)->pipe(fn($u) => $userPresenter->toDto($u));
	 *
	 * @param callable $callback function(Model $model): mixed
	 * @return mixed Whatever the callback returns
	 */
	public function pipe(callable $callback) {
		return $callback($this);
	}

	public function offsetExists($offset): bool {
		return isset($this->attributes[$offset]);
	}

    #[\ReturnTypeWillChange]
	public function offsetGet($offset) {
		return $this->__get($offset);
	}

	public function offsetSet($offset, $value): void {
		$this->setAttributeDirectly($offset, $value);
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

    /**
     * Start a query with relationship counts (Eloquent-style static withCount()).
     * Usage: User::withCount('posts')->get(); // each $user->posts_count
     * @param array|string $relations
     * @return \MJ\WPORM\QueryBuilder
     */
    public static function withCount($relations) {
        return static::query()->withCount($relations);
    }

    public static function withSum($relations, string $column) {
        return static::query()->withSum($relations, $column);
    }

    public static function withAvg($relations, string $column) {
        return static::query()->withAvg($relations, $column);
    }

    public static function withMin($relations, string $column) {
        return static::query()->withMin($relations, $column);
    }

    public static function withMax($relations, string $column) {
        return static::query()->withMax($relations, $column);
    }

    public function __isset($key) {
        // Eager loaded relations (use array_key_exists so null values are considered "set")
        if (array_key_exists($key, $this->_eagerLoaded)) {
            return true;
        }
        $method = 'get' . Helpers::convert_to_pascal_case($key) . 'Attribute';
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

    /**
     * Get the fillable attributes for the model (Eloquent-style).
     * @return array
     */
    public function getFillable()
    {
        return $this->fillable;
    }

    /**
     * Conditionally add query constraints (Eloquent-style when()).
     * Usage: Model::query()->when($condition, function($q) { ... });
     *
     * @param mixed $value Condition value
     * @param callable $callback Callback if condition is truthy
     * @param callable|null $default Callback if condition is falsy
     * @return QueryBuilder
     */
    public static function when($value, callable $callback, ?callable $default = null) {
        $query = static::query();
        if ($value) {
            $callback($query, $value);
        } elseif ($default) {
            $default($query, $value);
        }
        return $query;
    }
}
