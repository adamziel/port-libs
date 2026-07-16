# real-upstream-corpus-trigger-fkey-dynamic-triggerF-without-rowid-20260530T222140Z

- Slice: `real-upstream-corpus-trigger-fkey-dynamic-20260530T222140Z-0`
- Accepted base: `2b1cf655ef1be10ae886e50a15d966f7036573f3`
- Upstream source: `/home/claude/port-libs/.upstream-cache/libsqlite/test/triggerF.test`
- Upstream scenarios: `triggerF` cases `1.2`, `1.3`, and `1.4`.
- Behavior ported: `WITHOUT ROWID` primary-key delete triggers fired by plain `DELETE`, `INSERT OR REPLACE`, and `UPDATE OR REPLACE`, including before-trigger row-count visibility before deletion and after-trigger row-count visibility after deletion.
- Focused PHP coverage: `SQLiteRealUpstreamTriggerFkeyDynamicTriggerFWithoutRowidTest.php` adds 15,004 focused TestRunner PASS cases and 15,005 assertions.
- Non-overlap: avoids accepted triggerD/triggerE rowid-variable coverage, trigger5 undo coverage, trigger8 large-body coverage, triggerG recursion coverage, fkey action-matrix coverage, and trigger4 view batches.
- Dependency closure: reuses existing native PHP trigger/FK row-array planning in `SQLiteDynamicTriggerForeignKeyPlan`; no new support component is needed.
- Verification:
  - `php -l lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php`
  - `php -l lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTriggerFWithoutRowidTest.php`
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTriggerFWithoutRowidTest.php`
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `git diff --check -- lanes/libsqlite`
