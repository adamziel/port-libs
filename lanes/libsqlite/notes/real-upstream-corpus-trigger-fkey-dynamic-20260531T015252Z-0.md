# real-upstream-corpus-trigger-fkey-dynamic-20260531T015252Z-0

Base accepted HEAD: `5355cb7ecea35e8be7c9099c3c6dbf4e5ec09d23`.

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/trigger9.test`
- Ported section: `trigger9-4.1..4.3`

## Behavior

- `INSTEAD OF` view triggers reject `DELETE` and `UPDATE` statements that
  reference `rowid` when rowid access through views is disabled.
- When rowid-in-view access is enabled, the same `rowid` predicates route to
  the matching view row and fire the statement-kind trigger program.
- `UPDATE v1 SET a=b WHERE rowid=2` materializes the selected NEW view row for
  the trigger program while preserving the underlying base rows because the
  upstream trigger body only writes to the log table.

## Changed Lane Files

- `lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php`
- `lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTrigger9ViewRowidTest.php`
- `lanes/libsqlite/lane-status.json`
- `lanes/libsqlite/notes/real-upstream-corpus-trigger-fkey-dynamic-20260531T015252Z-0.md`

## Focused Evidence

- `php -l lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php`
  - `No syntax errors detected`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTrigger9ViewRowidTest.php`
  - `No syntax errors detected`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTrigger9ViewRowidTest.php`
  - `1 test files, 5606 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTrigger9ViewRowidTest.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTrigger9OldRowsTest.php`
  - `2 test files, 10815 assertions, 0 failures`

## Countability

- Focused selected movement: `+5606` real TestRunner PASS/assertion lines.
- Mapped denominator coverage remains `1589 / 1589`; this is behavior growth,
  not new denominator mapping.

## Non-Overlap

This does not repeat accepted `trigger9-1.*` old-column load planning,
`trigger9-3.*` INSTEAD OF view old-row materialization, temp trigger schema
reload/attached routing, trigger UPDATE FROM, trigger5 undo, trigger7 name/drop
diagnostics, trigger8 large body execution, triggerD rowid alias handling,
triggerE variable rejection, triggerF WITHOUT ROWID conflicts, triggerG
recursive SELECT behavior, fkey action matrices, or source-neutral cleanup.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP
trigger/FK dynamic planner surface and the hydrated SQLite upstream checkout as
source truth.
