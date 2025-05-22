<?php
namespace WPORM;

use WPORM\QueryBuilder;
use WPORM\Casts\CastableInterface;
use WPORM\Schema\Blueprint;
use WPORM\Schema\SchemaBuilder;

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
				(empty($this->fillable) || in_array($key, $this->fillable)) &&
				!in_array($key, $this->guarded)
			) {
				$this->__set($key, $value);
			}
		}
		return $this;
	}

	public function __get($key) {
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
			(empty($this->fillable) || in_array($key, $this->fillable)) &&
			!in_array($key, $this->guarded)
		) {
			$this->attributes[$key] = $this->castSet($key, $value);
		}
	}

	public function __call($method, $parameters) {
		// Handle dynamic scopes: scopeXyz()
		if (strpos($method, 'scope') === 0 && method_exists($this, $method)) {
			return $this->$method(...$parameters);
		}
		throw new \BadMethodCallException("Method {$method} does not exist.");
	}

	protected function castGet($key, $value) {
		if (!isset($this->casts[$key])) return $value;
		$cast = $this->casts[$key];
		if (class_exists($cast)) {
			$castInstance = new $cast();
			if ($castInstance instanceof CastableInterface) {
				return $castInstance->get($value);
			}
		}
		return $value;
	}

	protected function castSet($key, $value) {
		if (!isset($this->casts[$key])) return $value;
		$cast = $this->casts[$key];
		if (class_exists($cast)) {
			$castInstance = new $cast();
			if ($castInstance instanceof CastableInterface) {
				return $castInstance->set($value);
			}
		}
		return $value;
	}

	public static function query() {
		return new QueryBuilder(new static);
	}

	public static function all() {
		return static::query()->get();
	}

	public static function find($id) {
		return static::query()->where('id', $id)->first();
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
			$this->attributes['created_at'] = $now;
			$this->attributes['updated_at'] = $now;
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
			$this->attributes['updated_at'] = current_time('mysql');
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

	public function getTable() {
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
	public function hasOne($related, $foreignKey = null, $localKey = null) {
		$instance = new $related;
		$foreignKey = $foreignKey ?: strtolower(class_basename(static::class)) . '_id';
		$localKey = $localKey ?: $this->primaryKey;
		return $related::query()->where($foreignKey, $this->$localKey)->first();
	}

	public function hasMany($related, $foreignKey = null, $localKey = null) {
		$instance = new $related;
		$foreignKey = $foreignKey ?: strtolower(class_basename(static::class)) . '_id';
		$localKey = $localKey ?: $this->primaryKey;
		return $related::query()->where($foreignKey, $this->$localKey)->get();
	}

	public function belongsTo($related, $foreignKey = null, $ownerKey = null) {
		$instance = new $related;
		$foreignKey = $foreignKey ?: strtolower(class_basename($related)) . '_id';
		$ownerKey = $ownerKey ?: $instance->primaryKey;
		return $related::query()->where($ownerKey, $this->$foreignKey)->first();
	}

	public function belongsToMany($related, $pivotTable = null, $foreignPivotKey = null, $relatedPivotKey = null) {
		global $wpdb;
		$relatedInstance = new $related;
		$pivotTable = $pivotTable ?: $this->getTable() . '_' . $relatedInstance->getTable();
		$foreignPivotKey = $foreignPivotKey ?: strtolower(class_basename(static::class)) . '_id';
		$relatedPivotKey = $relatedPivotKey ?: strtolower(class_basename($related)) . '_id';

		$query = "SELECT r.* FROM {$wpdb->prefix}{$relatedInstance->getTable()} r
				  JOIN {$wpdb->prefix}{$pivotTable} p ON r.id = p.{$relatedPivotKey}
				  WHERE p.{$foreignPivotKey} = %d";

		$results = $wpdb->get_results($wpdb->prepare($query, $this->attributes[$this->primaryKey]), ARRAY_A);
		return array_map(fn($data) => new $related($data), $results);
	}

	public function hasManyThrough($related, $through, $firstKey = null, $secondKey = null, $localKey = null) {
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
		return array_map(fn($data) => new $related($data), $results);
	}

	public function newFromBuilder(array $attributes) {
		$instance = new static;
	
		foreach ($attributes as $key => $value) {
			$instance->$key = $value;
		}
	
		$instance->original = $attributes;
		$instance->exists = true;
	
		return $instance;
	}    

	public function toArray() {
		$attributes = [];
		$publicVars = get_object_vars($this);
	
		if( !empty( $publicVars['attributes'] ) ) {
			foreach($publicVars['attributes'] as $key => $value) {
		
				if (isset($this->casts[$key])) {
					$castClass = $this->casts[$key];
					$caster = new $castClass;
					$value = $caster->get($value);
				}
		
				$attributes[$key] = $value;
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
	
		foreach (get_object_vars($this) as $key => $value) {
			if (in_array($key, ['fillable', 'guarded', 'casts', 'exists', 'original'])) {
				continue;
			}
	
			if (!array_key_exists($key, $this->original)) {
				continue;
			}
	
			if ($value !== $this->original[$key]) {
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
}