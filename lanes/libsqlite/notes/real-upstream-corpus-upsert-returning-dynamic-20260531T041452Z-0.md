# real-upstream-corpus-upsert-returning-dynamic-20260531T041452Z-0

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert1.test`
  - `upsert1-500`: UPSERT `DO UPDATE SET y=max(t1.y,excluded.y) AND true`
    preserves SQLite expression assignment truthiness and final row image.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test`
  - RETURNING rows are emitted in statement order from the post-change row
    image for both inserted and updated rows.

## Handoff

Added `SQLiteRealUpstreamUpsertReturningExpressionAssignmentDynamicTest.php`.
The test ports 250 deterministic mixed insert/update statements over generic
`app_values` rows. Each statement compares the native
`SQLiteUpsertDoUpdateWherePlan::executeConflictArmsWithYieldTrace()` result
against an in-memory SQLite oracle for final rows, RETURNING rows, and
`changes()`, then checks the native yield trace preserves one pre-insert yield
per source row and returns changed rows in statement order.

Focused movement:

- `1002` distinct TestRunner PASS cases.
- `1252` focused assertions.
- No production source change was needed.

## Non-Overlap

This slice does not repeat accepted `upsert4` omitted-target `DO NOTHING`,
`upsert5` multi-arm priority/catch-all, target-first unique conflict,
`upsert2` WHERE-gated repeated-source, redundant-conflict, trigger-old-value,
row-value/savepoint, or fault-injection batches. It owns the upstream
`upsert1-500` UPSERT expression-assignment behavior through RETURNING/yield
row images.

## Dependency Closure

No new support component is needed. The focused test reuses the native UPSERT
conflict-arm/yield executor and uses PDO SQLite only as a local oracle.

## Verification

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningExpressionAssignmentDynamicTest.php`
  - no syntax errors
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningExpressionAssignmentDynamicTest.php`
  - `1 test files, 1252 assertions, 0 failures`
