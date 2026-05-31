# real-upstream-corpus-upsert-returning-dynamic-20260531T061115Z-0

## Upstream source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test`
  - `returning1-21.0`: `INSERT INTO sqlite_schema DEFAULT VALUES RETURNING sqlite_schema.name` yields one row with a null `name`.
  - `returning1-21.1`: `INSERT INTO sqlite_temp_schema DEFAULT VALUES RETURNING sqlite_temp_schema.name` yields one row with a null `name`.
  - `returning1-22.1`: a `RETURNING` subquery that aliases a user table as `sqlite_master` reports `no such column: sqlite_master.name` before yielding any rows.

## Ported behavior

- Added `SQLiteReturningSchemaNamePlan` as a generic bounded model for writable schema/temp-schema `RETURNING` name binding and subquery name-resolution error ordering.
- Added `SQLiteReturningSchemaNameRealCorpusTest.php` with 150 seeded variants over the real upstream section. The test contributes 900 focused TestRunner PASS cases and 1,950 assertions.

## Verification

- `php -l lanes/libsqlite/src/SQLiteReturningSchemaNamePlan.php`
- `php -l lanes/libsqlite/tests/SQLiteReturningSchemaNameRealCorpusTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteReturningSchemaNameRealCorpusTest.php`
  - Result: `1 test files, 1950 assertions, 0 failures`
  - PASS lines: 900

## Non-overlap

This does not repeat accepted UPSERT arm priority, `upsert4` conflict-target admission, `upsert5` matrix behavior, `returning1-17` duplicate UPSERT streams, `returning1-18/19` trigger DDL behavior, `returning1-20` correlated DELETE subqueries, JSON/app-WAL/rowvalue conflict slices, or trigger/FK RETURNING helpers. This slice owns the remaining writable schema/temp-schema `RETURNING` name-binding behavior from `returning1.test` sections 21 and 22.

## Dependency closure

No new support component is needed. The slice reuses lane-local PHP data-shape/name-resolution helpers and real upstream `.test` source truth.
