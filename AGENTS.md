# Repository Guidelines

WPORM (`mjkhajeh/wporm`) is a lightweight, Eloquent-inspired ORM for WordPress plugins and themes. It is a PHP >= 7.4 library that targets the WordPress `$wpdb` API; there is no build step — contributions are PHP source plus Markdown documentation.

## Project Structure & Module Organization

Source lives at the repository root under the `MJ\WPORM\` namespace (PSR-4 maps it to `""`):

- **Core:** `Model.php` (abstract base models extend this), `QueryBuilder.php`, `Collection.php`, `DB.php`, `SchemaBuilder.php`, `Blueprint.php`, `ColumnDefinition.php`, `Helpers.php`.
- **Events:** `EventDispatcher.php` plus `ModelEvent.php` and `Events.php` under `MJ\WPORM\Events` (lifecycle events: `creating`, `saved`, `deleting`, etc.).
- **Exceptions:** `ModelNotFoundException.php`.
- **`Casts/`** — attribute casters (`CastableInterface`, `JsonCast`).
- **`Example/`** — runnable usage samples; the best reference when adding features.
- **Docs:** `Readme.md`, `Methods.md`, `Blueprint.md`, `CastsType.md`, `DB.md`, `Debugging.md`; `todo` tracks pending work.
- `vendor/` is Composer-managed and gitignored.

## Build, Test, and Development Commands

```bash
composer install        # install dependencies
composer dump-autoload  # regenerate the PSR-4/classmap autoloader after adding classes
```

There is no test suite yet. Validate changes by running a script under `Example/` against a live WordPress `$wpdb` instance.

## Coding Style & Naming Conventions

- PHP 7.4 compatible; PSR-4 autoloading; one class per file.
- `PascalCase` classes and filenames; `camelCase` methods and properties.
- Place new classes in the matching namespace (`MJ\WPORM`, `MJ\WPORM\Casts`, `MJ\WPORM\Events`).
- Add concise `/** */` docblocks on public methods — see `Model.php` for the house style.
- No automated linter is configured; match the surrounding style by hand.

## Testing Guidelines

No formal tests or coverage targets exist today. When adding a feature, add a focused `Example/` script demonstrating it and confirm it runs against `wpdb`. Keep examples isolated from production code paths.

## Commit & Pull Request Guidelines

- Commits use imperative mood with a capitalized verb prefix, matching the existing history: `Add`, `Refactor`, `Fix`, `Enhance`, `Implement`, `Update`.
- Examples: `Add withCount() method for eager loading relationship counts`, `Fix exists() method to restore original limit on QueryBuilder`.
- Name the affected class or method in the message; split unrelated changes into separate commits.
- Open PRs against `main` with a short description of what changed and why, and link any related issue.
