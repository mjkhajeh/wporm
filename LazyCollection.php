<?php
namespace MJ\WPORM;

/**
 * A replayable, generator-backed collection for lazy transformations.
 *
 * Sources may be arrays, Traversable instances, or callables that return
 * either. A callable source is evaluated once per iteration.
 */
class LazyCollection implements \IteratorAggregate, \Countable {
    protected $source;
    protected $modelClass;

    /**
     * @param iterable|callable $source
     * @param string|null $modelClass
     */
    public function __construct($source = [], $modelClass = null) {
        if (!is_array($source) && !($source instanceof \Traversable) && !is_callable($source)) {
            throw new \InvalidArgumentException('A LazyCollection source must be iterable or callable.');
        }

        $this->source = $source;
        $this->modelClass = $modelClass;
    }

    /**
     * Create a lazy collection from an iterable or source callback.
     *
     * @param iterable|callable $source
     * @return static
     */
    public static function make($source = []) {
        return new static($source);
    }

    /**
     * Yield the source items. Callable sources are invoked for each traversal.
     *
     * @return \Traversable
     */
    public function getIterator(): \Traversable {
        $source = $this->source;
        if (!is_array($source) && !($source instanceof \Traversable) && is_callable($source)) {
            $source = $source();
        }

        if (is_array($source) || $source instanceof \Traversable) {
            yield from $source;
            return;
        }

        throw new \UnexpectedValueException('A LazyCollection source callback must return an iterable value.');
    }

    /**
     * Return all items as an array. This consumes the entire source.
     *
     * @return array
     */
    public function all() {
        return iterator_to_array($this->getIterator());
    }

    /**
     * Materialize this lazy collection as a regular WPORM collection.
     *
     * @return Collection
     */
    public function collect() {
        return new Collection($this->all(), $this->modelClass);
    }

    /**
     * Return a new lazy collection with mapped values.
     *
     * @return static
     */
    public function map(callable $callback) {
        $source = $this;
        return new static(function () use ($source, $callback) {
            foreach ($source as $key => $value) {
                yield $key => $callback($value, $key);
            }
        });
    }

    /**
     * Filter items without materializing the source.
     *
     * @param callable|null $callback Omitted means remove falsy values
     * @return static
     */
    public function filter(?callable $callback = null) {
        $source = $this;
        return new static(function () use ($source, $callback) {
            foreach ($source as $key => $value) {
                if ($callback === null ? (bool) $value : $callback($value, $key)) {
                    yield $key => $value;
                }
            }
        }, $this->modelClass);
    }

    /**
     * Reject items matching the callback.
     *
     * @param callable|null $callback
     * @return static
     */
    public function reject(?callable $callback = null) {
        if ($callback === null) {
            return $this->filter(function ($value) {
                return !$value;
            });
        }

        return $this->filter(function ($value, $key) use ($callback) {
            return !$callback($value, $key);
        });
    }

    /**
     * Map each item to key/value pairs and lazily yield the combined items.
     *
     * @return static
     */
    public function mapWithKeys(callable $callback) {
        $source = $this;
        return new static(function () use ($source, $callback) {
            foreach ($source as $key => $value) {
                $mapped = $callback($value, $key);
                if (!is_array($mapped)) {
                    throw new \InvalidArgumentException('LazyCollection::mapWithKeys() callback must return an array.');
                }
                foreach ($mapped as $mappedKey => $mappedValue) {
                    yield $mappedKey => $mappedValue;
                }
            }
        });
    }

    /**
     * Map each item and flatten one level.
     *
     * @return static
     */
    public function flatMap(callable $callback) {
        $source = $this;
        return new static(function () use ($source, $callback) {
            foreach ($source as $key => $value) {
                $mapped = $callback($value, $key);
                if (is_array($mapped) || $mapped instanceof \Traversable) {
                    foreach ($mapped as $mappedValue) {
                        yield $mappedValue;
                    }
                } else {
                    yield $mapped;
                }
            }
        });
    }

    /**
     * Call the callback for each item, stopping if it returns false.
     *
     * @return $this
     */
    public function each(callable $callback) {
        foreach ($this as $key => $value) {
            if ($callback($value, $key) === false) {
                break;
            }
        }

        return $this;
    }

    /**
     * Yield at most the requested number of items.
     *
     * @return static
     */
    public function take($limit) {
        $limit = (int) $limit;
        $source = $this;

        return new static(function () use ($source, $limit) {
            if ($limit <= 0) {
                return;
            }

            $taken = 0;
            foreach ($source as $key => $value) {
                yield $key => $value;
                if (++$taken >= $limit) {
                    break;
                }
            }
        }, $this->modelClass);
    }

