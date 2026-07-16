# real-upstream-corpus-trigger-fkey-dynamic-20260531T075159Z-0

Base accepted HEAD: `9d7a6158784515939dbe96138a460121fe325c71`.

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/trigger1.test`
- Ported sections: `trigger1-16.1..16.7`

## Behavior

- Trigger-program `INSERT`, `UPDATE`, and `DELETE` statements reject qualified target table names.
- Trigger-program `UPDATE` and `DELETE` statements reject `NOT INDEXED`.
- Trigger-program `UPDATE` and `DELETE` statements reject `INDEXED BY`.
- Equivalent unqualified DML statements remain admissible.

## Changed Lane Files

- `lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php`
- `lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTrigger1ProgramRestrictions20260531Test.php`
- `lanes/libsqlite/lane-status.json`
- `lanes/libsqlite/notes/real-upstream-corpus-trigger-fkey-dynamic-20260531T075159Z-0.md`

## Focused Evidence

- `php -l lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTrigger1ProgramRestrictions20260531Test.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTrigger1ProgramRestrictions20260531Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTrigger1ProgramRestrictions20260531Test.php`
  - `1 test files, 10941 assertions, 0 failures`

## Countability

- Focused selected movement: `+10941` behavior assertions from real upstream `trigger1.test` cases.
- Mapped denominator coverage remains `1589 / 1589`; this is behavior growth over an already mapped upstream file, not new denominator mapping.

## Non-Overlap

This does not repeat accepted `trigger1-1.10..1.11` statement preservation,
`trigger1-1.12..1.14` target class validation, `trigger1-17..24` late
regressions, temp trigger schema reload, trigger6 expression evaluation,
trigger9 view rowid/OLD-row materialization, triggerC, triggerG, fkey action
matrices, source-neutral cleanup, or suite metadata admission. This slice is
limited to upstream `trigger1-16.*` trigger-program DML restrictions.

## Dependency Closure

No new support component is needed. This reuses the existing dynamic trigger/FK
planner class and the hydrated upstream SQLite checkout as source truth.
