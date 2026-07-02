<?php
namespace MJ\WPORM;

/**
 * Eloquent-like Collection for WPORM
 */
class Collection implements \ArrayAccess, \IteratorAggregate, \Countable {
    protected $items = [];

    public function __construct(array $items = []) {
        $this->items = $items;
    }

    /**
     * Get the items after a given value (first occurrence).
     *
     * @param mixed $value
     * @return static
     */
    public function after($value, $strict = true)
    {
        $index = array_search($value, $this->items, $strict);
        if ($index === false) {
            return new static([]);
        }
        return new static(array_slice($this->items, $index + 1));
    }

    public function toArray() {
        return array_map(function($item) {
            return method_exists($item, 'toArray') ? $item->toArray() : (array)$item;
        }, $this->items);
    }

    /**
     * Convert the collection to its JSON representation.
     * Respects each model's $hidden/$visible via toArray().
     *
     * Mirrors Eloquent's behavior: if json_encode() fails, a \JsonException
     * is thrown rather than silently returning `false`.
     *
     * @param int $options json_encode() options
     * @return string
     * @throws \JsonException
     */
    public function toJson($options = 0) {
        $json = json_encode($this->toArray(), $options);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \JsonException('Error encoding collection to JSON: ' . json_last_error_msg());
        }

