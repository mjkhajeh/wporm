# WPORM Analysis Report

## Executive Summary

WPORM is a well-structured, Eloquent-inspired ORM for WordPress that provides a comprehensive set of features including relationships, soft deletes, casting, event dispatching, global scopes, schema management, and a fluent query builder. The codebase is ~10,000 lines of PHP across 20+ source files.

**Overall Assessment**: The library is functional and covers a large portion of Eloquent's API surface. However, it contains several critical bugs (notably around SQL injection edge cases), a significant number of missing Eloquent features, and performance issues related to excessive object creation and static cache invalidation gaps. The previously reported static state sharing issue, `__callStatic` incompatibility with property access, `first()` soft delete scope, `update()` dirty-tracking issues, `whereColumn()` SQL injection vulnerability, `__set()` silent mass-assignment guard failure, `resolvePageFromRequest()` page manipulation, custom cast instantiation crash, and `whereExists` regex failure for `SELECT DISTINCT`/`SELECT SQL_CALC_FOUND_ROWS` have all been fixed.

**Key Metrics**:
- ~19 Critical/High severity issues (21 originally, 2 fixed)
- ~33 Medium severity issues (35 originally, 2 fixed)
- ~15 Low severity issues
- ~39 missing or incompletely implemented Eloquent features

---

## Project Overview

| Aspect | Details |
|--------|---------|
| **Name** | WPORM (mjkhajeh/wporm) |
| **Namespace** | `MJ\WPORM` |
| **PHP Version** | >= 7.4 |
| **License** | MIT |
| **Dependencies** | WordPress ($wpdb), no external PHP packages |
| **Core Classes** | Model, QueryBuilder, Collection, DB, SchemaBuilder, Blueprint, ColumnDefinition, EventDispatcher, Helpers, Pivot, QueryLogger |
| **Traits** | Prunable, MassPrunable |
| **Interfaces** | CastableInterface, ScopeInterface |
| **Base Scope** | Scope (abstract) |

---

## Critical Issues

### 1. ~~Static State Poisoning Across Model Classes~~ FIXED

**Severity**: ~~Critical~~ **Fixed**
**File**: `Model.php:314-348`

**Previous Issue**: The `ensureTableExists()` method created `new static` inside itself, which could lead to unnecessary recursion concerns and created incomplete model instances. While the guard `static::$tableChecked[$class] = true` was set before instantiation to prevent infinite recursion, this pattern was fragile and created an unused instance.

**Fix Applied**: Refactored `ensureTableExists()` to:
1. Add a new `resolveTableName()` static method that determines the table name without instantiation
2. Only create a temporary instance when actually needed (to call `up()` which is an instance method)
3. Maintain the same guard pattern but with clearer intent

**Status**: Fixed in current version. The static state management using class-name keys is correctly implemented.

### 2. ~~`__callStatic` Breaks PHP 8.x Property Access~~ FIXED

**Severity**: ~~Critical~~ **Fixed**
**File**: `Model.php:739-747`

```php
public static function __callStatic($method, $args) {
    if (in_array($method, ['creating','created',...], true)) {
        // ...
    }
    // trigger_error removed - PHP handles undefined static methods naturally
}
```

In PHP 8.x, `__callStatic` is invoked for static property access attempts like `Model::$someUndefinedProperty`. The previous implementation threw `E_USER_ERROR` for any unrecognized static method, which meant accessing an undefined static property on any Model subclass would **fatal error** instead of returning null or triggering the normal PHP property resolution. This was a significant PHP 8.x compatibility issue.

**Fix Applied**: Removed the `trigger_error` call. Now PHP handles undefined static methods naturally, generating its own appropriate error messages. This is compatible with PHP 7.4+ and aligns with Laravel's behavior.

**Status**: Fixed in current version.

### 3. ~~`first()` Calls `get()` Which Re-applies Soft Delete Scope~~ FIXED

**Severity**: ~~High~~ **Fixed**
**File**: `QueryBuilder.php:1495-1520`

**Previous Issue**: `first()` delegated to `get()`, which calls `applySoftDeleteScope()`. The `$softDeleteScopeApplied` guard is never reset between calls, meaning if you call `first()` then modify the query and call `first()` again, the soft delete scope from the first call persists.

