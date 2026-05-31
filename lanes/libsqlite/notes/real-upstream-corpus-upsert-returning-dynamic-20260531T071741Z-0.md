# real-upstream-corpus-upsert-returning-dynamic-20260531T071741Z-0

## Upstream source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert5.test`
  - `upsert5-1.410` through `upsert5-1.413`: catch-all `ON CONFLICT DO UPDATE` arms update the first conflicting row.
  - `upsert5-1.420` through `upsert5-1.423`: targeted `DO NOTHING` arms suppress later catch-all `DO UPDATE` and emit no RETURNING row.
  - `upsert5-1.500` through `upsert5-1.505`: targeted `DO UPDATE` arms beat a final catch-all `DO NOTHING` arm and emit RETURNING rows.

## Implementation

- Added `SQLiteRealUpstreamUpsertReturningCatchallDynamicTest.php`.
- The batch adds 1,002 focused TestRunner PASS cases and 8,002 behavior assertions over generic application rows.
- It reuses `SQLiteUpsertDoUpdateWherePlan::executeConflictArmsWithYieldTrace()`; no new source component was needed.

## Non-overlap

This slice does not repeat accepted `upsert5` first-arm priority matrices, `upsert4` replace-precedence rows, `upsert1-1100/1200/1300`, alias/excluded behavior, expression assignments, SELECT-source UPSERT RETURNING, correlated RETURNING subqueries, or trigger/FK RETURNING cases. It owns the later `upsert5` catch-all/final-arm interaction with RETURNING stream presence and yield-trace ordering.

## Verification

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningCatchallDynamicTest.php` passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningCatchallDynamicTest.php` passed: `1 test files, 8002 assertions, 0 failures`.

## Dependency closure

No new support component is needed. The focused batch reuses the existing native PHP generalized UPSERT conflict-arm executor with yield tracing.
