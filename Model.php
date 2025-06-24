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
			return $this->$key();
		}
		if (isset($this->attributes[$key])) {
			return $this->castGet($key, $this->attributes[$key]);
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

	public static function query($applyGlobalScopes = true) {
		return new \MJ\WPORM\QueryBuilder(new static, $applyGlobalScopes);
	}

	public static function newQuery($applyGlobalScopes = true) {
		return new \MJ\WPORM\QueryBuilder(new static, $applyGlobalScopes);
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
		$instance = static::query()->where('id', $id)->first();
		if ($instance && method_exists($instance, 'retrieved')) {
			$instance->retrieved();
		}
		return $instance;
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
	 * @return static
	 */
	public static function updateOrCreate(array $attributes, array $values = []) {
		$instance = static::query()->where($attributes)->first();
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
	 * @return static
	 */
	public static function firstOrCreate(array $attributes, array $values = []) {
		$instance = static::query()->where($attributes)->first();
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
	 * @return static
	 */
	public static function firstOrNew(array $attributes, array $values = []) {
		$instance = static::query()->where($attributes)->first();
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
		$this->attributes[$this->primaryKey] = $wpdb->insert_id;
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
		$wpdb->update($this->getTable(), $this->attributes, [$this->primaryKey => $this->attributes[$this->primaryKey]]);
		return true;
	}

	public function delete() {
		if (method_exists($this, 'deleting')) { // Event
			$this->deleting();
		}
		global $wpdb;
		$wpdb->delete($this->getTable(), [$this->primaryKey => $this->attributes[$this->primaryKey]]);
		$this->exists = false;
		return true;
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
	 * @return T|null
	 */
	public function hasOne($related, $foreignKey = null, $localKey = null) {
		$instance = new $related;
		$foreignKey = $foreignKey ?: strtolower(class_basename(static::class)) . '_id';
		$localKey = $localKey ?: $this->primaryKey;
		return $related::query()->where($foreignKey, $this->$localKey)->first();
	}

	/**
     * @template T of Model
     * @param class-string<T> $related
     * @param string|null $foreignKey
     * @param string|null $localKey
     * @return \MJ\WPORM\Collection<T>
     */
    public function hasMany($related, $foreignKey = null, $localKey = null): \MJ\WPORM\Collection {
        $instance = new $related;
        $foreignKey = $foreignKey ?: strtolower(class_basename(static::class)) . '_id';
        $localKey = $localKey ?: $this->primaryKey;
        return $related::query()->where($foreignKey, $this->$localKey)->get();
    }

    /**
     * @template T of Model
     * @param class-string<T> $related
     * @param string|null $pivotTable
     * @param string|null $foreignPivotKey
     * @param string|null $relatedPivotKey
     * @return \MJ\WPORM\Collection<T>
     */
    public function belongsToMany($related, $pivotTable = null, $foreignPivotKey = null, $relatedPivotKey = null): \MJ\WPORM\Collection {
        global $wpdb;
        $relatedInstance = new $related;
        $pivotTable = $pivotTable ?: $this->getTable() . '_' . $relatedInstance->getTable();
        $foreignPivotKey = $foreignPivotKey ?: strtolower(class_basename(static::class)) . '_id';
        $relatedPivotKey = $relatedPivotKey ?: strtolower(class_basename($related)) . '_id';

        $query = "SELECT r.* FROM {$wpdb->prefix}{$relatedInstance->getTable()} r
                  JOIN {$wpdb->prefix}{$pivotTable} p ON r.id = p.{$relatedPivotKey}
                  WHERE p.{$foreignPivotKey} = %d";

        $results = $wpdb->get_results($wpdb->prepare($query, $this->attributes[$this->primaryKey]), ARRAY_A);
        $models = array_map(fn($data) => new $related($data), $results);
        return new \MJ\WPORM\Collection($models);
    }

    /**
     * @template T of Model
     * @template Through of Model
     * @param class-string<T> $related
     * @param class-string<Through> $through
     * @param string|null $firstKey
     * @param string|null $secondKey
     * @param string|null $localKey
     * @return \MJ\WPORM\Collection<T>
     */
    public function hasManyThrough($related, $through, $firstKey = null, $secondKey = null, $localKey = null): \MJ\WPORM\Collection {
        global $wpdb;
        $throughInstance = new $through;
        $relatedInstance = new $related;
        $firstKey = $firstKey ?: strtolower(class_basename($through)) . '_id';
        $secondKey = $secondKey ?: strtolower(class_basename($related)) . '_id';
        $localKey = $localKey ?: $this->primaryKey;

        $query = "SELECT r.* FROM {$wpdb->prefix}{$relatedInstance->getTable()} r
                  JOIN {$wpdb->prefix}{$throughInstance->getTable()} t ON r.{$secondKey} = t.id
                  WHERE t.{$firstKey} = %d";

        $results = $wpdb->get_results($wpdb->prepare($query, $this->$localKey), ARRAY_A);
        $models = array_map(fn($data) => new $related($data), $results);
        return new \MJ\WPORM\Collection($models);
    }

	public function newFromBuilder(array $attributes) {
		$instance = new static;
		foreach ($attributes as $key => $value) {
			$instance->attributes[$key] = $value;
		}
		// Ensure primary key property is set (for eager loading)
		if (isset($attributes[$instance->primaryKey])) {
			$instance->{$instance->primaryKey} = $attributes[$instance->primaryKey];
		}
		$instance->original = $attributes;
		$instance->exists = true;
		return $instance;
	}    

	public function toArray() {
		$attributes = [];
		foreach ($this->attributes as $key => $value) {
			$attributes[$key] = isset($this->casts[$key])
				? (new $this->casts[$key])->get($value)
				: $value;
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

    // The collectionToArray helper is no longer needed because get() now returns a Collection with toArray().
}