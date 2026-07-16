# real-upstream-corpus-trigger-fkey-dynamic-20260530T184544Z-0

- Slice: `real-upstream-corpus-trigger-fkey-dynamic-20260530T184544Z-0`
- Base accepted HEAD: `7e63d4798cb030955a466f3272d59cba9c03648e`
- Upstream source truth:
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/trigger3.test`
  - Scenario ranges: `trigger3-1.1` through `trigger3-7.3`

## Behavior

Added a bounded native PHP model for `trigger3.test` RAISE() trigger control
flow in `SQLiteDynamicTriggerForeignKeyPlan`:

- table triggers with `RAISE(ABORT)`, `RAISE(FAIL)`, `RAISE(ROLLBACK)`, and
  `RAISE(IGNORE)`;
- no-active-transaction rollback-trigger behavior from ticket #3035;
- `RAISE(IGNORE)` row suppression for UPDATE and DELETE;
- nested-trigger `RAISE(IGNORE)` boundary behavior;
- INSTEAD OF view-trigger `RAISE(ROLLBACK)`, `RAISE(IGNORE)`, and
  `RAISE(ABORT)`.

The new corpus file uses generic row data only.

## Evidence

- `php -l lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php`
  - `No syntax errors detected`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicRaiseCorpusTest.php`
  - `No syntax errors detected`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicRaiseCorpusTest.php`
  - `1 test files, 9684 assertions, 0 failures`

Focused delta:

- New focused PASS cases: `9684`
- New focused assertions: `9684`

## Non-Overlap

This does not repeat accepted `trigger2.test` row timing, UPDATE OF/WHEN,
cascading trigger programs, conflict-policy propagation, view trigger OLD/NEW
rows, `e_fkey.test` action matrices, `fkey2.test` cascade/RESTRICT behavior,
or existing trigger RETURNING/savepoint RAISE helpers. The new surface is the
real upstream `trigger3.test` RAISE() action corpus.

## Dependency Closure

No new support component is needed. The slice reuses the lane-local dynamic
trigger/FK planner and adds bounded RAISE action state transitions needed for
native trigger execution parity.

## Next

Continue with a non-overlapping trigger/FK range such as `trigger4.test`
view-trigger persistence/reopen behavior or `fkey3.test`/`fkey4.test`
DDL-validation and mismatch behavior.
