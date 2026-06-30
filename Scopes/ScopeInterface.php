<?php

namespace MJ\WPORM\Scopes;

use MJ\WPORM\QueryBuilder;
use MJ\WPORM\Model;

/**
 * Interface for reusable query scope classes.
 *
 * Scope classes encapsulate query constraints that can be applied to any
 * model's query builder. Unlike scope*() methods (which live on the model),
 * scope classes are standalone, testable, and can be shared across models.
 *
 * Usage:
 *   // Define a scope class
 *   class ActiveScope implements ScopeInterface {
 *       public function apply(QueryBuilder $query, Model $model): void {
 *           $query->where('active', true);
 *       }
 *   }
 *
 *   // Register it globally
 *   User::addGlobalScope('active', new ActiveScope());
 *
 *   // Or use it ad-hoc
 *   User::query()->withoutGlobalScopes()->applyScope(new ActiveScope())->get();
 */
interface ScopeInterface
{
    /**
     * Apply the scope to the query builder.
     *
     * @param QueryBuilder $query  The query builder to constrain
     * @param Model        $model  The model instance (for context like table name)
     * @return void
     */
    public function apply(QueryBuilder $query, Model $model): void;
}
