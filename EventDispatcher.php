<?php
namespace MJ\WPORM;

/**
 * Simple, zero-dependency event dispatcher for WPORM model lifecycle events.
 *
 * Supports two binding styles, matching Eloquent's $dispatchesEvents interface:
 *
 *   1. Class-based listeners (maps event class → listener class/callable):
 *      $dispatchesEvents = ['creating' => \App\Listeners\LogCreating::class];
 *      The listener class must implement handle(\MJ\WPORM\Events\ModelEvent $event).
 *
 *   2. Wildcard (global) listeners registered via EventDispatcher::listen():
 *      EventDispatcher::listen(\MJ\WPORM\Events\Creating::class, function($e) { ... });
 *      EventDispatcher::listen(\MJ\WPORM\Events\Creating::class, [MyListener::class, 'handle']);
 *
 * The dispatcher is a static singleton — no DI container required.
 * Laravel/Illuminate is NOT a dependency; this is a standalone implementation.
 */
class EventDispatcher
{
    /**
     * Global listeners: eventClass => [callable, ...]
     *
     * @var array<string, callable[]>
     */
    protected static $listeners = [];

    /**
     * Cache of instantiated listener objects, keyed by class name.
     *
     * @var array<string, object>
     */
    protected static $instances = [];

    /**
     * Register a global listener for an event class.
     *
     * @param string          $eventClass  Fully-qualified event class name.
     * @param callable|string $listener    Callable, or a class-string whose
     *                                     static handle() will be invoked.
     * @return void
     */
    public static function listen(string $eventClass, $listener): void
    {
        static::$listeners[$eventClass][] = $listener;
    }

    /**
     * Return all registered listeners for a given event class, or empty array.
     *
     * @param string $eventClass
     * @return callable[]
     */
    public static function getListeners(string $eventClass): array
    {
        return static::$listeners[$eventClass] ?? [];
    }

    /**
     * Remove all global listeners for a given event class (or all events).
     *
     * @param string|null $eventClass  Null to forget everything.
     * @return void
     */
    public static function forget(?string $eventClass = null): void
    {
        if ($eventClass === null) {
            static::$listeners = [];
            static::$instances = [];
        } else {
            unset(static::$listeners[$eventClass]);
        }
    }

    /**
     * Dispatch an event object.
     *
     * Invokes, in order:
     *   1. The model's $dispatchesEvents mapping (if the model carries one).
     *   2. All globally-registered listeners for this event class.
     *
     * If any listener returns exactly `false`, dispatching stops immediately
     * and `false` is returned (mirrors Eloquent's "halt on false" behavior,
     * used for the before-hooks: creating, updating, saving, deleting, etc.).
     *
     * @param \MJ\WPORM\Events\ModelEvent $event
     * @return mixed  `false` if a listener halted the event, the event otherwise.
     */
    public static function dispatch($event)
    {
        $eventClass = get_class($event);
        $model      = $event->model;

        // ── 1. $dispatchesEvents class mapping ────────────────────────────────
        if (property_exists($model, 'dispatchesEvents')) {
            $map = $model->dispatchesEvents ?? [];
            // Map key: the short event name, e.g. 'creating', 'saved', …
            $shortName = static::shortName($eventClass);
            if (!empty($map[$shortName])) {
                $listenerClass = $map[$shortName];
                $result = static::callListener($listenerClass, $event);
                if ($result === false) {
                    return false;
                }
            }
        }

        // ── 2. Globally-registered listeners ─────────────────────────────────
        foreach (static::$listeners[$eventClass] ?? [] as $listener) {
            $result = static::callListener($listener, $event);
            if ($result === false) {
                return false;
            }
        }

        return $event;
    }

    /**
     * Invoke a listener. Accepts:
     *   - A callable (closure, function name, [object, method], [class, method]).
     *   - A class-string: instantiated with new $class() and handle() called.
     *
     * @param callable|string                   $listener
     * @param \MJ\WPORM\Events\ModelEvent       $event
     * @return mixed
     */
    protected static function callListener($listener, $event)
    {
        if (is_callable($listener)) {
            return $listener($event);
        }

        if (is_string($listener) && class_exists($listener)) {
            if (!isset(static::$instances[$listener])) {
                static::$instances[$listener] = new $listener();
            }
            $instance = static::$instances[$listener];
            if (method_exists($instance, 'handle')) {
                return $instance->handle($event);
            }
        }

        return null;
    }

    /**
     * Convert a fully-qualified event class name to the lowercase short name
     * used as keys in $dispatchesEvents, e.g.:
     *   MJ\WPORM\Events\Creating  →  'creating'
     *   App\Events\OrderShipped   →  'ordershipped'   (user-defined)
     *
     * @param string $eventClass
     * @return string
     */
    protected static function shortName(string $eventClass): string
    {
        $parts = explode('\\', $eventClass);
        return strtolower(end($parts));
    }
}
