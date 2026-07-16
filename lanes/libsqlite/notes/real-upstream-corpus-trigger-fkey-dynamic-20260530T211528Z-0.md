# real-upstream-corpus-trigger-fkey-dynamic-20260530T211528Z-0

Status: ready focused PHP TestRunner growth from real upstream SQLite trigger
corpus behavior.

Upstream source:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/trigger1.test`
- `trigger1-1.10`: DELETE statement preservation while an AFTER DELETE trigger
  deletes another row using `old.a`.
- `trigger1-1.11`: UPDATE statement preservation while an AFTER UPDATE trigger
  deletes another row using `old.a`.

Added coverage:

- `SQLiteRealUpstreamTriggerFkeyDynamicStatementPreservationTest.php`
- 125 dynamic DELETE statement-preservation cases over varying row ids,
  trigger names, side-effect delete targets, remaining row images, and total
  change accounting.
- 125 dynamic UPDATE statement-preservation cases over varying row ids,
  trigger names, side-effect delete targets, updated payloads, remaining row
  images, and total change accounting.
- 3,377 distinct focused TestRunner PASS cases / 3,505 assertions.

Non-overlap:

- This does not repeat accepted fkey2/fkey6/fkey8 action/defer/journal
  behavior, trigger2 selective/cascading/conflict behavior, trigger3 RAISE
  behavior, triggerG recursive SELECT subprogram behavior, trigger/FK
  savepoint/RETURNING/current-source batches, PRAGMA foreign-key catalog
  coverage, schema reparse coverage, WAL/VFS, JSON, B-tree, planner, or suite
  metadata work.
- The new surface is upstream `trigger1.test` statement cursor preservation:
  trigger side-effect DELETE operations must not corrupt the outer DELETE or
  UPDATE row stream.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicStatementPreservationTest.php`
  - `1 test files, 3505 assertions, 0 failures`

Dependency closure:

- No new support component is needed. The slice reuses
  `SQLiteDynamicTriggerForeignKeyPlan::deleteWithAfterTrigger()` and
  `SQLiteDynamicTriggerForeignKeyPlan::updateWithAfterTrigger()` for bounded
  native PHP trigger side-effect modeling.
