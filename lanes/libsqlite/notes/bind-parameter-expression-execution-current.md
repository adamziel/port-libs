# SQLite Bind Parameter Expression Execution

Date: 2026-05-27
Base accepted HEAD: 21cf5c25cafc6db7dd2282a9e0135304340fe25b

## Behavior

This slice adds bounded SELECT SQL bind parameter execution at the parser boundary for:

- anonymous positional parameters (`?`);
- numbered positional parameters (`?NNN`);
- named parameters (`:name`, `@name`, `$name`);
- scalar, NULL, boolean, numeric, text, and `SQLiteBlobValue` values;
- quoted SQL text that must not bind placeholder-looking tokens.

The focused assertions exercise parameters in projection expressions, predicates, BETWEEN bounds, ORDER/LIMIT inputs, scalar subqueries, JSON table arguments, and malformed bind guards.

## Verification

- `php -l lanes/libsqlite/src/SQLiteSelectSql.php`
- `php -l lanes/libsqlite/tests/SQLiteHeaderTest.php`
- `php -l lanes/libsqlite/examples/application-select-sql-bind-parameters.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php`
- `php lanes/libsqlite/examples/application-select-sql-bind-parameters.php`
- `git diff --check -- lanes/libsqlite`

Focused `SQLiteHeaderTest.php` moved from the current lane-status baseline of 9476 assertions to 9516 assertions.

## Non-Overlap

This does not repeat accepted SELECT SQL text dispatch, JOIN text, subqueries, GROUP BY/HAVING, expression ORDER BY, JSON table sources/cursor/hidden or visible constraints, VFS sync/write/lock/rollback, WAL byte truncation, B-tree page move/root collapse/overflow release, Unicode GLOB, or INSERT OR REPLACE conflict planning. It only adds parameter binding before the existing bounded SELECT SQL executor parses and executes expressions.

## Dependency Closure

No new support component is needed. The binder reuses existing lane-local SQL literal parsing, `SQLiteBlobValue`, SELECT expression evaluation, JSON table execution, and Application copied-row fixtures.
