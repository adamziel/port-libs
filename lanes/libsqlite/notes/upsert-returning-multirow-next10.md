# UPSERT RETURNING Multi-Row Next10

## Behavior

- Added statement-order `returning_rows` to `SQLiteUpsertDoUpdateWherePlan::execute()` for rows changed by multi-row `INSERT ... ON CONFLICT DO UPDATE`.
- RETURNING rows include both inserted rows and updated rows in incoming statement order.
- Skipped `DO UPDATE WHERE` conflicts are omitted from RETURNING rows while remaining visible in `skipped_rows`.
- Repeated incoming conflicts see the current row produced by the prior incoming row in the same statement.
- `NULL` unique-key incoming rows insert independently and are returned independently, matching SQLite unique-index NULL conflict behavior.
- Added `SQLiteUpsertDoUpdateWherePlan::returningRows()` projection support for full rows, explicit columns, aliases, callables, wildcard expansion, and malformed projection validation.

## Focused Evidence

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpsertReturningMultirowCorpusTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 56 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpsertDoUpdateWhereCorpusTest.php lanes/libsqlite/tests/SQLiteUpsertReturningMultirowCorpusTest.php
Focused test run: 2 selected test files (root lock skipped)
2 test files, 120 assertions, 0 failures
```

```text
php lanes/libsqlite/examples/application-upsert-returning-multirow.php --self-test
```

The example self-test exited successfully.

## Dashboard Delta

- `phpPass`: `3236 -> 3292` from the 56 verified new PASS lines in `SQLiteUpsertReturningMultirowCorpusTest.php`.
- `benchmarkDenominator.mapped`: unchanged; this is focused PHP behavior coverage, not a newly mapped upstream inventory unit.

## Non-Overlap

This slice builds on the accepted UPSERT `DO UPDATE WHERE` corpus without repeating it. It does not touch accepted UPDATE/DELETE RETURNING, grouped SELECT, JSON table, VFS/WAL, B-tree, Unicode GLOB, rollback-journal, or suite-runner evidence clusters.

## Dependency Closure

No new support component is needed. The slice reuses the existing bounded row-array UPSERT executor and RETURNING projection pattern.
