# SELECT SQL VALUES Source Current Next16

## Behavior

This slice adds bounded parser-level `FROM (VALUES ...)` source support to
`SQLiteSelectSql`. The source materializes SQLite-style `column1`, `column2`,
... rows, accepts optional table aliases, participates in joins and comma
cross joins, and reuses the existing VALUES expression evaluator for scalar,
NULL, and BLOB values.

## Focused Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteSelectValuesSourceTest.php`
  - `Focused test run: 1 selected test files (root lock skipped)`
  - `PASS executes sqlite values source rows in select sql from clauses`
  - `PASS joins sqlite values source rows with copied application option rows`
  - `PASS handles sqlite values source expressions blobs nulls and malformed aliases`
  - `1 test files, 60 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-select-sql-values-source.php`
  - matched copied `wp_options` rows: `siteurl`, `home`

## Status Delta

- `phpPass`: `5433 -> 5436` from 3 newly verified PASS lines.
- `benchmarkDenominator.mapped`: unchanged; no new upstream inventory unit was
  admitted in this behavior slice.

## Dependency Closure

No new support component is needed. The slice reuses the existing
`SQLiteSelectSql`, `SQLiteSelectQuery`, `SQLiteSelectExpression`, and
`SQLiteSelectPredicate` row-array executor components.

## Non-Overlap

This avoids accepted SELECT JOIN/GROUP/ORDER/subquery text dispatch, JSON table
source/cursor/constraint work, WAL/VFS rollback/sync/savepoint/file-writer
clusters, B-tree page-move/root-collapse/overflow release clusters, and Unicode
GLOB behavior. The new behavior is specifically parenthesized `VALUES` as a
SELECT source.
