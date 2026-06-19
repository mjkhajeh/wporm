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
}
