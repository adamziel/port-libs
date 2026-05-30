# ALTER TABLE ADD/DROP COLUMN Corpus Next6

## Behavior

- Added `SQLiteAlterTableColumnCorpus` for bounded upstream-style schema rewrite planning.
- Covers `ALTER TABLE ... ADD COLUMN` acceptance for nullable/default/generated virtual/reference columns and rejections for duplicate, `PRIMARY KEY`, `UNIQUE`, `NOT NULL` without default, non-constant timestamp defaults, stored generated columns, wrong target table, and malformed SQL.
- Covers `ALTER TABLE ... DROP COLUMN` rewrites for middle/quoted/case-insensitive columns and rejections for missing columns, primary/unique columns, table `CHECK`/`UNIQUE` references, and dependent index/view/trigger schema SQL.

## Evidence

- `php -l lanes/libsqlite/src/SQLiteAlterTableColumnCorpus.php`
- `php -l lanes/libsqlite/tests/SQLiteAlterTableAddDropColumnCorpusTest.php`
- `php -l lanes/libsqlite/examples/application-alter-table-add-drop-column-corpus.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteAlterTableAddDropColumnCorpusTest.php`
  - `Focused test run: 1 selected test files (root lock skipped)`
  - `1 test files, 66 assertions, 0 failures`
  - PASS-line delta: `+66`
- `php lanes/libsqlite/examples/application-alter-table-add-drop-column-corpus.php`
- `git diff --check -- lanes/libsqlite`

## Status Delta

- `lane-status.json` `phpPass`: `2017 -> 2083`.
- `benchmarkDenominator.mapped`: unchanged; this is a focused PHP corpus slice, not a newly mapped upstream inventory unit.
- Root harness: not run - isolated micro-slice.

## Non-Overlap

Avoids accepted ALTER rename/drop-column handoffs by focusing on schema rewrite and rejection corpus for ADD COLUMN plus DROP COLUMN dependency/table-constraint guards in a new focused test file. It does not repeat accepted JSON, WAL, B-tree, VFS, GROUP BY, SELECT SQL, Unicode GLOB, rollback-commit, or batch5a corpus clusters.

## Dependency Closure

No new support component is needed. The slice reuses lane-local `SQLiteSchemaRecord` and PHP string parsing only.
