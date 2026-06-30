<?php

namespace MJ\WPORM\Scopes;

use MJ\WPORM\QueryBuilder;
use MJ\WPORM\Model;

/**
 * Abstract base class for query scopes.
 *
 * Provides a convenient base for defining reusable query scopes.
 * Extend this class and override the apply() method to add your constraints.
 *
 * Usage:
 *   class ActiveScope extends Scope {
 *       public function apply(QueryBuilder $query, Model $model): void {
 *           $query->where('active', true);
 *       }
 *   }
 *
 *   // Register globally
 *   User::addGlobalScope('active', new ActiveScope());
 *
 *   // Or apply ad-hoc
 *   User::query()->applyScope(new ActiveScope())->get();
 */
abstract class Scope implements ScopeInterface
{
    /**
     * Apply the scope to the query builder.
     *
     * @param QueryBuilder $query
     * @param Model $model
     * @return void
     */
    abstract public function apply(QueryBuilder $query, Model $model): void;
}
