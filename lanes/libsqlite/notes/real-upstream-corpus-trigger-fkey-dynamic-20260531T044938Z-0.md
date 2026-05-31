# real-upstream-corpus-trigger-fkey-dynamic-20260531T044938Z-0

Base accepted HEAD: `ea98db4ecded4356aee592549997cc44a35fab5b`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/triggerC.test`
- Ported section: `triggerC-13.1..13.2`

## Behavior

- An `AFTER UPDATE` trigger that updates the same table recursively advances the row image frame by frame.
- Recursive trigger execution stops at the configured depth limit with `too many levels of trigger recursion`.
- The failed recursive statement rolls back to the original row image instead of committing the attempted final values.

## Changed Lane Files

- `lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php`
- `lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTriggerCRecursionLimit20260531Test.php`
- `lanes/libsqlite/lane-status.json`
- `lanes/libsqlite/notes/real-upstream-corpus-trigger-fkey-dynamic-20260531T044938Z-0.md`

## Focused Evidence

- `php -l lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTriggerCRecursionLimit20260531Test.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTriggerCRecursionLimit20260531Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTriggerCRecursionLimit20260531Test.php`
  - `1 test files, 10327 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 3 assertions, 0 failures`
- `git diff --check -- lanes/libsqlite`
  - passed

## Countability

- Focused selected movement: `+10327` real TestRunner PASS/assertion lines.
- `lane-status.json` moves `phpPass` from `2125874` to `2136201`.
- Mapped denominator coverage remains `1589 / 1589`; this is behavior growth, not new denominator mapping.

## Non-Overlap

This ports `triggerC-13.1..13.2` recursive update depth-limit behavior. It does not repeat existing `triggerC-5.*` OR REPLACE delete-trigger firing, `triggerC-14.*` constant-loop behavior, `triggerC-15.*` quoted trigger target dequoting, accepted `trigger9` old-row/view-rowid behavior, fkey2/fkey6/fkey7/fkey8 action matrices, temp trigger schema routing, trigger UPDATE FROM, trigger5 undo, trigger7 name/drop diagnostics, trigger8 large body execution, triggerD rowid alias handling, triggerE variable rejection, triggerF WITHOUT ROWID conflicts, triggerG recursive SELECT behavior, source-neutral cleanup, or any WordPress-specific API surface.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP trigger/FK dynamic planner surface and the hydrated SQLite upstream checkout as source truth.
