<?php

namespace MJ\WPORM;

/**
 * Trait for mass-pruning old model records using chunked deletion.
 *
 * Similar to Prunable, but processes records in chunks for better memory
 * efficiency on very large datasets. Uses direct SQL DELETE queries instead
 * of individual model deletions (model events are NOT fired).
 *
 * Usage:
 *   class AuditLog extends Model {
 *       use MassPrunable;
 *
 *       public function prunable() {
 *           return static::query()->where('created_at', '<', now()->subDays(90));
 *       }
 *   }
 *
 *   // Run the mass pruning
 *   AuditLog::prune();
 *
 * @see \MJ\WPORM\Prunable for per-record pruning with model events
 */
trait MassPrunable
{
    /**
     * Get the query builder that defines which records to prune.
     *
     * Must return a QueryBuilder instance. The trait will delete matching
     * records in chunks using direct SQL.
     *
     * @return \MJ\WPORM\QueryBuilder
     */
    abstract public function prunable();

    /**
     * Mass-prune old records matching the prunable() query.
     *
     * Processes records in chunks for memory efficiency. Uses direct SQL
     * DELETE queries (model events are NOT fired).
     *
     * @param int $chunkSize  Number of records to delete per chunk (default: 1000)
     * @return int  Total number of records pruned
     */
    public static function prune(int $chunkSize = 1000): int
    {
        $instance = new static;
        $query = $instance->prunable();

        if (!($query instanceof \MJ\WPORM\QueryBuilder)) {
            return 0;
        }

        $total = 0;
        $pk = $instance->getPrimaryKey();

        do {
            // Get a batch of IDs to delete
            $ids = $query->clone()
                ->select([$pk])
                ->limit($chunkSize)
                ->pluck($pk);

            if (empty($ids)) {
                break;
            }

            // Delete the batch using direct SQL
            $deleted = static::query()
                ->whereIn($pk, $ids)
                ->delete();

            $total += $deleted;

            // If we got fewer than chunkSize, we're done
            if ($deleted < $chunkSize) {
                break;
            }

        } while (true);

        return $total;
    }
}
