# real-upstream-corpus-trigger-fkey-dynamic-20260531T040351Z-0

Slice source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey7.test`
  `fkey7-4.1..4.6`: `INSERT OR FAIL` stops at the first FK/UNIQUE failure,
  preserves prior successful rows for FAIL, and leaves `foreign_key_check`
  empty.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/trigger1.test`
  `trigger1-24.1..24.2`: `RAISE()` accepts an SQL expression message that can
  reference `NEW` row values and rolls back or preserves prior rows according
  to the conflict action.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/triggerB.test`
  `triggerB-3.1..3.2`: trigger OLD/NEW column masks must handle columns above
  32, including `c60`.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/triggerF.test`
  `triggerF-1.*`: WITHOUT ROWID conflict deletes fire BEFORE/AFTER delete
  triggers in the expected order.

Added focused PHP coverage:

- `SQLiteRealUpstreamTriggerFkeyDynamicConflictRaiseMask20260531Test.php`
  adds high-volume dynamic assertions over the existing generic
  `SQLiteDynamicTriggerForeignKeyPlan` methods:
  `insertOrFailForeignKeyBatch()`, `triggerRaiseExpressionPowerOfTwo()`,
  `wideColumnTriggerMaskPlan()`, and
  `withoutRowidConflictDeleteTriggerPlan()`.

Non-overlap:

- This does not repeat the accepted trigger/FK nocase repair, fkey2 deferred
  graph/counter, fkey6 deferred restrict, fkey7 authorizer, fkey8 attached
  restrict, DDL, recursive cascade pragma, or triggerG recursive OP_Once files.
- No new support component is needed; the existing generic PHP plan helper is
  reused.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicConflictRaiseMask20260531Test.php`
  passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicConflictRaiseMask20260531Test.php`
  passed with `1 test files, 3462 assertions, 0 failures`.
