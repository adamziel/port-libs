# real-upstream-corpus-upsert-returning-dynamic-20260531T014452Z

Base accepted HEAD: `d0e37b664c0ef9500748faeafd4d7f1484470255`

Added `SQLiteRealUpstreamUpsertReturningInsertSelectYieldDynamicTest.php`, a
lane-local real upstream UPSERT/RETURNING corpus batch.

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert2.test`
  `upsert2-200`, `upsert2-201`, and `upsert2-210`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test`
  changed-row RETURNING stream behavior

Behavior covered:

- repeated `INSERT ... SELECT`-style source rows update the current row image
  seen by later source rows in the same statement;
- `ON CONFLICT DO UPDATE ... WHERE` false rows are skipped and produce no
  RETURNING row;
- RETURNING row order follows changed source-row ordinals only;
- yield-trace ordinals preserve before-insert, update-returning,
  insert-returning, and where-false boundaries.

Focused evidence:

`php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningInsertSelectYieldDynamicTest.php`

Result: `1 test files, 8002 assertions, 0 failures`, with `1002` focused PASS
lines.

Non-overlap:

This slice does not repeat the accepted UPSERT/RETURNING composite-tail,
catch-all, broad matrix, target-first, partial-index, or trigger/fkey batches.
It ports the `upsert2.test` repeated source-row current-image path with
RETURNING yield suppression at a 1000-case dynamic scale.

Dependency closure:

No new support component is needed. The test reuses the existing
`SQLiteUpsertDoUpdateWherePlan::executeConflictArmsWithYieldTrace()` native PHP
executor.
