<?php
namespace MJ\WPORM\Events;

/**
 * Base class for all WPORM model lifecycle events.
 *
 * Every dispatched event carries a reference to the model that triggered it.
 * Custom event classes extend this; event listeners can type-hint the specific
 * event class or this base class to receive any model event.
 *
 * Eloquent parity: mirrors Illuminate\Database\Eloquent\Events\* conventions.
 */
abstract class ModelEvent
{
    /**
     * The model that fired the event.
     *
     * @var \MJ\WPORM\Model
     */
    public $model;

    /**
     * @param \MJ\WPORM\Model $model
     */
    public function __construct($model)
    {
        $this->model = $model;
    }
}
