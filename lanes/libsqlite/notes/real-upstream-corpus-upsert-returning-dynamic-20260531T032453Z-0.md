# real-upstream-corpus-upsert-returning-dynamic-20260531T032453Z-0

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert1.test`
  - `upsert1-700` through `upsert1-780`: multiple uniqueness constraints must test the named UPSERT conflict target first, even when other unique constraints also conflict.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test`
  - RETURNING streams expose the post-change row image in statement order.

## Ported Behavior

- Added `SQLiteRealUpstreamUpsertReturningTargetFirstDynamicTest.php`.
- The file checks 1,080 dynamic rowid, ordinary-rowid, and WITHOUT ROWID variants derived from the upstream `upsert1-700` through `upsert1-780` target-first matrix.
- Each variant verifies selected conflict target, insert/update partitioning, one-row RETURNING yield, non-selected conflicting columns, stable RETURNING projection order, yield trace shape, and final-state parity.
- This reuses the existing native `SQLiteUpsertDoUpdateWherePlan::executeConflictArmsWithYieldTrace()` behavior; no production source change or new support component was needed.

## Focused Evidence

- Command: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningTargetFirstDynamicTest.php`
- Result: `1 test files, 16201 assertions, 0 failures`
- New focused PASS lines: `7561`

## Non-Overlap

This avoids the accepted UPSERT RETURNING hex-yield, `upsert2` repeated-source WHERE, `upsert3` literal `excluded` table, `upsert4` DO NOTHING/secondary-unique, `upsert5` arm-priority full-matrix, `returning1-17` duplicate row stream, trigger/FK RETURNING, row-value RETURNING, and recursive view UPSERT clusters. This slice owns the real upstream `upsert1` target-first multi-constraint behavior through a generic RETURNING/yield trace.

## Dependency Closure

No new support component is needed. The slice reuses existing native PHP UPSERT conflict-arm matching, unique-constraint validation, and RETURNING projection helpers.
