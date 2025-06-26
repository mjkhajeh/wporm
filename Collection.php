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

    public function toArray() {
        return array_map(function($item) {
            return method_exists($item, 'toArray') ? $item->toArray() : (array)$item;
        }, $this->items);
    }

    public function all() {
        return $this->items;
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
    public function offsetGet($offset): mixed {
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
        return reset($this->items) ?: null;
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
     * Determine if the collection contains a given value (strict).
     */
    public function contains($value) {
        return in_array($value, $this->items, true);
    }
}
