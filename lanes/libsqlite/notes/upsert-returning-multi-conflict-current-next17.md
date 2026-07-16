# UPSERT RETURNING Multi Conflict Current Next17

Slice: `yield-sqlite-upsert-returning-multi-conflict-current-next17`

Behavior added:

- Adds bounded multiple `ON CONFLICT` arm execution to `SQLiteUpsertDoUpdateWherePlan`.
- Preserves SQLite first-matching-arm behavior across named conflict targets.
- Supports `DO UPDATE`, `DO NOTHING`, and a catch-all conflict arm.
- Keeps `RETURNING` rows limited to inserts and updates; skipped `DO NOTHING` and false-`WHERE` rows are omitted.
- Checks secondary/current UNIQUE conflicts after updates and before inserts, while preserving SQLite NULL non-conflicts.
- Tracks matched arm targets/actions for Application import diagnostics.

Focused verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpsertReturningMultiConflictCurrentNext17Test.php
Focused test run: 1 selected test files (root lock skipped)
44 PASS lines
1 test files, 44 assertions, 0 failures

php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpsertReturningMultirowCorpusTest.php lanes/libsqlite/tests/SQLiteUpsertReturningConflictCurrentTest.php lanes/libsqlite/tests/SQLiteUpsertReturningMultiConflictCurrentNext17Test.php
Focused test run: 3 selected test files (root lock skipped)
147 PASS lines
3 test files, 147 assertions, 0 failures

php lanes/libsqlite/examples/application-upsert-returning-multi-conflict-current.php --self-test
exit 0
```

Status delta:

- `phpPass`: `5718 -> 5762` (`+44` verified PASS lines from the new focused test file).
- `benchmarkDenominator.mapped`: unchanged at `456`; this is focused PHP behavior coverage and does not admit a new upstream inventory unit.

Non-overlap:

This slice builds on but does not repeat accepted single-target UPSERT `DO UPDATE WHERE`, multi-row UPSERT RETURNING, and next15 current secondary-unique conflict checks. It targets ordered multiple conflict arms and catch-all behavior. It also avoids accepted INSERT SELECT conflict handling, INSERT OR REPLACE delete-before-insert planning, UPDATE FROM current conflict behavior, trigger conflict inheritance, and the recent SELECT/JSON/VFS/WAL/B-tree clusters.

Dependency closure:

No new support component is needed. The slice reuses the existing bounded row-array UPSERT executor and adds native PHP conflict-arm routing inside that helper.
