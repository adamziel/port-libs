# real-upstream-corpus-trigger-fkey-dynamic-20260530T195829Z-0

- Slice: `real-upstream-corpus-trigger-fkey-dynamic-20260530T195829Z-0`
- Base accepted HEAD: `688b5b5b02ee30d2a82f4468b5b909f17254ae0e`
- Upstream source:
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/trigger2.test`
  - Scenario range: `trigger2-11.1` through `trigger2-11.2`

## Status

Added focused real-upstream corpus coverage for trigger-program variable
rejection. SQLite rejects bound parameters inside trigger bodies at trigger
creation time (`trigger cannot use variables`), while ordinary literal
`old`/`new` references remain accepted. The new native helper reports the
parse-time boundary before trigger installation and identifies positional and
named parameter forms in bounded generic application trigger bodies.

Focused delta:

- New focused test file: `lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicVariableRejectionTest.php`
- New focused PASS assertions: `1578`
- `lane-status.json` `phpPass`: `496269 -> 497847`
- Mapped denominator coverage unchanged at `1472 / 1589`; this is additional
  behavior coverage for an already mapped trigger/FK upstream family.

## Verification

- `php -l lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicVariableRejectionTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicVariableRejectionTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicVariableRejectionTest.php`
  - `1 test files, 1578 assertions, 0 failures`

## Non-Overlap

This avoids earlier real trigger/FK corpus coverage for `trigger2.test`
row-trigger timing (`1.1` through `1.3`), selective `UPDATE OF` / `WHEN`
clauses, cascaded trigger programs, conflict policy propagation, view
`INSTEAD OF` old/new rows, expression-view trigger rows, `trigger3.test`
RAISE behavior, and accepted FK action matrices. The new surface is
specifically `trigger2-11` parse-time rejection of trigger bodies containing
bound parameters.

## Dependency Closure

No new support component is needed. The slice reuses the existing lane-local
dynamic trigger/FK planner and adds a bounded parser-admission check for
trigger-program parameter tokens.

## Next

Continue trigger/FK corpus work with a non-overlapping upstream range such as
`trigger4.test` recursive-trigger effects or a remaining `fkey3.test` /
`fkey4.test` behavior cluster.
