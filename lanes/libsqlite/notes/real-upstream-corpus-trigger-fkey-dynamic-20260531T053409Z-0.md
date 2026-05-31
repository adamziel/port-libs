# real-upstream-corpus-trigger-fkey-dynamic-20260531T053409Z-0

Base accepted HEAD: `4492e9529d6540daf2941a27323f36260b8cf64c`.

## Upstream source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/trigger2.test`
- Sections: `trigger2-6.1a` through `trigger2-6.1g` and `trigger2-6.2a` through `trigger2-6.2g`
- Behavior: outer statement conflict policy propagation into trigger-body `INSERT OR IGNORE` and `UPDATE OR IGNORE`, including ABORT rollback, FAIL statement-row preservation, REPLACE target-row replacement, and ROLLBACK transaction rollback.

## Local movement

- Added `SQLiteUpstreamTriggerFkeyDynamicPlan::trigger2ConflictPropagation()`.
- Added `SQLiteRealUpstreamTriggerFkeyDynamicTrigger2ConflictPropagation20260531Test.php`.
- Focused result: `1 test files, 15417 assertions, 0 failures`.
- Focused PASS lines: `15404`.
- Expected selected movement: `2323745 -> 2339149 pass / 0 fail`.
- Mapped denominator movement: none; mapped coverage is already `1589 / 1589`.

## Non-overlap

This slice does not repeat accepted triggerC recursive insert/default/affinity timing, trigger2 row timing/program execution/count_changes, trigger variable rejection, trigger drop/schema binding, or fkey action-matrix coverage. It owns the upstream `trigger2.test` conflict propagation subsection for statement conflict policies inside trigger programs.

## Dependency closure

No new support component is needed. The slice reuses the existing generic upstream trigger/FK dynamic plan surface and the hydrated SQLite upstream checkout as source truth.