        return $json;
    }

    /**
     * Convert the collection to its string representation (Eloquent-style).
     * Allows a collection to be used directly in string contexts, e.g.
     * `echo $users;`, producing the same output as `toJson()`.
     *
     * @return string
     */
    public function __toString() {
        return $this->toJson();
    }

    public function all() {
        return $this->items;
    }

    /**
     * Reverse the order of the items in the collection.
     *
     * @return static
     */
    public function reverse() {
        return new static(array_reverse($this->items));
    }

    public function slice($offset, $length = null) {
        return new static(array_slice($this->items, $offset, $length));
    }

    // Countable
    public function count(): int {
        return count($this->items);
    }
    // IteratorAggregate
    public function getIterator(): \Traversable {
        return new \ArrayIterator($this->items);
    }
    // ArrayAccess
    public function offsetExists($offset): bool {
        return isset($this->items[$offset]);
    }
    #[\ReturnTypeWillChange]
    public function offsetGet($offset) {
        return isset($this->items[$offset]) ? $this->items[$offset] : null;
    }
    public function offsetSet($offset, $value): void {
        if (is_null($offset)) {
            $this->items[] = $value;
        } else {
            $this->items[$offset] = $value;
        }
    }
    public function offsetUnset($offset): void {
        unset($this->items[$offset]);
    }

    /**
     * Get the first item in the collection.
     */
    public function first() {
        if (empty($this->items)) {
            return null;
        }
        return reset($this->items);
    }

    /**
     * Get the first item in the collection, or throw a ModelNotFoundException
     * if the collection is empty (Eloquent-style).
     *
     * Useful after in-memory filtering (e.g. ->filter(...)->firstOrFail())
     * where the underlying query already ran and a query-builder-level
     * firstOrFail() is no longer an option.
     *
     * Checks isEmpty() (rather than first() === null) so a collection whose
     * first item happens to be a falsy value (0, false, '') is not mistaken
     * for an empty collection.
     *
     * @return mixed
     * @throws ModelNotFoundException
     */
    public function firstOrFail() {
        if ($this->isEmpty()) {
            // The collection has no model context of its own (it's just a
            // plain bag of items), so the exception names the Collection
            // class itself rather than a specific model.
            throw (new ModelNotFoundException())->setModel(static::class);
        }

        return $this->first();
    }

    /**
     * Get the last item in the collection.
     */
    public function last() {
        return empty($this->items) ? null : end($this->items);
    }

    /**
     * Pluck a value from each item in the collection (uses wp_list_pluck if available).
     * @param string $key
     * @param string|null $indexKey
     * @return array
     */
    public function pluck($key, $indexKey = null) {
        if (function_exists('wp_list_pluck')) {
            return wp_list_pluck($this->toArray(), $key, $indexKey);
        }
        $results = [];
        foreach ($this->items as $item) {
            $array = method_exists($item, 'toArray') ? $item->toArray() : (array)$item;
            if ($indexKey !== null && isset($array[$indexKey])) {
                $results[$array[$indexKey]] = $array[$key] ?? null;
            } else {
                $results[] = $array[$key] ?? null;
            }
        }
        return $results;
    }

    /**
     * Determine if the collection is empty.
     */
    public function isEmpty() {
        return empty($this->items);
    }

    /**
     * Filter items using a callback.
     */
    public function filter(callable $callback) {
        return new static(array_filter($this->items, $callback));
    }

    /**
     * Map items using a callback.
     */
    public function map(callable $callback) {
        return new static(array_map($callback, $this->items));
    }

    /**
     * Transform each item in the collection using a callback (mutates in-place).
     * Unlike map(), which returns a new collection, transform() modifies the current collection.
     *
     * @param callable $callback
     * @return $this
     */
    public function transform(callable $callback) {
        $this->items = array_map($callback, $this->items);

        return $this;
    }

    /**
     * Determine if the collection contains a given value (strict).
     */
    public function contains($value) {
        return in_array($value, $this->items, true);
    }

    /**
     * Pass the collection to the given callback for side-effects, then return
     * the collection unchanged (Eloquent-style tap()). The callback's return
     * value is always discarded. Designed for inline debugging, logging, or
     * inspection without breaking a fluent chain.
     *
     * Usage:
     *   $emails = User::query()->get()
     *       ->filter(fn($u) => $u->active)
     *       ->tap(fn($c) => error_log('Active count: ' . $c->count()))
     *       ->pluck('email');
     *
     * @param callable $callback function(Collection $collection): void
     * @return $this
     */
    public function tap(callable $callback): self {
        $callback($this);
        return $this;
    }

    /**
     * Pass the collection to the given callback and return whatever the
     * callback returns (Eloquent-style pipe()). Unlike tap(), the callback's
     * return value IS used — pipe() terminates or transforms the chain.
     * Useful for handing the collection off to another layer (e.g. a
     * formatter, a presenter, or a further processing step) and returning
     * its result inline without breaking the fluent style.
     *
     * Usage:
     *   $result = User::query()->get()
     *       ->filter(fn($u) => $u->active)
     *       ->pipe(fn($c) => $c->pluck('email'));
     *
     *   // Hand off to a service/presenter:
     *   $dto = User::query()->get()
     *       ->pipe([$userPresenter, 'toDto']);
     *
     * @param callable $callback function(Collection $collection): mixed
     * @return mixed Whatever the callback returns
     */
    public function pipe(callable $callback) {
        return $callback($this);
    }

    /**
     * Resolve a "value extractor" for a given item against a string key
     * (dot-notation NOT supported, matching pluck()'s existing semantics),
     * a callable, or null (identity). Shared by sortBy(), groupBy(), keyBy(),
     * unique(), firstWhere(), sum(), avg(), min(), max(), etc. so they all
     * agree on how to pull a comparison/grouping value out of an item,
     * whether the item is a Model (object with __get) or a plain array.
     *
     * @param mixed $item
     * @param string|callable|null $key
     * @return mixed
     */
    protected function valueFor($item, $key) {
        if ($key === null) {
            return $item;
        }
        if (is_callable($key) && !is_string($key)) {
            return $key($item);
        }
        if (is_array($item)) {
            return $item[$key] ?? null;
        }
        if (is_object($item)) {
            return $item->$key ?? null;
        }
        return null;
    }

    /**
     * Iterate over each item in the collection, invoking the callback with
     * the item and its key/index (Eloquent-style each()). Returning `false`
     * from the callback stops iteration early, mirroring QueryBuilder::each().
     * Always returns $this so it can still be used at the start of a chain
     * even though it's primarily intended for side-effects.
     *
     * Usage:
     *   $users->each(function ($user, $key) {
     *       // ...
     *       if ($shouldStop) return false; // stops iteration
     *   });
     *
     * @param callable $callback function(mixed $item, int|string $key): mixed
     * @return $this
     */
    public function each(callable $callback) {
        foreach ($this->items as $key => $item) {
            if ($callback($item, $key) === false) {
                break;
            }
        }
        return $this;
    }

    /**
     * Reduce the collection to a single value, passing the carry and each
     * item to the callback in turn (Eloquent-style reduce()).
     *
     * Usage:
     *   $total = $orders->reduce(fn($carry, $order) => $carry + $order->total, 0);
     *
     * @param callable $callback function(mixed $carry, mixed $item): mixed
     * @param mixed $initial
     * @return mixed
     */
    public function reduce(callable $callback, $initial = null) {
        return array_reduce($this->items, $callback, $initial);
    }

    /**
     * Map each item using the callback, then flatten the result by one level
     * (Eloquent-style flatMap()). Useful when the callback returns an array
     * (or Collection) per item and you want a single flat collection back.
     *
     * Usage:
     *   $tags = $posts->flatMap(fn($post) => $post->tags); // flattens tag arrays into one list
     *
     * @param callable $callback function(mixed $item, int|string $key): mixed
     * @return static
     */
    public function flatMap(callable $callback) {
        $result = [];
        foreach ($this->items as $key => $item) {
            $mapped = $callback($item, $key);
            if ($mapped instanceof self) {
                $mapped = $mapped->all();
            }
            if (is_array($mapped)) {
                foreach ($mapped as $value) {
                    $result[] = $value;
                }
            } else {
                $result[] = $mapped;
            }
        }
        return new static($result);
    }

    /**
     * Sort the collection by the given key (string column name) or callback,
     * preserving keys (Eloquent-style sortBy()). Use values() afterward if
     * you need sequential integer keys.
     *
     * Usage:
     *   $sorted = $users->sortBy('name');
     *   $sorted = $users->sortBy(fn($user) => $user->profile->rank);
     *   $sorted = $users->sortBy('votes', true); // descending
     *
     * @param string|callable $key
     * @param bool $descending
     * @return static
     */
    public function sortBy($key, $descending = false) {
        $items = $this->items;
        uasort($items, function ($a, $b) use ($key) {
            $valA = $this->valueFor($a, $key);
            $valB = $this->valueFor($b, $key);
            return $valA <=> $valB;
        });
        if ($descending) {
            $items = array_reverse($items, true);
        }
        return new static($items);
    }

    /**
     * Sort the collection by the given key/callback in descending order
     * (Eloquent-style sortByDesc()). Shorthand for sortBy($key, true).
     *
     * @param string|callable $key
     * @return static
     */
    public function sortByDesc($key) {
        return $this->sortBy($key, true);
    }

    /**
     * Group the collection's items by the value of a given key or callback
     * result (Eloquent-style groupBy()). Returns a Collection of Collections,
     * keyed by the distinct grouping values.
     *
     * Usage:
     *   $byRole = $users->groupBy('role'); // ['admin' => Collection, 'editor' => Collection]
     *   $byYear = $orders->groupBy(fn($o) => date('Y', strtotime($o->created_at)));
     *
     * @param string|callable $key
     * @return static A Collection whose items are themselves Collections.
     */
    public function groupBy($key) {
        $groups = [];
        foreach ($this->items as $item) {
            $groupKey = $this->valueFor($item, $key);
            // Coerce to a string-safe array key (objects/null would error otherwise).
            if (is_object($groupKey) || is_array($groupKey)) {
                $groupKey = (string) json_encode($groupKey);
            } elseif ($groupKey === null) {
                $groupKey = '';
            }
            $groups[$groupKey][] = $item;
        }
        return new static(array_map(fn($g) => new static($g), $groups));
    }

    /**
     * Re-key the collection's items by the value of a given key or callback
     * result (Eloquent-style keyBy()). If multiple items share the same key
     * value, the last one wins (matching Eloquent's behavior).
     *
     * Usage:
     *   $byEmail = $users->keyBy('email'); // ['a@test.com' => User, ...]
     *   $byId = $users->keyBy(fn($u) => $u->id);
     *
     * @param string|callable $key
     * @return static
     */
    public function keyBy($key) {
        $result = [];
        foreach ($this->items as $item) {
            $itemKey = $this->valueFor($item, $key);
            if (is_object($itemKey) || is_array($itemKey)) {
                $itemKey = (string) json_encode($itemKey);
            }
            $result[$itemKey] = $item;
        }
        return new static($result);
    }

    /**
     * Get the unique items in the collection (Eloquent-style unique()).
     * Without a key, uniqueness is determined by loose string comparison of
     * the whole item (matching Eloquent's default). With a key/callback,
     * only the first item per distinct extracted value is kept.
     *
     * Usage:
     *   $unique = $collection->unique();
     *   $uniqueByEmail = $users->unique('email');
     *   $uniqueByDomain = $users->unique(fn($u) => strstr($u->email, '@'));
     *
     * @param string|callable|null $key
     * @return static
     */
    public function unique($key = null) {
        if ($key === null) {
            $seen = [];
            $result = [];
            foreach ($this->items as $itemKey => $item) {
                if (is_object($item)) {
                    $hash = spl_object_id($item);
                } elseif (is_array($item)) {
                    $hash = json_encode($item);
                } else {
                    $hash = $item;
                }
                if (!array_key_exists($hash, $seen)) {
                    $seen[$hash] = true;
                    $result[$itemKey] = $item;
                }
            }
            return new static(array_values($result));
        }
        $seen = [];
        $result = [];
        foreach ($this->items as $itemKey => $item) {
            $value = $this->valueFor($item, $key);
            $hash = is_scalar($value) || $value === null ? (string) $value : json_encode($value);
            if (!array_key_exists($hash, $seen)) {
                $seen[$hash] = true;
                $result[$itemKey] = $item;
            }
        }
        return new static(array_values($result));
    }

    /**
     * Reset the collection's keys to sequential integers, discarding the
     * original keys (Eloquent-style values()). Useful after groupBy(),
     * keyBy(), filter(), or unique() leave non-sequential/string keys.
     *
     * @return static
     */
    public function values() {
        return new static(array_values($this->items));
    }

    /**
     * Get a new Collection containing this collection's keys (Eloquent-style
     * keys()).
     *
     * @return static
     */
    public function keys() {
        return new static(array_keys($this->items));
    }

    /**
     * Get the items in this collection that are NOT present in the given
     * array/Collection (Eloquent-style diff()), compared loosely via
     * array_diff(). For object items (e.g. models), comparison falls back to
     * PHP's loose equality, which compares object contents — pass a plain
     * array of scalars (e.g. via pluck()) for predictable results with
     * models.
     *
     * @param array|Collection $items
     * @return static
     */
    public function diff($items) {
        $compare = $items instanceof self ? $items->all() : $items;
        $result = array_filter($this->items, function($item) use ($compare) {
            return !in_array($item, $compare, true);
        });
        return new static(array_values($result));
    }

    /**
     * Get the items in this collection that ARE present in the given
     * array/Collection (Eloquent-style intersect()).
     *
     * @param array|Collection $items
     * @return static
     */
    public function intersect($items) {
        $compare = $items instanceof self ? $items->all() : $items;
        $result = array_filter($this->items, function($item) use ($compare) {
            return in_array($item, $compare, true);
        });
        return new static(array_values($result));
    }

    /**
     * Merge the given array/Collection into this collection (Eloquent-style
     * merge()). Numeric keys are renumbered/appended; string keys in
     * $items overwrite matching string keys in this collection — i.e. the
     * same semantics as PHP's array_merge().
     *
     * @param array|Collection $items
     * @return static
     */
    public function merge($items) {
        $merge = $items instanceof self ? $items->all() : $items;
        return new static(array_merge($this->items, $merge));
    }

    /**
     * Push an item onto the end of the collection, mutating it in place
     * (Eloquent-style push()). Returns $this for chaining.
     *
     * @param mixed $value
     * @return $this
     */
    public function push($value) {
        $this->items[] = $value;
        return $this;
    }

    /**
     * Remove and return an item from the collection by key, mutating it in
     * place (Eloquent-style pull()). Returns $default if the key isn't set.
     *
     * @param int|string $key
     * @param mixed $default
     * @return mixed
     */
    public function pull($key, $default = null) {
        if (!array_key_exists($key, $this->items)) {
            return $default;
        }
        $value = $this->items[$key];
        unset($this->items[$key]);
        return $value;
    }

    /**
     * Set an item in the collection by key, mutating it in place
     * (Eloquent-style put()). Equivalent to $collection[$key] = $value.
     * Returns $this for chaining.
     *
     * @param int|string $key
     * @param mixed $value
     * @return $this
     */
    public function put($key, $value) {
        $this->items[$key] = $value;
        return $this;
    }

    /**
     * Join the collection's items into a single string, optionally
     * extracting a key/column from each item first (Eloquent-style
     * implode()). If $key is omitted, items are cast to string directly
     * (e.g. for a collection of plain scalars).
     *
     * Usage:
     *   $csv = $tags->implode(', ');                 // plain scalar items
     *   $names = $users->implode(', ', 'name');       // extract a column first
     *
     * @param string $glue
     * @param string|null $key
     * @return string
     */
    public function implode($glue, $key = null) {
        $values = $key === null
            ? $this->items
            : array_map(fn($item) => $this->valueFor($item, $key), $this->items);
        return implode($glue, $values);
    }

    /**
     * Conditionally execute a callback against the collection (Eloquent-style
     * when()). If $value is truthy, $callback($this, $value) is invoked and
     * its result returned; otherwise $default (if given) is invoked the same
     * way. If neither runs, $this is returned unchanged — so when() is always
     * safe to use mid-chain.
     *
     * Usage:
     *   $users = $collection->when($isAdmin, fn($c) => $c->where('role', 'admin'));
     *
     * @param mixed $value
     * @param callable $callback function(Collection $collection, mixed $value): mixed
     * @param callable|null $default function(Collection $collection, mixed $value): mixed
     * @return mixed
     */
    public function when($value, callable $callback, ?callable $default = null) {
        if ($value) {
            return $callback($this, $value) ?? $this;
        }
        if ($default) {
            return $default($this, $value) ?? $this;
        }
        return $this;
    }

    /**
     * Inverse of when() — runs the callback when $value is falsy
     * (Eloquent-style unless()).
     *
     * @param mixed $value
     * @param callable $callback function(Collection $collection, mixed $value): mixed
     * @param callable|null $default function(Collection $collection, mixed $value): mixed
     * @return mixed
     */
    public function unless($value, callable $callback, ?callable $default = null) {
        return $this->when(!$value, $callback, $default);
    }

    /**
     * Get the first item matching a simple key/operator/value condition
     * (Eloquent-style firstWhere()). Supports the same 2-arg ('key', $value)
     * and 3-arg ('key', $operator, $value) forms as QueryBuilder::where().
     *
     * Usage:
     *   $admin = $users->firstWhere('role', 'admin');
     *   $cheap = $products->firstWhere('price', '<', 100);
     *
     * @param string $key
     * @param mixed $operator
     * @param mixed $value
     * @return mixed|null
     */
    public function firstWhere($key, $operator = null, $value = null) {
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }
        foreach ($this->items as $item) {
            $actual = $this->valueFor($item, $key);
            if ($this->compare($actual, $operator, $value)) {
                return $item;
            }
        }
        return null;
    }

    /**
     * Evaluate a simple comparison between two values for firstWhere().
     *
     * @param mixed $actual
     * @param string $operator
     * @param mixed $expected
     * @return bool
     */
    protected function compare($actual, $operator, $expected) {
        switch ($operator) {
            case '=':
            case '==':
                return $actual == $expected;
            case '!=':
            case '<>':
                return $actual != $expected;
            case '>':
                return $actual > $expected;
            case '>=':
                return $actual >= $expected;
            case '<':
                return $actual < $expected;
            case '<=':
                return $actual <= $expected;
            case '===':
                return $actual === $expected;
            case '!==':
                return $actual !== $expected;
            default:
                return false;
        }
    }

    /**
     * Map each item to a [groupKey => value] pair via the callback, then
     * group all values under their respective group keys (Eloquent-style
     * mapToGroups()). Unlike groupBy() (which groups by an existing column),
     * mapToGroups() lets the callback compute BOTH the group key and the
     * value to store per item in one pass.
     *
     * Usage:
     *   $byRole = $users->mapToGroups(fn($u) => [$u->role => $u->name]);
     *   // ['admin' => Collection['Alice', 'Bob'], 'editor' => Collection['Carol']]
     *
     * @param callable $callback function(mixed $item, int|string $key): array Single [groupKey => value] pair
     * @return static A Collection of Collections, keyed by group.
     */
    public function mapToGroups(callable $callback) {
        $groups = [];
        foreach ($this->items as $key => $item) {
            $pair = $callback($item, $key);
            foreach ($pair as $groupKey => $value) {
                $groups[$groupKey][] = $value;
                break; // only one pair per item, matching Eloquent's contract
            }
        }
        return new static(array_map(fn($g) => new static($g), $groups));
    }

    /**
     * Get the sum of the collection's values, optionally extracting a
     * key/column from each item first (Eloquent-style sum()).
     *
     * Usage:
     *   $total = $orders->sum('total');
     *   $total = $orders->sum(fn($o) => $o->total * $o->qty);
     *
     * @param string|callable|null $key
     * @return int|float
     */
    public function sum($key = null) {
        $total = 0;
        foreach ($this->items as $item) {
            $value = $key === null ? $item : $this->valueFor($item, $key);
            $total += (float) $value;
        }
        // Promote to int when the result is a whole number, matching the
        // query-builder-level sum()'s "+0" numeric promotion behavior.
        return $total == (int) $total ? (int) $total : $total;
    }

    /**
     * Get the average of the collection's values, optionally extracting a
     * key/column from each item first (Eloquent-style avg()/average()).
     * Returns null for an empty collection.
     *
     * @param string|callable|null $key
     * @return int|float|null
     */
    public function avg($key = null) {
        if ($this->isEmpty()) {
            return null;
        }
        return $this->sum($key) / $this->count();
    }

    /**
     * Alias for avg().
     *
     * @param string|callable|null $key
     * @return int|float|null
     */
    public function average($key = null) {
        return $this->avg($key);
    }

    /**
     * Get the minimum value in the collection, optionally extracting a
     * key/column from each item first (Eloquent-style min()). Returns null
     * for an empty collection.
     *
     * @param string|callable|null $key
     * @return mixed|null
     */
    public function min($key = null) {
        if ($this->isEmpty()) {
            return null;
        }
        $values = $key === null ? $this->items : array_map(fn($item) => $this->valueFor($item, $key), $this->items);
        return min($values);
    }

    /**
     * Get the maximum value in the collection, optionally extracting a
     * key/column from each item first (Eloquent-style max()). Returns null
     * for an empty collection.
     *
     * @param string|callable|null $key
     * @return mixed|null
     */
    public function max($key = null) {
        if ($this->isEmpty()) {
            return null;
        }
        $values = $key === null ? $this->items : array_map(fn($item) => $this->valueFor($item, $key), $this->items);
        return max($values);
    }
}
