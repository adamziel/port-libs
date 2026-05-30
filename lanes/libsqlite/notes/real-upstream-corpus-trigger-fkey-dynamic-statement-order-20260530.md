# Real Upstream Trigger/FK Dynamic Statement Order

Slice: `real-upstream-corpus-trigger-fkey-dynamic-20260530T221659Z-0`

Accepted base: `661e026d244a8143c42a9b42e699177ff26e29f3`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/trigger2.test`
  - `trigger2-1.1..1.3`: BEFORE/AFTER row triggers for UPDATE, DELETE, and INSERT.
  - Conditional AFTER UPDATE trigger checks that the WHEN clause uses the OLD row image.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/trigger9.test`
  - `trigger9-1.2..1.7`: BEFORE DELETE/UPDATE trigger rollback boundaries.
  - `trigger9-3.*`: INSTEAD OF view trigger admission blocks.

New focused coverage:

- Added `lanes/libsqlite/tests/SQLiteRealUpstreamCorpusTriggerFkeyDynamicStatementOrderTest.php`.
- The test generates 120 dynamic generic row sets over `SQLiteDynamicTriggerForeignKeyPlan::rowTriggerExecutionOrder()`.
- Focused PASS-line count: 2044 distinct TestRunner PASS cases.
- Focused assertion count: 2056 behavior assertions.

Non-overlap:

- This slice does not add another fkey2/fkey5/fkey6/fkey7/trigger5 row.
- It targets trigger2 row-trigger statement-order and trigger9 rollback/view-trigger source citations, distinct from existing FK action, defer pragma, authorizer, nocase repair, and trigger5 undo coverage.

Dependency closure:

- No new support component is needed.
- Existing bounded native PHP trigger/FK plan helpers are reused.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusTriggerFkeyDynamicStatementOrderTest.php`
  - `1 test files, 2056 assertions, 0 failures`
  - 2044 PASS lines

Root harness:

- Not run - isolated micro-slice.