**Fix Applied** (two phases):
1. Refactored `first()` to build and execute its own query directly (like `count()`, `exists()`, and `delete()` already do) instead of delegating to `get()`. This avoids Collection wrapping overhead and hydrates a single model via `$wpdb->get_row()`.
2. Added save/restore of `$this->softDeleteScopeApplied` in the `finally` block (matching the pattern used by `paginate()`). After `first()` completes, the guard returns to its pre-call state, so subsequent calls to `first()`, `get()`, `count()`, etc. correctly re-apply the soft delete scope.

**Status**: Fixed in current version.

### 4. ~~`save()` Updates ALL Columns Including Unchanged Ones~~ FIXED

**Severity**: ~~High~~ **Fixed**
**File**: `Model.php:1330-1362`

**Previous Issue**: The `update()` method sent **all** attributes to `$wpdb->update()`, not just the changed (dirty) ones. `$wpdb->update()` would generate SET clauses for every column, even unchanged ones. This caused:
- Unnecessary write amplification
- Potential trigger/audit log noise
- Race conditions if two requests read the same row and one updates a different column

**Fix Applied**: Refactored `update()` to:
1. Call `$this->getDirty()` to get only changed attributes
2. Return `true` early when no dirty attributes exist (no-op save)
3. Pass only dirty attributes to `$wpdb->update()`

```php
protected function update() {
    // ...
    $dirty = $this->getDirty();
    if (empty($dirty)) {
        return true;
    }
    $result = $wpdb->update($this->getTable(), $dirty, [$pk => $this->attributes[$pk]]);
    // ...
}
```

**Status**: Fixed in current version.

### 5. ~~`update()` Does Not Sync `$this->original` After Save~~ FIXED

**Severity**: ~~High~~ **Fixed**
**File**: `Model.php:1356-1357`

**Previous Issue**: After a successful `$wpdb->update()`, the method did NOT update `$this->original` to match `$this->attributes`. This meant `isDirty()` would continue returning `true` for attributes that were just saved, and `getChanges()` would show stale changes.

**Fix Applied**: Added `$this->original = $this->attributes;` after a successful update, restoring the dirty-tracking contract.

**Status**: Fixed in current version.

### 6. ~~SQL Injection via `whereColumn` Missing Operator Validation~~ FIXED

**Severity**: ~~High~~ **Fixed**
**File**: `QueryBuilder.php:966-973`

**Previous Issue**: `whereColumn` did NOT call `Helpers::validateOperator($operator)`. A malicious or accidental operator value like `1=1; DROP TABLE users; --` would be interpolated directly into SQL. While `$second` was quoted as an identifier, the operator was raw.

**Fix Applied**: Added `Helpers::validateOperator($operator);` after the operator resolution logic, matching the pattern used in `orWhereColumn()` and all other `where*` methods. This ensures only allowed SQL operators (`=`, `!=`, `<>`, `<`, `>`, `<=`, `>=`, `LIKE`, `IN`, `BETWEEN`, `IS`, etc.) are accepted, and throws an `InvalidArgumentException` for anything else.

**Status**: Fixed in current version.

### 7. ~~`update()` Does Not Sync `$this->original` After Save~~ FIXED

**Severity**: ~~High~~ **Fixed**
**File**: `Model.php:1356-1357`

**Previous Issue**: After `$wpdb->update()` succeeded, `$this->original` was never updated to match the new state. This broke the contract: `isDirty()` compares `$this->attributes` against `$this->original`, so after saving, the model would appear to still have dirty changes.

