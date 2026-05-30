# real-upstream-corpus-trigger-fkey-dynamic-20260530T200246Z-0

Status: ready handoff for real upstream trigger/FK corpus coverage.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/trigger4.test`
- Scenario range: `trigger4-1.1` through `trigger4-7.2`

Behavior added:

- Added `SQLiteDynamicTriggerForeignKeyPlan::viewInsteadOfTriggerRouting()` for
  `INSTEAD OF` trigger routing from a join/simple view to backing table rows.
- Covers upstream trigger4 insert routing into both base tables, update routing
  of view columns to backing tables, delete routing from a simple view, failed
  trigger-program execution when a backing table is missing, and bulk view
  delete/insert/update behavior.
- Added focused real-upstream PHP corpus
  `SQLiteRealUpstreamTriggerFkeyDynamicTrigger4ViewBatchTest.php`.

Focused verification:

- `php -l lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTrigger4ViewBatchTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTrigger4ViewBatchTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTrigger4ViewBatchTest.php`
  - `1 test files, 8266 assertions, 0 failures`

Focused corpus count:

- New focused TestRunner PASS cases: 7083
- New focused behavior assertions: 8266
- Expected lane `phpPass` movement: `+7083` if accepted as a new selected PHP
  test file.

Non-overlap:

- This extends the accepted trigger/FK dynamic corpus without repeating the
  earlier `trigger2.test` BEFORE/AFTER timing, selective UPDATE OF/WHEN,
  cascaded trigger program, count-changes, conflict-policy, view expression
  rows, `trigger3.test` RAISE(), `fkey1.test` replacement cascade,
  `fkey2.test` recursive cascade, RESTRICT repair-trigger, composite cascade
  mapping, blob-column, self-reference, or current/next action-matrix batches.
- The new surface is specifically `trigger4.test` view-trigger routing and
  backing-table error behavior.

Dependency closure:

- No new support component is needed. The slice reuses the existing native PHP
  dynamic trigger/FK planner and adds a bounded view-trigger routing model.

Next task:

- Continue trigger/FK corpus work with a non-overlapping upstream range such as
  `trigger5.test` temporary trigger/schema behavior or `fkey3.test` action
  edge cases.
