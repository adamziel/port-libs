# Generated Column CHECK Constraint Corpus Next5

2026-05-27 isolated slice `yield-sqlite-generated-column-check-constraint-corpus-next5`.

## Scope

- Added focused schema parser coverage for generated columns declared with both shorthand `AS (...)` and verbose `GENERATED ALWAYS AS (...)`.
- Covered generated-column expressions and CHECK constraints containing `UNIQUE`, `CHECK`, and `PRIMARY KEY` text so autoindex discovery only counts real declared UNIQUE constraints.
- Added a Application-shaped schema smoke for `wp_options` generated metadata inspection without `ext/sqlite`.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteGeneratedColumnCheckConstraintCorpusTest.php`
  - `Focused test run: 1 selected test files (root lock skipped)`
  - `32` PASS lines, `1 test files, 32 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteGeneratedColumnCheckConstraintCorpusTest.php lanes/libsqlite/tests/SQLitePragmaSchemaCatalogTest.php lanes/libsqlite/tests/SQLiteSchemaPragmaDdlCorpusTest.php`
  - `Focused test run: 3 selected test files (root lock skipped)`
  - `3 test files, 146 assertions, 0 failures`
- `php -l lanes/libsqlite/src/SQLitePragmaSchemaCatalog.php`
  - `No syntax errors detected`
- `php -l lanes/libsqlite/tests/SQLiteGeneratedColumnCheckConstraintCorpusTest.php`
  - `No syntax errors detected`
- `php -l lanes/libsqlite/examples/application-generated-column-check-schema.php`
  - `No syntax errors detected`
- `php lanes/libsqlite/examples/application-generated-column-check-schema.php`
  - Reported visible `table_info` columns, `table_xinfo` hidden codes `[0,0,0,0,2,3]`, and `sqlite_autoindex` columns `["option_name"]`.
- `git diff --check -- lanes/libsqlite`
  - Passed with no output.

## Status Delta

- `phpPass`: `1684 -> 1716` (`+32` verified PASS lines).
- `benchmarkDenominator.mapped`: unchanged; this slice adds focused PHP corpus coverage without claiming a newly hydrated upstream inventory unit.

## Non-Overlap

This avoids accepted JSON table, WAL, B-tree, VFS, SELECT SQL, Unicode GLOB, and batch4 corpus clusters. It extends the older schema PRAGMA generated-column coverage only into shorthand generated declarations plus generated-column CHECK/UNIQUE keyword disambiguation.

## Dependency Closure

No new support component is needed. The slice reuses the existing lane-local schema catalog/create-table parsers.
