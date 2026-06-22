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
    public static function table($table) {
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
}
