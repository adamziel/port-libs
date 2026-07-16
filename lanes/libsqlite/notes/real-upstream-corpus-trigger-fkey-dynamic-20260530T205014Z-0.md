# real-upstream-corpus-trigger-fkey-dynamic-20260530T205014Z-0

Base accepted HEAD: `f32e8deaca85f9598bd0eb6230903f7d3fab9f57`.

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/triggerG.test`
- Ported scenarios: `triggerG-100` and `triggerG-200`.

## Change

Added `SQLiteDynamicTriggerForeignKeyPlan::recursiveOnceTriggerSelectPlan()`
and focused coverage in
`SQLiteRealUpstreamTriggerFkeyDynamicTriggerGRecursiveTest.php`.

The new behavior models recursive trigger SELECT subprogram execution where the
trigger body appends rows to the trigger source table and also inserts SELECT
results into a side table. The coverage includes single-table IN-filter SELECT
output, cross-join SELECT output, recursive trigger disablement, OP_Once-style
per-firing reset behavior, and malformed input guards.

## Non-Overlap

This does not repeat accepted fkey2 NO ACTION/RESTRICT repair, fkey5 undo SQL,
fkey6 defer-foreign-keys, trigger2 row-order, trigger4 view routing,
trigger5 undo, RAISE, variable rejection, foreign-key-check, or trigger/FK
action-journal batches. It targets the real upstream `triggerG.test` recursive
trigger SELECT-subprogram regression.

## Verification

- `php -l lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTriggerGRecursiveTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTriggerGRecursiveTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTriggerGRecursiveTest.php`
  - `1 test files, 2406 assertions, 0 failures`

## Dependency Closure

No new support component is needed. The slice reuses the existing generic
dynamic trigger/FK planner and upstream hydrated SQLite test corpus.
