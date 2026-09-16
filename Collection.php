<?php
namespace MJ\WPORM;

/**
 * Eloquent-like Collection for WPORM
 */
class Collection implements \ArrayAccess, \IteratorAggregate, \Countable {
    protected $items = [];

    /**
     * The model class associated with this collection's items, if known.
     * Used by firstOrFail() to throw a meaningful ModelNotFoundException.
     * @var string|null
     */
    protected $modelClass;

    public function __construct(array $items = [], $modelClass = null) {
        $this->items = $items;
        $this->modelClass = $modelClass;
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
            return new static([], $this->modelClass);
        }
        return new static(array_slice($this->items, $index + 1), $this->modelClass);
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
        return new static(array_reverse($this->items), $this->modelClass);
    }

    public function slice($offset, $length = null) {
        return new static(array_slice($this->items, $offset, $length), $this->modelClass);
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
            $modelClass = $this->modelClass ?? static::class;
            throw (new ModelNotFoundException())->setModel($modelClass);
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
     * Determine if the collection is not empty.
     */
    public function isNotEmpty() {
        return !$this->isEmpty();
    }

    /**
     * Filter items using a callback, or remove falsy values when omitted.
     *
     * @param callable|null $callback
     * @return static
     */
    public function filter(?callable $callback = null) {
        $items = $callback === null
            ? array_filter($this->items)
            : array_filter($this->items, $callback);

        return new static($items, $this->modelClass);
    }

    /**
     * Map each item to a key/value pair and merge the result into a new collection.
     * The callback must return an associative array of the form [key => value].
     *
     * @param callable $callback
     * @return static
     */
    public function mapWithKeys(callable $callback) {
        $result = [];
        foreach ($this->items as $key => $item) {
            $mapped = $callback($item, $key);
            if (!is_array($mapped)) {
                throw new \InvalidArgumentException('Collection::mapWithKeys() callback must return an array of key/value pairs.');
            }
            foreach ($mapped as $mappedKey => $mappedValue) {
                $result[$mappedKey] = $mappedValue;
            }
        }
        return new static($result, $this->modelClass);
    }

    /**
     * Return a new collection of all items that do not pass the given truth test.
     * When no callback is supplied, it is the inverse of filter() and removes truthy values.
     *
     * @param callable|null $callback
     * @return static
     */
    public function reject(?callable $callback = null) {
        if ($callback === null) {
            return new static(array_filter($this->items, fn($item) => !$item), $this->modelClass);
        }

        return new static(array_filter($this->items, fn($item, $key) => !$callback($item, $key), ARRAY_FILTER_USE_BOTH), $this->modelClass);
    }

    /**
     * Determine if every item in the collection passes the given truth test.
     * With no callback, every item must be truthy.
     *
     * @param callable|null $callback
     * @return bool
     */
    public function every(?callable $callback = null) {
        if ($callback === null) {
            if ($this->isEmpty()) {
                return true;
            }
            foreach ($this->items as $item) {
                if (!$item) {
                    return false;
                }
            }
            return true;
        }

        foreach ($this->items as $key => $item) {
            if (!$callback($item, $key)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Return a new collection with the first $limit items from the beginning.
     * Negative values are treated like array_slice() and omit the last |$limit| values.
     *
     * @param int $limit
     * @return static
     */
    public function take($limit) {
        return $this->slice(0, $limit);
    }

    /**
     * Get a page of items from the collection.
     *
     * @param int $page
     * @param int $perPage
     * @return static
     */
    public function forPage($page, $perPage) {
        $page = max(1, (int) $page);
        $perPage = max(1, (int) $perPage);
        return $this->slice(($page - 1) * $perPage, $perPage);
    }

    /**
     * Split the collection into chunks of the given size.
     *
     * @param int $size
     * @return static
     */
    public function chunk($size) {
        $size = max(1, (int) $size);
        $chunks = array_chunk($this->items, $size, true);
        return new static(array_map(fn($chunk) => new static($chunk, $this->modelClass), $chunks), $this->modelClass);
    }

    /**
     * Split the collection into a fixed number of groups.
     *
     * @param int $numberOfGroups
     * @return array<int, static>
     */
    public function split($numberOfGroups) {
        $numberOfGroups = max(1, (int) $numberOfGroups);
        $items = array_values($this->items);
        $chunkSize = $this->isEmpty() ? 0 : (int) ceil(count($items) / $numberOfGroups);
        $chunks = $chunkSize > 0 ? array_chunk($items, $chunkSize, true) : [];
        return array_map(fn($chunk) => new static($chunk, $this->modelClass), $chunks);
    }

    /**
     * Partition the collection into two collections by a truth test.
     *
     * @param string|callable $key
     * @param mixed $operator
     * @param mixed $value
     * @return array{0: static, 1: static}
     */
    public function partition($key, $operator = null, $value = null) {
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        $passed = [];
        $failed = [];
        foreach ($this->items as $itemKey => $item) {
            $actual = $this->valueFor($item, $key);
            if ($this->compare($actual, $operator, $value)) {
                $passed[$itemKey] = $item;
            } else {
                $failed[$itemKey] = $item;
            }
        }

        return [new static($passed, $this->modelClass), new static($failed, $this->modelClass)];
    }

    /**
     * Combine the values of the collection with the given keys.
     *
     * @param array|Collection $values
     * @return static
     */
    public function combine($values) {
        $values = $values instanceof self ? $values->all() : $values;
        $combined = [];
        foreach (array_keys($this->items) as $index => $key) {
            $combined[$key] = $values[$index] ?? null;
        }
        return new static($combined, $this->modelClass);
    }

    /**
     * Zip the collection together with one or more arrays/collections.
     *
     * @param array|Collection $items
     * @return static
     */
    public function zip(...$items) {
        $lists = [];
        foreach ($items as $item) {
            $lists[] = $item instanceof self ? $item->all() : $item;
        }

        $count = count($this->items);
        $result = [];
        foreach ($this->items as $index => $item) {
            $pair = [$item];
            foreach ($lists as $list) {
                $pair[] = $list[$index] ?? null;
            }
            $result[] = $pair;
        }

        return new static($result, $this->modelClass);
    }

    /**
     * Flatten a multi-dimensional collection by one or more levels.
     *
     * @param int $depth
     * @return static
     */
    public function flatten($depth = INF) {
        $result = [];
        $this->flattenItems($this->items, $result, $depth);
        return new static($result, $this->modelClass);
    }

    /**
     * Flatten nested arrays/collections into a single list.
     *
     * @param mixed $items
     * @param array $result
     * @param int $depth
     * @param int $currentDepth
     * @return void
     */
    protected function flattenItems($items, array &$result, $depth, $currentDepth = 0) {
        foreach ($items as $item) {
            if ($item instanceof self) {
                $item = $item->all();
            }

            if (is_object($item) && method_exists($item, 'toArray')) {
                $item = $item->toArray();
            }

            if (is_array($item) && $currentDepth < $depth) {
                $this->flattenItems($item, $result, $depth, $currentDepth + 1);
                continue;
            }

            $result[] = $item;
        }
    }

    /**
     * Get only the specified keys from the collection.
     *
     * @param array|mixed $keys
     * @return static
     */
    public function only($keys) {
        if (!is_array($keys)) {
            $keys = [$keys];
        }

        $items = [];
        foreach ($keys as $key) {
            if (array_key_exists($key, $this->items)) {
                $items[$key] = $this->items[$key];
            }
        }
        return new static($items, $this->modelClass);
    }

    /**
     * Get all items except the specified keys.
     *
     * @param array|mixed $keys
     * @return static
     */
    public function except($keys) {
        if (!is_array($keys)) {
            $keys = [$keys];
        }

        $items = $this->items;
        foreach ($keys as $key) {
            unset($items[$key]);
        }
        return new static($items, $this->modelClass);
    }

    /**
     * Return a random item from the collection, or a random subset when a count is supplied.
     *
     * @param int|null $number
     * @return mixed|static
     */
    public function random($number = null) {
        if ($this->isEmpty()) {
            return $number === null ? null : new static([], $this->modelClass);
        }

        $items = array_values($this->items);
        if ($number === null) {
            return $items[array_rand($items)];
        }

        $number = max(0, (int) $number);
        $count = min($number, count($items));
        if ($count === 0) {
            return new static([], $this->modelClass);
        }

        $shuffled = $items;
        shuffle($shuffled);
        return new static(array_slice($shuffled, 0, $count), $this->modelClass);
    }

    /**
     * Get every nth item from the collection.
     *
     * @param int $step
     * @param int $offset
     * @return static
     */
    public function nth($step, $offset = 0) {
        $step = max(1, (int) $step);
        $offset = max(0, (int) $offset);
        $items = array_values($this->items);
        $result = [];
        for ($i = $offset; $i < count($items); $i += $step) {
            $result[] = $items[$i];
        }
        return new static($result, $this->modelClass);
    }

    /**
     * Remove and return the last item from the collection.
     *
     * @return mixed
     */
    public function pop() {
        if ($this->isEmpty()) {
            return null;
        }
        return array_pop($this->items);
    }

    /**
     * Remove and return the first item from the collection.
     *
     * @return mixed
     */
    public function shift() {
        if ($this->isEmpty()) {
            return null;
        }
        return array_shift($this->items);
    }

    /**
     * Prepend an item to the beginning of the collection.
     *
     * @param mixed $value
     * @param mixed $key
     * @return $this
     */
    public function prepend($value, $key = null) {
        if ($key === null) {
            array_unshift($this->items, $value);
            return $this;
        }

        $this->items = array_merge([$key => $value], $this->items);
        return $this;
    }

    /**
     * Map items using a callback.
     */
    public function map(callable $callback) {
        return new static(array_map($callback, $this->items), $this->modelClass);
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

        $segments = is_string($key) && strpos($key, '.') !== false ? explode('.', $key) : [$key];
        $current = $item;

        foreach ($segments as $segment) {
            if (is_array($current)) {
                if (!array_key_exists($segment, $current)) {
                    return null;
                }
                $current = $current[$segment];
                continue;
            }

            if (is_object($current)) {
                if (isset($current->{$segment})) {
                    $current = $current->{$segment};
                    continue;
                }

                if (property_exists($current, $segment)) {
                    $current = $current->{$segment};
                    continue;
                }

                return null;
            }

            return null;
        }

        return $current;
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
        return new static($result, $this->modelClass);
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
        return new static($items, $this->modelClass);
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
        return new static(array_map(fn($g) => new static($g, $this->modelClass), $groups), $this->modelClass);
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
        return new static($result, $this->modelClass);
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
            return new static(array_values($result), $this->modelClass);
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
        return new static(array_values($result), $this->modelClass);
    }

    /**
     * Reset the collection's keys to sequential integers, discarding the
     * original keys (Eloquent-style values()). Useful after groupBy(),
     * keyBy(), filter(), or unique() leave non-sequential/string keys.
     *
     * @return static
     */
    public function values() {
        return new static(array_values($this->items), $this->modelClass);
    }

    /**
     * Get a new Collection containing this collection's keys (Eloquent-style
     * keys()).
     *
     * @return static
     */
    public function keys() {
        return new static(array_keys($this->items), $this->modelClass);
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
        return new static(array_values($result), $this->modelClass);
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
        return new static(array_values($result), $this->modelClass);
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
        return new static(array_merge($this->items, $merge), $this->modelClass);
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
        return new static(array_map(fn($g) => new static($g, $this->modelClass), $groups), $this->modelClass);
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
