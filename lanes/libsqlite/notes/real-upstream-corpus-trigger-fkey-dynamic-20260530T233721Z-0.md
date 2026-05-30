# real-upstream-corpus-trigger-fkey-dynamic-20260530T233721Z-0

Slice: `real-upstream-corpus-trigger-fkey-dynamic-20260530T233721Z-0`

Accepted base: `d7c5d7f50d0d0c3f24c91125036d23912559b628`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/trigger9.test`
  - `trigger9-1.2.1..1.7.3`: DELETE/UPDATE row triggers that reference only `OLD.rowid`, `OLD.x`, or a WHEN-filtered `OLD.x` avoid full rowdata loading.
  - `trigger9-3.2..3.6`: INSTEAD OF UPDATE triggers over plain, WHERE-alias, DISTINCT, EXCEPT, and grouped/HAVING views materialize the old rows needed by the trigger program.

Implementation:

- Added `SQLiteDynamicTriggerForeignKeyPlan::trigger9OldColumnLoadPlan()` for trigger9 old-row column subset loading and rowdata-opcode avoidance metadata.
- Added `SQLiteDynamicTriggerForeignKeyPlan::trigger9InsteadOfViewOldRowsPlan()` for INSTEAD OF view trigger old-row materialization across direct, predicate, distinct, compound, and grouped view shapes.
- Added `SQLiteRealUpstreamTriggerFkeyDynamicTrigger9OldRowsTest.php` with 5,209 focused assertions over the real upstream trigger9 scenarios.

Verification:

- `php -l lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTrigger9OldRowsTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTrigger9OldRowsTest.php`
  - Result: `1 test files, 5209 assertions, 0 failures`

Dashboard movement:

- `phpPass`: `1157667 -> 1162876` from the new focused TestRunner PASS lines.
- `benchmarkDenominator.mapped`: unchanged at `1589 / 1589`; this is behavior PASS-line growth against already mapped real upstream trigger corpus.

Dependency closure:

- No new support component is needed. This reuses existing lane-local dynamic trigger/FK corpus helpers and the hydrated SQLite upstream checkout as source truth.

Non-overlap:

- This does not repeat accepted trigger/FK fkey2 nocase repair, fkey6 deferred restrict, fkey7/fkey8, trigger2 timing, trigger4 view behavior, trigger5 undo, trigger7/trigger8/triggerB/triggerD/triggerE/triggerF/triggerG, RAISE/action-matrix, schema-drop, or statement-order batches.
- The new surface is specifically `trigger9.test` old-row column-load optimization and INSTEAD OF view-trigger old-row materialization.