    /**
     * Skip the first requested number of items.
     *
     * @return static
     */
    public function skip($count) {
        $count = max(0, (int) $count);
        $source = $this;

        return new static(function () use ($source, $count) {
            $skipped = 0;
            foreach ($source as $key => $value) {
                if ($skipped++ < $count) {
                    continue;
                }
                yield $key => $value;
            }
        }, $this->modelClass);
    }

    /**
     * Yield items until the callback first returns false.
     *
     * @return static
     */
    public function takeWhile(callable $callback) {
        $source = $this;
        return new static(function () use ($source, $callback) {
            foreach ($source as $key => $value) {
                if (!$callback($value, $key)) {
                    break;
                }
                yield $key => $value;
            }
        }, $this->modelClass);
    }

    /**
     * Skip items while the callback returns true, then yield the remainder.
     *
     * @return static
     */
    public function skipWhile(callable $callback) {
        $source = $this;
        return new static(function () use ($source, $callback) {
            $skipping = true;
            foreach ($source as $key => $value) {
                if ($skipping && $callback($value, $key)) {
                    continue;
                }
                $skipping = false;
                yield $key => $value;
            }
        }, $this->modelClass);
    }

    /**
     * Yield only the first item for each extracted value.
     *
     * @param string|callable|null $key
     * @return static
     */
    public function unique($key = null) {
        $source = $this;
        return new static(function () use ($source, $key) {
            $seen = [];
            foreach ($source as $itemKey => $item) {
                $value = $this->valueFor($item, $key);
                if (in_array($value, $seen, true)) {
                    continue;
                }
                $seen[] = $value;
                yield $itemKey => $item;
            }
        }, $this->modelClass);
    }

    /**
     * Yield items keyed by the extracted value.
     *
     * @param string|callable $key
     * @return static
     */
    public function keyBy($key) {
        $source = $this;
        return new static(function () use ($source, $key) {
            foreach ($source as $item) {
                yield $this->valueFor($item, $key) => $item;
            }
        }, $this->modelClass);
    }

    /**
     * Reindex items with sequential integer keys.
     *
     * @return static
     */
    public function values() {
        $source = $this;
        return new static(function () use ($source) {
            foreach ($source as $value) {
                yield $value;
            }
        }, $this->modelClass);
    }

    /**
     * Lazily yield the keys of the source.
     *
     * @return static
     */
    public function keys() {
        $source = $this;
        return new static(function () use ($source) {
            foreach ($source as $key => $_) {
                yield $key;
            }
        });
    }

    /**
     * Return the first item, optionally matching a predicate.
     *
     * @param callable|null $callback
     * @param mixed $default
     * @return mixed
     */
    public function first(?callable $callback = null, $default = null) {
        foreach ($this as $key => $value) {
            if ($callback === null || $callback($value, $key)) {
                return $value;
            }
        }

        return is_callable($default) ? $default() : $default;
    }

    /**
     * Return the final item. This consumes the entire source.
     *
     * @param mixed $default
     * @return mixed
     */
    public function last($default = null) {
        $found = false;
        $last = null;
        foreach ($this as $value) {
            $found = true;
            $last = $value;
        }

        return $found ? $last : (is_callable($default) ? $default() : $default);
    }

