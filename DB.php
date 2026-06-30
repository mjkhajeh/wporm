<?php
namespace MJ\WPORM;

class DB {
    /**
     * Start a query on a table (returns a QueryBuilder for a raw table, not a model).
     * Usage: DB::table('posts')->where('id', 1)->get();
     *
     * @param string $table
     * @return QueryBuilder
     */
    /**
     * Start a query on a table or a subquery (derived table).
     *
     * Plain table name:
     *   DB::table('posts')->where('id', 1)->get();
     *
     * Derived table (subquery as FROM), Eloquent-style:
     *   DB::table(function($q) {
     *       $q->from('orders')->select(['user_id', 'SUM(total) as total'])->groupBy('user_id');
     *   }, 'order_totals')->where('total', '>', 100)->get();
     *
     *   // or with an existing QueryBuilder:
     *   $sub = Order::query()->select(['user_id', 'SUM(total) as total'])->groupBy('user_id');
     *   DB::table($sub, 'order_totals')->where('total', '>', 100)->get();
     *
     * @param string|\Closure|\MJ\WPORM\QueryBuilder $table
     * @param string|null $alias  Required when $table is a Closure or QueryBuilder
     * @return QueryBuilder
     */
    public static function table($table, ?string $alias = null) {
        $model = new class {
            public $table;
            public $primaryKey = 'id';
            public $casts = [];
            public $timestamps = false;
            public $softDeletes = false;
            public $softDeleteType = 'timestamp';
            public $deletedAtColumn = 'deleted_at';
            public $fillable = [];
            public $createdAtColumn = 'created_at';
            public $updatedAtColumn = 'updated_at';

            public function getTable() { return $this->table; }
        };

        if ($table instanceof \Closure || $table instanceof QueryBuilder) {
            if ($alias === null) {
                throw new \InvalidArgumentException(
                    'DB::table() requires a string $alias as second argument when a Closure or QueryBuilder is passed as $table.'
                );
            }
            $model->table = $alias;
            $qb = new QueryBuilder($model);
            $qb->fromSub($table, $alias);
            return $qb;
        }

        $model->table = $table;
        return new QueryBuilder($model);
    }

    /**
     * Execute a Closure within a database transaction (Eloquent-style).
     *
     * Automatically commits when the callback returns without throwing, and
     * rolls back + re-throws on any exception. The optional $attempts
     * parameter retries the whole callback up to that many times when a
     * deadlock or lock-wait-timeout is detected (MySQL error codes 1213 /
     * 1205), mirroring Laravel's DB::transaction() retry behaviour.
     *
     * The return value of the callback is forwarded to the caller:
     *
     *   $user = DB::transaction(function() {
     *       $u = User::create(['name' => 'Alice']);
     *       Profile::create(['user_id' => $u->id]);
     *       return $u;
     *   });
     *
     *   // With deadlock-retry (attempt up to 3 times before giving up):
     *   DB::transaction(function() { ... }, 3);
     *
     * @param \Closure $callback Receives no arguments.
     * @param int $attempts Maximum number of attempts before propagating the exception (default 1).
     * @return mixed Whatever the callback returns.
     * @throws \Throwable Re-throws the last exception after all attempts are exhausted.
     */
    public static function transaction(\Closure $callback, int $attempts = 1) {
        global $wpdb;
        return QueryBuilder::runTransaction($wpdb, $callback, $attempts);
    }

    // ── Query Logging ────────────────────────────────────────────────────────

    /**
     * Enable query logging.
     *
     * @return void
     */
    public static function enableQueryLog(): void
    {
        QueryLogger::enableQueryLog();
    }

    /**
     * Disable query logging.
     *
     * @return void
     */
    public static function disableQueryLog(): void
    {
        QueryLogger::disableQueryLog();
    }

    /**
     * Check if query logging is enabled.
     *
     * @return bool
     */
    public static function isQueryLogging(): bool
    {
        return QueryLogger::isQueryLogging();
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
        QueryLogger::listen($callback);
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
        return QueryLogger::getQueryLog();
    }

    /**
     * Get the total number of logged queries.
     *
     * @return int
     */
    public static function queryCount(): int
    {
        return QueryLogger::count();
    }

    /**
     * Get the total execution time of all logged queries.
     *
     * @return float  Time in milliseconds
     */
    public static function queryTime(): float
    {
        return QueryLogger::totalTime();
    }

    /**
     * Clear the query log.
     *
     * @return void
     */
    public static function flushQueryLog(): void
    {
        QueryLogger::flushQueryLog();
    }
}
