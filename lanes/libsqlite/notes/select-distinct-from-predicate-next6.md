# SELECT DISTINCT-FROM Predicate next6

## Behavior

- Adds null-safe `IS DISTINCT FROM` and `IS NOT DISTINCT FROM` dispatch to `SQLiteSelectPredicate`.
- Wires parser-level SELECT SQL text predicates for the same operators before the shorter `IS` / `IS NOT` operators are considered.
- Covers scalar, NULL, BLOB, storage-class, row-value, joined-row, grouped-row, parameter, and scalar-subquery predicate inputs.

## Focused Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteSelectDistinctFromPredicateTest.php` passed: `1 test files, 48 assertions, 0 failures` with 48 PASS lines.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php lanes/libsqlite/tests/SQLiteSelectDistinctFromPredicateTest.php` passed: `2 test files, 9794 assertions, 0 failures`.
- `php lanes/libsqlite/examples/application-select-sql-distinct-from.php` passed and reported drifted copied `wp_options` rows `home` and `_site_transient_update_plugins`.
- `php -l lanes/libsqlite/src/SQLiteSelectPredicate.php`, `php -l lanes/libsqlite/src/SQLiteSelectSql.php`, `php -l lanes/libsqlite/tests/SQLiteSelectDistinctFromPredicateTest.php`, and `php -l lanes/libsqlite/examples/application-select-sql-distinct-from.php` passed.
- `jq empty lanes/libsqlite/lane-status.json lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json` passed.
- `git diff --check -- lanes/libsqlite` passed.

Expected dashboard movement: `phpPass` increases by the verified +48 focused PASS lines from `2017` to `2065`; mapped upstream denominator is unchanged.

## Non-Overlap

This slice avoids accepted SELECT subqueries, expression `ORDER BY`, grouped SELECT SQL text, JSON table source/cursor/constraint work, Unicode GLOB ranges, rollback/VFS writer clusters, B-tree overflow/root-collapse/page-move clusters, and release-runner evidence. It targets the generic SELECT predicate null-safe comparison operator family.

## Dependency Closure

No new support component is needed. The patch reuses the existing native PHP SELECT predicate, expression, and SQL text executor paths.
