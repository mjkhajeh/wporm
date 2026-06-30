<?php

namespace MJ\WPORM;

/**
 * Represents a pivot table record in a many-to-many relationship.
 *
 * Pivot models carry the pivot table's own columns (beyond the two foreign keys)
 * and can be customized per relationship. They are not backed by their own
 * database table — they exist only as part of a belongsToMany relationship.
 *
 * Usage:
 *   // Access pivot data on an eager-loaded relation
 *   foreach ($post->tags as $tag) {
 *       echo $tag->pivot->order; // pivot column 'order'
 *   }
 *
 *   // Define which pivot columns to load
 *   $post->tags()->withPivot('order', 'created_at')->get();
 *
 *   // Include timestamps automatically
 *   $post->tags()->withTimestamps()->get();
 *
 *   // Use a custom pivot class
 *   $post->tags()->using(TagPost::class)->get();
 */
class Pivot
{
    /**
     * The attributes loaded from the pivot table.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [];

    /**
     * The parent model that owns this pivot relationship.
     *
     * @var Model|null
     */
    protected $parent;

    /**
     * The related model this pivot connects to.
     *
     * @var Model|null
     */
    protected $related;

    /**
     * Create a new Pivot instance.
     *
     * @param Model|null $parent     The parent model
     * @param array      $attributes Pivot table attributes
     * @param Model|null $related    The related model (optional)
     */
    public function __construct(?Model $parent = null, array $attributes = [], ?Model $related = null)
    {
        $this->parent = $parent;
        $this->related = $related;
        $this->attributes = $attributes;
    }

    /**
     * Get a pivot attribute by name.
     *
     * @param string $key
     * @return mixed
     */
    public function __get(string $key)
    {
        return $this->attributes[$key] ?? null;
    }

    /**
     * Set a pivot attribute.
     *
     * @param string $key
     * @param mixed  $value
     * @return void
     */
    public function __set(string $key, $value): void
    {
        $this->attributes[$key] = $value;
    }

    /**
     * Check if a pivot attribute exists.
     *
     * @param string $key
     * @return bool
     */
    public function __isset(string $key): bool
    {
        return isset($this->attributes[$key]);
    }

    /**
     * Get all pivot attributes.
     *
     * @return array<string, mixed>
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * Set multiple pivot attributes.
     *
     * @param array<string, mixed> $attributes
     * @return $this
     */
    public function setAttributes(array $attributes): self
    {
        $this->attributes = array_merge($this->attributes, $attributes);
        return $this;
    }

    /**
     * Get the parent model.
     *
     * @return Model|null
     */
    public function getParent(): ?Model
    {
        return $this->parent;
    }

    /**
     * Get the related model.
     *
     * @return Model|null
     */
    public function getRelated(): ?Model
    {
        return $this->related;
    }

    /**
     * Convert the pivot to an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->attributes;
    }

    /**
     * Convert the pivot to JSON.
     *
     * @param int $options
     * @return string
     */
    public function toJson(int $options = 0): string
    {
        return json_encode($this->toArray(), $options);
    }
}
