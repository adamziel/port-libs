# SELECT SQL DISTINCT current slice

## Behavior

- Adds parser-level `SELECT DISTINCT` support to `SQLiteSelectSql`.
- DISTINCT is applied after projection, so aliases, scalar expressions, wildcard/table-star expansion, joins, JSON table-valued scans, CTEs, parameters, and scalar subqueries deduplicate on the final output row.
- `SELECT ALL` is accepted as the explicit duplicate-preserving form.

## Focused Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteSelectDistinctSqlTest.php` passed: `1 test files, 63 assertions, 0 failures` with 31 PASS lines.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php` passed: `1 test files, 9701 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php lanes/libsqlite/tests/SQLiteSelectDistinctSqlTest.php` passed: `2 test files, 9764 assertions, 0 failures`.
- `php lanes/libsqlite/examples/application-select-sql-distinct.php` passed and reported 4 deduplicated copied `wp_options` rows.
- `php -l lanes/libsqlite/src/SQLiteSelectSql.php`, `php -l lanes/libsqlite/src/SQLiteSelectQuery.php`, `php -l lanes/libsqlite/tests/SQLiteSelectDistinctSqlTest.php`, and `php -l lanes/libsqlite/examples/application-select-sql-distinct.php` passed.
- `git diff --check -- lanes/libsqlite` passed.

Expected dashboard movement: `phpPass` increases by the verified +31 focused PASS lines from `SQLiteSelectDistinctSqlTest.php`; mapped denominator is unchanged.

## Non-Overlap

This slice does not repeat accepted `SQLiteSelectResult` distinct/order/limit helpers, parser-level SELECT SQL JOIN text, GROUP BY/HAVING text, expression ORDER BY, correlated subqueries, JSON table source/cursor wiring, or the current UPDATE FROM conflict slice. It wires the existing result primitive into parser-level SELECT text and covers projected-row DISTINCT behavior.

## Dependency Closure

No new support component is needed. The implementation reuses `SQLiteSelectSql`, `SQLiteSelectQuery`, `SQLiteSelectProjection`, and `SQLiteSelectResult` in native PHP.
