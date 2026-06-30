<?php

namespace MJ\WPORM;

/**
 * Trait for pruning old model records.
 *
 * Add this trait to a model and define a prunable() method that returns
 * a query builder constraining which records should be removed. The
 * prune() static method will delete matching records one at a time,
 * firing model events for each deletion.
 *
 * Usage:
 *   class AuditLog extends Model {
 *       use Prunable;
 *
 *       public function prunable() {
 *           return static::query()->where('created_at', '<', now()->subDays(90));
 *       }
 *   }
 *
 *   // Run the pruning
 *   AuditLog::prune();
 *
 * @see \MJ\WPORM\MassPrunable for chunk-based bulk pruning
 */
trait Prunable
{
    /**
     * Get the query builder that defines which records to prune.
     *
     * Must return a QueryBuilder instance. The trait will call delete()
     * on the result, processing one record at a time with model events.
     *
     * @return \MJ\WPORM\QueryBuilder
     */
    abstract public function prunable();

    /**
     * Prune old records matching the prunable() query.
     *
     * Processes records one at a time, firing model events (deleting/deleted)
     * for each. Returns the total number of records pruned.
     *
     * @return int  Number of records pruned
     */
    public static function prune(): int
    {
        $instance = new static;
        $query = $instance->prunable();

        if (!($query instanceof \MJ\WPORM\QueryBuilder)) {
            return 0;
        }

        $count = 0;
        foreach ($query->cursor() as $model) {
            if ($model->delete() !== false) {
                $count++;
            }
        }

        return $count;
    }
}
