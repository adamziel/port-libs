# UPSERT DO UPDATE WHERE corpus next6

This slice adds a bounded native PHP row-array executor for SQLite
`INSERT ... ON CONFLICT(...) DO UPDATE SET ... WHERE ...` behavior and a
SQLite-oracle-backed corpus focused on the `DO UPDATE WHERE` gate.

Focused verification:

- `php -l lanes/libsqlite/src/SQLiteUpsertDoUpdateWherePlan.php`
- `php -l lanes/libsqlite/tests/SQLiteUpsertDoUpdateWhereCorpusTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpsertDoUpdateWhereCorpusTest.php`
  - `1 test files, 64 assertions, 0 failures`
  - 64 PASS lines
- `php lanes/libsqlite/examples/application-upsert-do-update-where.php`

Status delta:

- `phpPass`: `2017 -> 2081` from the 64 newly verified focused PASS lines.
- `benchmarkDenominator.mapped`: unchanged; this is focused native PHP
  executor/corpus coverage backed by a local `sqlite3` oracle, not a newly
  mapped upstream inventory unit.

Non-overlap:

- Avoids accepted INSERT SELECT conflict handling, UPDATE FROM duplicate-source
  behavior, insert-or-replace delete-before-insert planning, trigger conflict
  inheritance/recursion, SELECT SQL text execution, and recent WAL/VFS/B-tree
  clusters.
- Covers the narrower upstream DML behavior where an UPSERT conflict runs a
  conditional `DO UPDATE` against current and `excluded` values, including
  skipped updates, repeated input rows observing prior statement changes, NULL
  unique values inserting instead of conflicting, and update-result unique
  conflict rejection.

Dependency closure:

- No new support component is needed. The focused tests use local `sqlite3` as
  an oracle only; the implementation remains native PHP under `lanes/libsqlite`.

Next task:

- Wire this bounded UPSERT behavior into parser-level INSERT text execution
  once the lane owns broader DML statement parsing.
