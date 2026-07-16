# real-upstream-corpus-trigger-fkey-dynamic-real-trigger-20260531T124640Z-0

Lane: `libsqlite`
Base accepted HEAD: `b46358aff7aa9b475bc4c01fea4fdbf8d07e53e1`

## Source Truth

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/trigger1.test`
- Upstream scenarios: `trigger1-10.0` through `trigger1-10.11`
- Behavior: TEMP triggers installed on `main.t4`, `temp.t4`, and `aux.t4` survive schema reload/reopen boundaries; trigger-body writes inside a rolled-back transaction do not leak; unqualified `insert_log` in the trigger program is resolved when the statement runs, so after `main.insert_log` is dropped and `aux.insert_log(db,d,e,f)` is created, the same TEMP trigger body writes to the attached table with the new column names.

## Patch

- Added `SQLiteDynamicTriggerForeignKeyPlan::trigger1TempTriggerReinstallRebindPlan()`.
- Added `SQLiteRealUpstreamCorpusTriggerFkeyDynamicRealTriggerTempRebind20260531Test.php`.
- Updated lane-local status to account for `+3130` focused PASS cases, from `2918190` to `2921320`.

## Evidence

- Focused test:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusTriggerFkeyDynamicRealTriggerTempRebind20260531Test.php`
  - Result: `1 test files, 3385 assertions, 0 failures`
  - PASS cases: `3130`

## Non-Overlap

This does not repeat accepted or existing trigger/FK slices for `trigger2` row timing/program execution, `trigger3` RAISE behavior, `trigger4` view routing, `trigger5` undo SQL, `trigger6` expression evaluation, `trigger7` diagnostics/pruning, `trigger8` large trigger bodies, `trigger9` OLD-row/view-rowid materialization, `triggerD`/`triggerE` rowid and variable boundaries, `triggerupfrom`, or the separate `temptrigger.test` shared-cache/attached-chain corpus. It specifically ports the `trigger1.test` temp trigger reinstall/body-target rebind cluster.

## Dependency Closure

No new support component is needed. The slice reuses the existing native trigger/FK dynamic planning surface and only models SQLite trigger catalog/body name-resolution semantics in lane-local PHP code.
