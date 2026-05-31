# real-upstream-corpus-trigger-fkey-dynamic-20260531T103649Z-0

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/trigger1.test`
  - `trigger1-6.1..6.8`: a trigger may share its name with a table; `DROP TRIGGER` removes only the trigger and preserves the namesake table.
  - `trigger1-8.1..8.6`: quoted keyword trigger names using single quotes, double quotes, or brackets normalize to the catalog name `trigger` and can be dropped without dropping the table.

## Behavior Ported

- Added `SQLiteRealUpstreamTriggerFkeyDynamicTrigger1NameCatalog20260531Test.php`.
- Added `SQLiteDynamicTriggerForeignKeyPlan::triggerNameCatalogIdentityPlan()` to describe the catalog and drop behavior with generic table and row names.
- The focused file runs 1002 deterministic upstream-backed dynamic PASS cases plus 6 citation/guard PASS cases.
- Assertions cover namesake table/trigger catalog rows, delete guard behavior before drop, table preservation after trigger drop, quoted trigger-name normalization, quote-style handling, and malformed input guards.

## Non-Overlap

- This does not repeat accepted trigger/FK implicit DROP behavior, trigger rename/reparse behavior, temp trigger behavior, recursive/view/returning trigger helpers, or foreign-key cascade/update/delete behavior.
- This slice owns only `trigger1.test` catalog identity sections `trigger1-6.1..6.8` and `trigger1-8.1..8.6`.

## Focused Count

- Focused command: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTrigger1NameCatalog20260531Test.php`
- Result: `1 test files, 27065 assertions, 0 failures`
- PASS-line growth in the focused file: 1008 TestRunner PASS cases.
- Mapped coverage: unchanged at 1589 / 1589 because this is behavior growth over an already mapped upstream file.

## Dependency Closure

- No new support component is needed. The slice reuses the lane-local dynamic trigger/FK planner and the hydrated upstream SQLite test checkout.
