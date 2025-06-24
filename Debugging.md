# Debugging the WPORM QueryBuilder

This document provides tips and built-in features to help you debug queries and ORM behavior in the WPORM system.

## 1. Viewing the Generated SQL

- **Use the `toSql()` method** to return the raw SQL (with placeholders):
  ```php
  $sql = Model::query()->where('id', 1)->toSql();
  echo $sql;
  ```
- **Check the last executed query** using WordPress's `$wpdb`:
  ```php
  global $wpdb;
  echo $wpdb->last_query;
  ```

## 2. Viewing Query Bindings

- **Expose bindings** with a `getBindings()` method:
  ```php
  $bindings = Model::query()->where('id', 1)->getBindings();
  print_r($bindings);
  ```

## 3. Dumping SQL and Bindings

- **Use the `dumpSql()` method** to print both SQL and bindings for quick inspection:
  ```php
  Model::query()->where('id', 1)->dumpSql();
  ```

## 4. Logging Queries

- **Log queries** using `error_log()` for later inspection:
  ```php
  error_log($sql);
  error_log(print_r($bindings, true));
  ```

## 5. Using the `$debug` Property

- **Enable debug mode** on any query to automatically log SQL and bindings before execution:
  ```php
  $query = Model::query()->where('id', 1);
  $query->debug = true; // or $query->setDebug(true);
  $results = $query->get();
  ```
- When `$debug` is enabled, SQL and bindings are logged for `get()`, `first()`, `count()`, and `delete()` methods.

## 6. General Tips

- Use `var_dump()` or `print_r()` on model/query objects to inspect their state.
- Use breakpoints or Xdebug for step-by-step debugging if available.
- **Install the [Query Monitor](https://wordpress.org/plugins/query-monitor/) plugin** for WordPress. It provides a powerful interface to inspect all database queries, hooks, HTTP requests, and more, directly from the WordPress admin bar. This is highly recommended for advanced debugging and performance analysis.

## Note on Casting and toArray()

- The `toArray()` method now uses proper casting for both built-in types and custom cast classes.
- Built-in types (e.g. 'int', 'bool', etc.) will not be instantiated as classes, preventing errors like 'Class int not found'.
- Custom cast classes must implement `MJ\WPORM\Casts\CastableInterface`.

---

**Note:**
- These debugging features are safe and do not affect production unless used.
- Remove or disable debug output in production environments for best performance and security.

---

For more advanced debugging, consider integrating with logging libraries or using Xdebug for PHP.
