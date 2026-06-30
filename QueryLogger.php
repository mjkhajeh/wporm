<?php

namespace MJ\WPORM;

/**
 * Centralized query logger for debugging and profiling.
 *
 * Records all executed queries with their SQL, bindings, duration,
 * and connection info. Supports listeners for real-time query monitoring.
 *
 * Usage:
 *   // Enable logging
 *   DB::enableQueryLog();
 *
 *   // Run queries...
 *   User::where('active', true)->get();
 *
 *   // Get logged queries
 *   $queries = DB::getQueryLog();
 *
 *   // Register a listener for real-time monitoring
 *   DB::listen(function($query, $bindings, $time) {
 *       error_log("Query: {$query} ({$time}ms)");
 *   });
 *
 *   // Clear the log
 *   DB::flushQueryLog();
 */
class QueryLogger
{
    /**
     * Whether query logging is enabled.
     *
     * @var bool
     */
    protected static $logging = false;

    /**
     * Stored query log entries.
     *
     * @var array<int, array{
     *     query: string,
     *     bindings: array,
     *     time: float,
     *     connection: string
     * }>
     */
    protected static $log = [];

    /**
     * Registered query listeners.
     *
     * @var array<int, callable>
     */
    protected static $listeners = [];

    /**
     * Enable query logging.
     *
     * @return void
     */
    public static function enableQueryLog(): void
    {
        static::$logging = true;
    }

    /**
     * Disable query logging.
     *
     * @return void
     */
    public static function disableQueryLog(): void
    {
        static::$logging = false;
    }

    /**
     * Check if query logging is enabled.
     *
     * @return bool
     */
    public static function isQueryLogging(): bool
    {
        return static::$logging;
    }

    /**
     * Register a listener that fires for every executed query.
     *
     * The callback receives three arguments:
     *   - string $sql      The executed SQL (with placeholders)
     *   - array  $bindings The bound parameter values
     *   - float  $time     Execution time in milliseconds
     *
     * Usage:
     *   DB::listen(function($sql, $bindings, $time) {
     *       error_log("[Query] {$time}ms: {$sql}");
     *   });
     *
     * @param callable $callback
     * @return void
     */
    public static function listen(callable $callback): void
    {
        static::$listeners[] = $callback;
    }

    /**
     * Get all registered listeners.
     *
     * @return array<int, callable>
     */
    public static function getListeners(): array
    {
        return static::$listeners;
    }

    /**
     * Remove all registered listeners.
     *
     * @return void
     */
    public static function flushListeners(): void
    {
        static::$listeners = [];
    }

    /**
     * Log a query execution.
     *
     * @param string $sql        The executed SQL
     * @param array  $bindings   The bound parameter values
     * @param float  $time       Execution time in milliseconds
     * @param string $connection Connection name (default: 'default')
     * @return void
     */
    public static function log(string $sql, array $bindings, float $time, string $connection = 'default'): void
    {
        $entry = [
            'query'      => $sql,
            'bindings'   => $bindings,
            'time'       => $time,
            'connection' => $connection,
        ];

        if (static::$logging) {
            static::$log[] = $entry;
        }

        // Notify all listeners (regardless of logging flag)
        foreach (static::$listeners as $listener) {
            $listener($sql, $bindings, $time);
        }
    }

    /**
     * Get all logged queries.
     *
     * @return array<int, array{
     *     query: string,
     *     bindings: array,
     *     time: float,
     *     connection: string
     * }>
     */
    public static function getQueryLog(): array
    {
        return static::$log;
    }

    /**
     * Get the total number of logged queries.
     *
     * @return int
     */
    public static function count(): int
    {
        return count(static::$log);
    }

    /**
     * Get the total execution time of all logged queries.
     *
     * @return float  Time in milliseconds
     */
    public static function totalTime(): float
    {
        return array_sum(array_column(static::$log, 'time'));
    }

    /**
     * Clear the query log.
     *
     * @return void
     */
    public static function flushQueryLog(): void
    {
        static::$log = [];
    }
}