    /**
     * Determine whether every item passes the optional predicate.
     *
     * @return bool
     */
    public function every(?callable $callback = null) {
        foreach ($this as $key => $value) {
            if ($callback === null ? !$value : !$callback($value, $key)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Determine whether the collection contains a value or matching item.
     *
     * With two arguments, checks an item's property/array key against a value.
     *
     * @return bool
     */
    public function contains($value, $expected = null) {
        if (func_num_args() > 1) {
            foreach ($this as $item) {
                if ($this->valueFor($item, $value) === $expected) {
                    return true;
                }
            }
            return false;
        }

        if (is_callable($value)) {
            foreach ($this as $key => $item) {
                if ($value($item, $key)) {
                    return true;
                }
            }
            return false;
        }

        foreach ($this as $item) {
            if ($item === $value) {
                return true;
            }
        }
        return false;
    }

    /**
     * Reduce items to a single value.
     *
     * @return mixed
     */
    public function reduce(callable $callback, $initial = null) {
        $result = $initial;
        foreach ($this as $key => $value) {
            $result = $callback($result, $value, $key);
        }
        return $result;
    }

    /**
     * Sum items, optionally extracting a value from each item.
     *
     * @param string|callable|null $key
     * @return mixed
     */
    public function sum($key = null) {
        return $this->reduce(function ($sum, $item) use ($key) {
            return $sum + $this->valueFor($item, $key);
        }, 0);
    }

    /**
     * Average item values, optionally extracting a value from each item.
     *
     * @param string|callable|null $key
     * @return float|int|null
     */
    public function avg($key = null) {
        $sum = 0;
        $count = 0;
        foreach ($this as $item) {
            $sum += $this->valueFor($item, $key);
            $count++;
        }
        return $count ? $sum / $count : null;
    }

    /**
     * Alias for avg().
     *
     * @return float|int|null
     */
    public function average($key = null) {
        return $this->avg($key);
    }

    /**
     * Return the minimum extracted value.
     *
     * @return mixed
     */
    public function min($key = null) {
        $found = false;
        $minimum = null;
        foreach ($this as $item) {
            $value = $this->valueFor($item, $key);
            if (!$found || $value < $minimum) {
                $minimum = $value;
                $found = true;
            }
        }
        return $found ? $minimum : null;
    }

    /**
     * Return the maximum extracted value.
     *
     * @return mixed
     */
    public function max($key = null) {
        $found = false;
        $maximum = null;
        foreach ($this as $item) {
            $value = $this->valueFor($item, $key);
            if (!$found || $value > $maximum) {
                $maximum = $value;
                $found = true;
            }
        }
        return $found ? $maximum : null;
    }

    /**
     * Extract values by key and optionally key the result by another key.
     *
     * @return static
     */
    public function pluck($key, $indexKey = null) {
        $source = $this;
        return new static(function () use ($source, $key, $indexKey) {
            foreach ($source as $itemKey => $item) {
                $value = $this->valueFor($item, $key);
                if ($indexKey === null) {
                    yield $itemKey => $value;
                } else {
                    yield $this->valueFor($item, $indexKey) => $value;
                }
            }
        });
    }

    /**
     * Yield fixed-size chunks as lazy collections.
     *
     * @param int $size
     * @return static
     */
    public function chunk($size) {
        $size = (int) $size;
        if ($size < 1) {
            throw new \InvalidArgumentException('The chunk size must be greater than zero.');
        }

        $source = $this;
        return new static(function () use ($source, $size) {
            $items = [];
            foreach ($source as $key => $value) {
                $items[$key] = $value;
                if (count($items) === $size) {
                    yield new static($items, $this->modelClass);
                    $items = [];
                }
            }
            if ($items) {
                yield new static($items, $this->modelClass);
            }
        });
    }

    /**
     * Invoke a callback with this collection, then return it unchanged.
     *
     * @return $this
     */
    public function tap(callable $callback) {
        $callback($this);
        return $this;
    }

    /**
     * Pass this collection to a callback and return its result.
     *
     * @return mixed
     */
    public function pipe(callable $callback) {
        return $callback($this);
    }

    /**
     * Conditionally apply a callback and return its result.
     *
     * @param mixed $value
     * @param callable $callback
     * @param callable|null $default
     * @return mixed
     */
    public function when($value, callable $callback, ?callable $default = null) {
        if ($value) {
            return $callback($this, $value) ?? $this;
        }
        return $default ? ($default($this, $value) ?? $this) : $this;
    }

    /**
     * Apply a callback unless the condition is truthy.
     *
     * @param mixed $value
     * @param callable $callback
     * @param callable|null $default
     * @return mixed
     */
    public function unless($value, callable $callback, ?callable $default = null) {
        return $this->when(!$value, $callback, $default);
    }

    /**
     * Materialize items and convert models to arrays.
     *
     * @return array
     */
    public function toArray() {
        $result = [];
        foreach ($this as $key => $item) {
            $result[$key] = is_object($item) && method_exists($item, 'toArray')
                ? $item->toArray()
                : (is_array($item) ? $item : (array) $item);
        }
        return $result;
    }

    /**
     * Encode the materialized collection as JSON.
     *
     * @param int $options
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
     * Return the JSON representation.
     *
     * @return string
     */
    public function __toString() {
        return $this->toJson();
    }

    /**
     * Check whether the source has no items.
     *
     * @return bool
     */
    public function isEmpty() {
        foreach ($this as $_) {
            return false;
        }
        return true;
    }

    /**
     * Check whether the source contains at least one item.
     *
     * @return bool
     */
    public function isNotEmpty() {
        return !$this->isEmpty();
    }

    /**
     * Count all items by traversing the source.
     *
     * @return int
     */
    public function count(): int {
        $count = 0;
        foreach ($this as $_) {
            $count++;
        }
        return $count;
    }

    /**
     * Resolve a value from arrays, objects, or a callable.
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

        $segments = is_string($key) && strpos($key, '.') !== false
            ? explode('.', $key)
            : [$key];
        $current = $item;
        foreach ($segments as $segment) {
            if (is_array($current)) {
                if (!array_key_exists($segment, $current)) {
                    return null;
                }
                $current = $current[$segment];
            } elseif (is_object($current)) {
                if (isset($current->{$segment}) || property_exists($current, $segment)) {
                    $current = $current->{$segment};
                } else {
                    return null;
                }
            } else {
                return null;
            }
        }
        return $current;
    }
}