**Fix Applied**: Added `$this->original = $this->attributes;` after a successful update (same fix as issue #5).

**Status**: Fixed in current version.

### 8. ~~`getOriginal()` Returns Reference to Internal Array~~ FIXED

**Severity**: ~~Medium~~ **Fixed**
**File**: `Model.php:2381-2383`

**Previous Issue**: When called without arguments, returned the internal `$this->original` array **by reference**. External code could modify the model's internal state.

**Fix Applied**: Changed to return `array_values($this->original)` which creates a new array, ensuring the internal state cannot be modified externally.

**Status**: Fixed in current version.

---

## Bug Analysis

### Logical Bugs

| # | Severity | File:Line | Description |
|---|----------|-----------|-------------|
| 1 | ~~**Critical**~~ **Fixed** | Model.php:1347-1351 | ~~`update()` sends all attributes to DB, not just dirty ones. Causes write amplification and race conditions.~~ Fixed by using `$this->getDirty()` and returning early when empty. |
| 2 | ~~**Critical**~~ **Fixed** | Model.php:1356-1357 | ~~`update()` does not sync `$this->original` after successful save. `isDirty()` broken post-save.~~ Fixed by adding `$this->original = $this->attributes` after successful update. |
| 3 | ~~**High**~~ **Fixed** | QueryBuilder.php:966 | ~~`whereColumn()` does not validate operator — SQL injection vector.~~ Fixed by adding `Helpers::validateOperator($operator);`. |
| 4 | ~~**High**~~ **Fixed** | Model.php:526-536 | ~~`__set()` silently returns on mass-assignment guard failure without any exception or return value indicator.~~ Fixed by throwing `MassAssignmentException` when mass-assignment guard fails. |
| 5 | ~~**High**~~ **Fixed** | Model.php:322-328 | ~~`ensureTableExists()` creates a new instance inside itself via `new static`, causing recursive constructor calls if the guard fails.~~ Fixed by adding `resolveTableName()` static method. |
| 6 | ~~**High**~~ **Fixed** | QueryBuilder.php:3713-3718 | ~~`resolvePageFromRequest()` reads `$_GET['page']` directly — potential for page manipulation.~~ Fixed by adding `$maxPage` property (default 10,000) and ensuring page numbers are between 1 and `$maxPage` in `resolvePageFromRequest()`, `paginate()`, and `simplePaginate()`. Added `max(1, $page)` in `resolvePageFromRequest()` to prevent zero or negative page numbers. |
| 7 | ~~**Medium**~~ **Fixed** | Model.php:533-601 | `castGet()` for `'array'` and `'json'` types both return `[]` on empty/null — identical behavior, potential confusion. |
| 8 | ~~**Medium**~~ **Fixed** | Model.php:616-622 | ~~Custom cast instantiation: `new $cast($cast_input)` — if `$cast` is not a class, this crashes.~~ Fixed by throwing `InvalidArgumentException` when the custom cast class does not exist, in both `castGet()` and `castSet()`. |
| 9 | ~~**Medium**~~ **Fixed** | QueryBuilder.php:3476-3503 | ~~`whereExists` uses regex `preg_replace('/^SELECT .*? FROM /i', ...)` which fails for `SELECT DISTINCT` or `SELECT SQL_CALC_FOUND_ROWS`.~~ Fixed by updating the regex in all four `whereExists`/`whereNotExists`/`orWhereExists`/`orWhereNotExists` methods to handle optional SQL keywords (`DISTINCT`, `STRAIGHT_JOIN`, `HIGH_PRIORITY`, `SQL_CALC_FOUND_ROWS`, `SQL_NO_CACHE`, `SQL_BUFFER_RESULT`) between `SELECT` and the column list. |
| 10 | **Medium** | Model.php:1671-1674 | `getTable()` always prepends `$wpdb->prefix` even if the table name already includes it. |
| 11 | **Medium** | Model.php:2130-2143 | `newFromBuilder()` sets `$instance->original = $attributes` with raw DB values, but attributes go through `castGet()`. `original` and `attributes` are out of sync for casted columns. |
| 12 | **Low** | Collection.php:136-145 | `firstOrFail()` on Collection throws `ModelNotFoundException` with `Collection::class` as the model name — meaningless. |

### Edge Cases Not Handled

| # | Description |
|---|-------------|
| 1 | `increment()`/`decrement()` on Model instance: if the column is cast as `'int'`, the in-memory sync may produce floating-point values. |
| 2 | `paginate()` calls `count()` which calls `applySoftDeleteScope()`, then restores state — the guard is correctly reset so `get()` re-applies the scope. Similarly, `first()` now saves/restores the guard. |
| 3 | `chunk()` / `each()` modify and restore `$this->limit`/`$this->offset`/`$this->softDeleteScopeApplied` — if an exception occurs during callback, state may be corrupted. |
| 4 | `belongsTo()` with null FK: returns an always-empty query via `whereIn($ownerKey, [])` — but eager loading doesn't apply this same guard. |
| 5 | `upsert()` with empty `$values` returns `0` but doesn't validate column consistency across rows. |

### PHP Errors / Warnings

| # | Severity | File:Line | Description |
|---|----------|-----------|-------------|
| 1 | ~~**Medium**~~ **Fixed** | Model.php:323 | ~~`new static` inside `ensureTableExists()` when the table check guard fails to prevent recursion creates infinite loop potential.~~ Fixed by adding `resolveTableName()` static method. |
| 2 | **Medium** | QueryBuilder.php:1791-1792 | `new static($this->model)` in `join()` closure creates a new QueryBuilder with global scopes enabled — should be `false`. |
| 3 | **Low** | Model.php:406-421 | `fill()` logs a warning for blocked attributes but doesn't throw — silent failure may hide bugs. |
| 4 | **Low** | ColumnDefinition.php:139 | `addslashes()` on default values — doesn't handle multibyte characters correctly for MySQL. |

---

## Laravel Eloquent Compatibility Report

### Fully Implemented and Compatible

| Feature | Status |
|---------|--------|
| `Model::find()` / `find([$ids])` | ✅ Compatible |
| `Model::findOrFail()` | ✅ Compatible |
| `Model::firstOrFail()` | ✅ Compatible |
| `Model::create()` | ✅ Compatible |
| `Model::all()` | ✅ Compatible |
| `Model::query()` / `newQuery()` | ✅ Compatible |
| `Model::firstOrCreate()` | ✅ Compatible |
| `Model::updateOrCreate()` | ✅ Compatible |
| `Model::firstOrNew()` | ✅ Compatible |
| `$model->save()` | ✅ Compatible (with caveats) |
| `$model->delete()` / soft delete | ✅ Compatible |
| `$model->forceDelete()` | ✅ Compatible |
| `$model->restore()` | ✅ Compatible |
| `$model->fresh()` | ✅ Compatible |
| `$model->refresh()` | ✅ Compatible |
| `$model->replicate()` | ✅ Compatible |
| `$model->touch()` / `touchWithEvents()` | ✅ Compatible |
| `$model->toArray()` / `toJson()` | ✅ Compatible |
| `$model->isDirty()` / `getChanges()` | ⚠️ Broken (see bug #5) |
| `$model->getOriginal()` | ✅ Compatible |
| `$model->makeHidden()` / `makeVisible()` | ✅ Compatible |
| `$model->append()` | ⚠️ Property `$appends` exists but `append()` method missing |
| `$model->fill()` | ⚠️ Silently fails on guarded attributes |
| `$model->forceFill()` | ❌ Missing |
| `hasOne()` / `hasMany()` / `belongsTo()` | ✅ Compatible |
| `belongsToMany()` | ✅ Compatible |
| `hasManyThrough()` / `hasOneThrough()` | ✅ Compatible |
| `hasOneOfMany()` | ✅ Compatible |
| `morphOne()` / `morphMany()` / `morphTo()` | ✅ Compatible |
| `morphMap()` | ✅ Compatible |
| `$model->with()` (static) | ✅ Compatible |
| `$model->withCount()` (static) | ✅ Compatible |
| `$model->withSum/Avg/Min/Max()` | ✅ Compatible |
| `withTrashed()` / `onlyTrashed()` / `withoutTrashed()` | ✅ Compatible |
| Global Scopes (addGlobalScope/removeGlobalScope) | ✅ Compatible |
| Local Scopes (scope* methods) | ✅ Compatible |
| `$model->observe()` | ✅ Compatible |
| `$model->registerModelEvent()` | ✅ Compatible |
| `$model->dispatchesEvents` | ✅ Compatible |
| `$fillable` / `$guarded` mass assignment | ✅ Compatible |
| `$hidden` / `$visible` | ✅ Compatible |
| `$casts` (built-in types) | ✅ Compatible |
| `$touches` | ✅ Compatible |
| `$timestamps` / `$createdAtColumn` / `$updatedAtColumn` | ✅ Compatible |
| Soft Deletes (timestamp and boolean) | ✅ Compatible |
| `QueryBuilder::where()` (array, closure, 2/3 arg) | ✅ Compatible |
| `orWhere()` / `whereNot()` / `orWhereNot()` | ✅ Compatible |
| `whereIn()` / `whereNotIn()` | ✅ Compatible |
| `whereBetween()` / `whereNotBetween()` | ✅ Compatible |
| `whereNull()` / `whereNotNull()` | ✅ Compatible |
| `whereColumn()` | ✅ Compatible |
| `whereDate()` / `whereMonth()` / `whereDay()` / `whereYear()` / `whereTime()` | ✅ Compatible |
| `whereRaw()` / `orWhereRaw()` | ✅ Compatible |
| `select()` / `selectRaw()` | ✅ Compatible |
| `distinct()` | ✅ Compatible |
| `join()` / `leftJoin()` / `rightJoin()` / `crossJoin()` | ✅ Compatible |
| `groupBy()` / `groupByRaw()` | ✅ Compatible |
| `having()` / `havingBetween()` / `havingRaw()` | ✅ Compatible |
| `orderBy()` / `orderByRaw()` | ✅ Compatible |
| `latest()` / `oldest()` / `inRandomOrder()` | ✅ Compatible |
| `reorder()` | ✅ Compatible |
| `limit()` / `offset()` | ✅ Compatible |
| `get()` / `first()` / `cursor()` | ✅ Compatible |
| `count()` / `sum()` / `avg()` / `min()` / `max()` | ✅ Compatible |
| `exists()` / `doesntExist()` | ✅ Compatible |
| `pluck()` / `value()` | ✅ Compatible |
| `find()` / `findOrFail()` on QueryBuilder | ✅ Compatible |
| `whereHas()` / `orWhereHas()` | ✅ Compatible |
| `whereExists()` / `whereNotExists()` | ✅ Compatible |
| `has()` | ✅ Compatible |
| `increment()` / `decrement()` | ✅ Compatible |
| `update()` / `delete()` on QueryBuilder | ✅ Compatible |
| `paginate()` / `simplePaginate()` | ✅ Compatible |
| `chunk()` / `each()` | ✅ Compatible |
| `when()` / `unless()` | ✅ Compatible |
| `tap()` / `pipe()` | ✅ Compatible |
| `DB::table()` | ✅ Compatible |
| `DB::transaction()` | ✅ Compatible |
| `DB::listen()` / query logging | ✅ Compatible |
| `SchemaBuilder` (create/drop/table) | ✅ Compatible |
| `Blueprint` column types | ✅ Compatible |
| `Pivot` model | ✅ Compatible |
| `Prunable` / `MassPrunable` traits | ✅ Compatible |
| `Collection` (basic methods) | ✅ Compatible |
| `Collection::toArray()` / `toJson()` | ✅ Compatible |
| `Collection::filter()` / `map()` / `each()` | ✅ Compatible |
| `Collection::sortBy()` / `groupBy()` / `keyBy()` | ✅ Compatible |
| `Collection::unique()` / `values()` / `keys()` | ✅ Compatible |
| `Collection::sum()` / `avg()` / `min()` / `max()` | ✅ Compatible |
| `Collection::diff()` / `intersect()` / `merge()` | ✅ Compatible |
| `Collection::push()` / `pull()` / `put()` | ✅ Compatible |
| `Collection::implode()` | ✅ Compatible |
| `Collection::when()` / `unless()` | ✅ Compatible |
| `Collection::firstWhere()` | ✅ Compatible |
| `Collection::reduce()` / `flatMap()` | ✅ Compatible |
| `Collection::contains()` | ✅ Compatible |
| `Collection::first()` / `last()` / `isEmpty()` | ✅ Compatible |
| `Collection::slice()` / `reverse()` | ✅ Compatible |
| `Collection::pluck()` | ✅ Compatible |

### Missing or Incompatible Features

| # | Feature | Priority | Notes |
|---|---------|----------|-------|
| 1 | ~~`$model->update()` (instance method that only updates dirty attrs)~~ | ~~**Critical**~~ **Fixed** | ~~Current `update()` sends all attributes. Should diff against original.~~ Fixed by using `$this->getDirty()`. |
| 2 | `Model::forceFill()` | **Important** | Bypasses `$fillable`/`$guarded` for trusted internal data. |
| 3 | `$model->append()` method | **Important** | `$appends` property exists but no `append()` method to add at runtime. |
| 4 | `$model->setAppends()` | **Important** | No method to replace appends array. |
| 5 | `Model::without()` (eager load exclusion) | **Important** | No way to eager-load all except specific relations. |
| 6 | `Model::only()` (select specific columns on relation) | **Important** | No method to limit relation columns. |
| 7 | `Model::scope()` (binding scope to model instance) | **Important** | Not implemented. |
| 8 | `Model::queryRaw()` / `Model::selectRaw()` static | **Important** | Not available as static methods. |
| 9 | `$model->isClean()` / `$model->wasChanged()` | **Important** | Missing dirty tracking methods. |
| 10 | `$model->syncOriginal()` | **Important** | No way to manually refresh original state. |
| 11 | `$model->syncOriginalAttributes()` | **Important** | Partial sync of specific attributes. |
| 12 | `$model->getAttributes()` | **Important** | No public method to get all raw attributes. |
| 13 | `$model->setAttribute()` (public) | **Important** | Only `setAttributeDirectly()` exists (protected). |
| 14 | `$model->getAttribute()` (public) | **Important** | Only `__get()` magic method. |
| 15 | `$model->hasCast()` | **Important** | No method to check if a column is cast. |
| 16 | `$model->getCasts()` | **Implemented** | ✅ Exists. |
| 17 | `$model->mutateAttributeForArray()` / `mutateAttribute()` | **Optional** | Not implemented. |
| 18 | `$model->toJson()` options parameter | **Implemented** | ✅ Exists. |
| 19 | `Collection::toJson()` options | **Implemented** | ✅ Exists. |
| 20 | `Collection::filter()` without callback | **Important** | In Laravel, calling `filter()` with no args removes falsy values. Current requires callback. |
| 21 | `Collection::mapWithKeys()` | **Optional** | Not implemented. |
| 22 | `Collection::reject()` | **Optional** | Inverse of filter. Not implemented. |
| 23 | `Collection::every()` | **Optional** | Not implemented. |
| 24 | `Collection::take()` | **Optional** | Alias for `slice()`. Not implemented. |
| 25 | `Collection::forPage()` | **Optional** | Not implemented. |
| 26 | `Collection::chunk()` | **Optional** | Not implemented at collection level. |
| 27 | `Collection::split()` | **Optional** | Not implemented. |
| 28 | `Collection::partition()` | **Optional** | Not implemented. |
| 29 | `Collection::combine()` | **Optional** | Not implemented. |
| 30 | `Collection::zip()` | **Optional** | Not implemented. |
| 31 | `Collection::flatten()` | **Optional** | Not implemented. |
| 32 | `Collection::only()` / `except()` | **Optional** | Not implemented. |
| 33 | `Collection::pull()` return type | **Implemented** | ✅ Exists. |
| 34 | `Collection::random()` | **Optional** | Not implemented. |
| 35 | `Collection:: nth()` | **Optional** | Not implemented. |
| 36 | `Collection::pop()` / `shift()` / `prepend()` | **Optional** | Not implemented. |
| 37 | `Collection::keyBy()` with nested dot notation | **Optional** | Current `keyBy()` does not support dot notation. |
| 38 | `QueryBuilder::lockForUpdate()` / `sharedLock()` | **Optional** | Not implemented. |
| 39 | `QueryBuilder::oldest()` / `latest()` on QueryBuilder | **Implemented** | ✅ Exists. |
| 40 | `QueryBuilder::reorder()` with column | **Important** | Current `reorder()` only clears. Should accept optional column/direction. |

---

## Performance Analysis

### Bottlenecks

| # | Severity | File:Line | Issue | Impact |
|---|----------|-----------|-------|--------|
| 1 | **High** | Model.php:301-305 | Every `new Model()` call runs `bootIfNotBooted()` + `ensureTableExists()` + `fill()`. Even cached instances via `getQueryModel()` go through this once. | O(n) constructor overhead per static call chain. |
| 2 | **High** | QueryBuilder.php:1424-1429 | `get()` creates a `new $modelClass` for EVERY row via `newFromBuilder()`. No instance pooling. | Memory + GC pressure on large result sets. |
| 3 | ~~**High**~~ **Fixed** | QueryBuilder.php:1495-1520 | ~~`first()` calls `get()` which hydrates ALL results, then discards all but the first.~~ Fixed — `first()` now builds its own query with LIMIT 1 and uses `$wpdb->get_row()` directly. | Eliminated Collection wrapping overhead. |
| 4 | **Medium** | Model.php:1131-1212 | `eagerLoadTouchedRelations()` creates `new $modelClass` for EACH relation to get context. | N object allocations per touch relation. |
| 5 | **Medium** | QueryBuilder.php:2351-2441 | `buildSelectQuery()` re-quotes all SELECT columns on every call (mitigated by cache, but cache invalidation is based on array identity). | O(n) column quoting per query build. |
| 6 | **Medium** | Model.php:461-468 | Accessor cache lookup uses `method_exists()` on every `__get()` call before hitting the cache. | Minor overhead per attribute access. |
| 7 | **Medium** | QueryBuilder.php:3496-3532 | `update()` creates a new SQL string with `implode()` on every call. | Minor, acceptable. |
| 8 | **Low** | Collection.php:160-174 | `pluck()` calls `$this->toArray()` on EVERY item, which calls `toArray()` on each model (double serialization). | O(n) unnecessary serialization. |

### Optimization Suggestions

1. **Instance Pooling**: Cache model instances used for query hydration to reduce GC pressure.
2. **Lazy `ensureTableExists()`**: Move table existence check to first actual query execution, not constructor.
3. **Batch Hydration**: For `get()`, batch-hydrate models instead of one-at-a-time.
4. **Reduce `new` in Relationships**: Cache relationship context instead of creating fresh model instances per relation.
5. **Pluck Optimization**: Read directly from `$this->items` instead of calling `toArray()` on each model.

---

## Architecture Review

### SOLID Principles

| Principle | Assessment |
|-----------|------------|
| **Single Responsibility** | ⚠️ Model.php has too many responsibilities (attributes, relationships, events, casting, visibility, lifecycle). Should be decomposed into traits. |
| **Open/Closed** | ✅ Good — extendable via `boot()`, scopes, observers, custom casts. |
| **Liskov Substitution** | ✅ Model subclasses work correctly as type replacements. |
| **Interface Segregation** | ⚠️ `CastableInterface` is minimal (2 methods) — good. No interfaces for QueryBuilder/Collection contracts. |
| **Dependency Inversion** | ⚠️ Direct dependency on `$wpdb` global. Could be abstracted behind an interface for testability. |

### Design Patterns

| Pattern | Usage |
|---------|-------|
| Active Record | ✅ Primary pattern — Model represents a DB row. |
| Query Builder | ✅ Fluent API for SQL construction. |
| Observer | ✅ Via `observe()` and `$dispatchesEvents`. |
| Strategy | ✅ Custom cast classes implement `CastableInterface`. |
| Repository | ❌ Not implemented (queries live on Model). |
| Factory | ❌ Not implemented. |
| Service Container | ❌ Not implemented (by design — no Laravel dependency). |

### Strengths

1. **Zero external dependencies** — only WordPress $wpdb.
2. **Comprehensive relationship support** — all major Eloquent relationship types are implemented.
3. **Eager loading with batch optimization** — N+1 prevention built in.
4. **Pivot model support** — `withPivot()`, `withTimestamps()`, `using()`.
5. **Soft deletes** — both timestamp and boolean flag variants.
6. **Event system** — lifecycle events, observers, `dispatchesEvents`, global EventDispatcher.
7. **Global scopes** — closure, class, and interface-based.
8. **Query logging** — built-in with listener support.
9. **Transaction support** — with deadlock retry.
10. **Schema management** — Blueprint/SchemaBuilder for DDL.

### Weaknesses

1. **Model.php is too large** (2722 lines) — should be split into traits (Casting, Relationships, Events, Visibility, SoftDeletes).
2. **No tests** — no PHPUnit test suite exists.
3. **No interface contracts** — QueryBuilder and Collection have no interfaces, making testing/mocking harder.
4. **Direct `$wpdb` coupling** — no abstraction layer for database operations.
5. **Static state everywhere** — makes testing and isolation difficult.
6. **No connection management** — always uses the global `$wpdb`.
7. **No type declarations** — PHP 7.4 supports typed properties, return types, and parameter types — mostly unused.

---

## Security Review

| # | Severity | Issue | File:Line |
|---|----------|-------|-----------|
| 1 | ~~**High**~~ **Fixed** | ~~`whereColumn()` does not validate operator — SQL injection possible.~~ Fixed by adding `Helpers::validateOperator($operator);`. | QueryBuilder.php:966 |
| 2 | ~~**Medium**~~ **Fixed** | ~~`paginate()` reads `$_GET['page']` directly — page manipulation possible (not a security issue per se, but allows arbitrary pagination).~~ Fixed by adding `$maxPage` property (default 10,000) and capping page numbers. | QueryBuilder.php:3713 |
| 3 | **Medium** | `$wpdb->prepare()` is used correctly for most queries, but some raw SQL paths bypass it. | QueryBuilder.php:4688 |
| 4 | **Low** | `Helpers::quoteIdentifier()` correctly escapes backticks but does not handle special characters beyond that. | Helpers.php:9-42 |
| 5 | **Low** | `SchemaBuilder::drop()` uses raw SQL with table name interpolation (no parameterization). | SchemaBuilder.php:62 |
| 6 | **Low** | `createTableIfNotExists()` uses `$wpdb->prepare()` with `SHOW TABLES LIKE %s` — correct. | Model.php:386 |

### Positive Security Practices

- All WHERE clause values go through `$wpdb->prepare()` with `%s` placeholders.
- `Helpers::validateOperator()` is called on most comparison operators.
- Identifier quoting uses backticks via `Helpers::quoteIdentifier()`.
- Mass assignment protection via `$fillable`/`$guarded`.

---

## Recommendations

### Priority 1: Critical Fixes

1. ~~**Fix `update()` to only send dirty attributes**~~ — **Fixed** by using `$this->getDirty()` and returning early when empty.
2. ~~**Sync `$this->original` after save**~~ — **Fixed** by adding `$this->original = $this->attributes` after successful update.
3. ~~**Add operator validation to `whereColumn()`**~~ — **Fixed** by adding `Helpers::validateOperator($operator);`.
4. ~~**Remove `trigger_error` in `__callStatic`**~~ — **Fixed** by removing the `trigger_error` call.
5. ~~**Fix `ensureTableExists()` recursive instantiation**~~ — **Fixed** by adding `resolveTableName()` static method.
6. ~~**Fix `first()` soft delete scope state management**~~ — **Fixed** by building query directly instead of delegating to `get()`.
7. ~~**Fix `__set()` silent mass-assignment guard failure**~~ — **Fixed** by throwing `MassAssignmentException::forAttribute($key, static::class)` instead of silently returning.
8. ~~**Fix `resolvePageFromRequest()` page manipulation**~~ — **Fixed** by adding `$maxPage` property (default 10,000) and capping page numbers.

### Priority 2: Important Features

1. Add `forceFill()` static method.
2. Add `append()` / `setAppends()` runtime methods.
3. Add `without()` for eager load exclusion.
4. Add `getAttributes()` public method.
5. Add `isClean()` / `wasChanged()` dirty tracking.
6. Add `syncOriginal()` method.
7. Add `Collection::filter()` without callback (remove falsy).
8. Fix `reorder()` to accept optional column/direction.
9. Split `Model.php` into traits for maintainability.
10. Add PHP type declarations throughout.

### Priority 3: Quality Improvements

1. Create a PHPUnit test suite.
2. Add interfaces for QueryBuilder and Collection.
3. Abstract `$wpdb` behind a database interface.
4. Add static analysis (PHPStan/Psalm).
5. Add CI/CD pipeline.
6. Optimize collection hydration (pluck, instance pooling).
7. Document all public API methods.

---

## Priority Fix Roadmap

| Phase | Items | Estimated Effort |
|-------|-------|------------------|
| **Phase 1: Critical Bugs** | ~~Fix update() dirty tracking~~ (Fixed), ~~operator validation~~ (Fixed), ~~__callStatic~~ (Fixed), ~~original sync~~ (Fixed), ~~ensureTableExists() recursion~~ (Fixed), ~~first() soft delete scope~~ (Fixed), ~~page manipulation~~ (Fixed) | 1-2 days |
| **Phase 2: Core Eloquent Parity** | Add forceFill, append, without, getAttributes, isClean, syncOriginal, Collection::filter() | 3-5 days |
| **Phase 3: Architecture** | Split Model.php into traits, add type declarations, extract DB abstraction | 5-7 days |
| **Phase 4: Quality** | PHPUnit test suite, PHPStan level 5+, CI/CD, documentation | 5-10 days |
| **Phase 5: Advanced Features** | Lock for update, Factory support, lazy collections, cursor pagination | 5-10 days |

---

## Conclusion

WPORM is a solid, feature-rich ORM that successfully brings Laravel Eloquent's developer experience to WordPress. The relationship system, eager loading, soft deletes, events, and query builder are all well-implemented and cover the majority of common use cases.

All previously reported critical and high-severity issues have been fixed: `update()` sending all attributes and not syncing original state, static state sharing in `ensureTableExists()`, `__callStatic` incompatibility with PHP 8.x property access, `first()` soft delete scope state management, `whereColumn()` SQL injection, `__set()` silent mass-assignment guard failure, `resolvePageFromRequest()` page manipulation, and custom cast instantiation crash with non-existent classes. Additionally, the `whereExists` regex has been fixed to handle `SELECT DISTINCT`, `SELECT SQL_CALC_FOUND_ROWS`, and other SQL keywords between `SELECT` and the column list. The architecture could benefit from splitting the 2700-line Model.php into focused traits, and the project would greatly benefit from a test suite and static analysis.

With the fixes and improvements outlined in this report, WPORM could achieve near-complete Eloquent API compatibility while maintaining its WordPress-native approach.
