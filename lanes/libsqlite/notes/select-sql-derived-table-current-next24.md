# SELECT SQL Derived Table Current Next24

## Behavior

This batch adds bounded parser-level `FROM (SELECT ...) AS alias` and
`WITH ... SELECT` derived-table source support to `SQLiteSelectSql`. Derived
tables materialize through the existing SELECT executor, accept optional column
alias lists, and participate in joins, GROUP BY/HAVING, CTE bodies, scalar
subquery contexts, compound rows, ORDER BY, LIMIT/OFFSET, and Application import
staging summaries without ext/sqlite.

## Focused Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteSelectDerivedTableCurrentNext24Test.php`
  passed `1 test files, 47 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteSelectSqlDerivedTableCurrentNext24Test.php`
  passed `1 test files, 60 assertions, 0 failures`.
- `php lanes/libsqlite/examples/application-select-sql-derived-table.php`
  reported the ordered derived option rows `home:29`, `siteurl:24`, and
  `theme_mods:16`.
- `php lanes/libsqlite/examples/application-select-sql-derived-import.php --self-test`
  reported copied `wp_options` import rows staged through derived tables,
  including update/insert distinction and UNION ALL ordering behavior.

## Status Delta

The two focused derived-table test files add current-source PASS lines for the
batch24 integration. `benchmarkDenominator.mapped` is unchanged for this note;
these are focused native PHP SELECT executor surfaces rather than fresh
upstream inventory units.

## Non-Overlap

This avoids accepted single-table SELECT SQL text, JSON table cursor/constraint
behavior, rollback/VFS writer paths, B-tree page moves/root collapse, Unicode
GLOB, and batch21 Application import transaction planning. The new behavior is
specifically parenthesized derived SELECT sources in `FROM` and joined source
positions.

## Dependency Closure

No new support component is needed. The slice reuses existing
`SQLiteSelectSql`, `SQLiteSelectQuery`, projection, predicate, aggregate, CTE,
VALUES, compound, and join components.
