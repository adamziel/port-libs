# real-upstream-corpus-trigger-fkey-dynamic-20260530T235419Z-0

Source truth:
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey2.test`
- Upstream section: `fkey2-2.1..2.17`, deferred foreign keys inside transactions.

Behavior ported:
- Deferred leaf insert can be repaired by inserting the missing node before commit.
- Deferred self-reference failure blocks commit but leaves the transaction open for repair.
- Parent delete remains deferred until commit and can be repaired by reinserting the parent.
- Rollback after a failed deferred commit restores the original graph rows.

Focused evidence:
- `php -l lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicFkey2DeferredGraphTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicFkey2DeferredGraphTest.php`
  - `1 test files, 9885 assertions, 0 failures`
  - `1042` focused PASS lines

Non-overlap:
- This does not repeat the accepted fkey1 dynamic corpus, fkey7 authorizer/batch work, trigger2/trigger5/trigger9 program batches, or the accepted trigger/FK fkey1 sweep in `5b28f965d6f8e02568efabc1e03fe995b898ae37`.

Dependency closure:
- No new support component is needed. The slice reuses the existing native PHP trigger/FK dynamic plan class and lane-local TestRunner.
