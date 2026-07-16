# real-upstream-corpus-trigger-fkey-dynamic-20260531T054907Z-0

Slice: `real-upstream-corpus-trigger-fkey-dynamic-20260531T054907Z-0`
Base accepted HEAD: `db171f640e25dd929585c8e1b7a1c804219fdfee`

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/triggerC.test`
- Ported sections:
  - `triggerC-1.2..1.7`: basic BEFORE/AFTER INSERT, UPDATE, and DELETE trigger old/new row image lifecycle.
  - `triggerC-1.8..1.10`: AFTER DELETE trigger with `RAISE(ABORT, 'delete is not supported')` preserves the target row after statement rollback.

## Behavior Added

- Added `SQLiteDynamicTriggerForeignKeyPlan::triggerCBasicOldNewLifecyclePlan()` for generic application rows.
- The planner records new-only INSERT images, old/new UPDATE images, old-only DELETE images, optional DELETE execution, and aborting DELETE rollback preservation.
- Added `SQLiteRealUpstreamTriggerFkeyDynamicTriggerCBasicLifecycle20260531Test.php` with 160 dynamic variants plus source-citation and malformed-input guards.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTriggerCBasicLifecycle20260531Test.php`
  - `1 test files, 6698 assertions, 0 failures`

## Non-Overlap

This does not repeat accepted triggerC default-values, affinity timing, rowid mutation, recursion-limit, trigger8 large-body, trigger9 old-column/view-rowid/view-old-row, trigger/FK deferred restrict/yield-current, fkey2/fkey7/fkey8, or recursive view/UPSERT/RETURNING batches. The new surface is the upstream triggerC basic trigger lifecycle and aborting delete preservation block.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP trigger/FK dynamic planner surface and lane-local focused TestRunner.
