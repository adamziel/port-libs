# real-upstream-corpus-trigger-fkey-dynamic-20260531T035555Z-0

## Upstream source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey6.test`
- Ported sections: `fkey6-1.0..1.22`, `fkey6-2.1..2.6`, and `fkey6-4.0..4.2`.

## Behavior

Added `SQLiteDynamicTriggerForeignKeyPlan::deferForeignKeysTransactionStatusPlan()` for the upstream `PRAGMA defer_foreign_keys` lifecycle:

- default/off boundary behavior;
- DBSTATUS-style outstanding deferred-FK counter transitions;
- counter clearing after deleting the child row or dropping the child table;
- automatic reset at `COMMIT` or `ROLLBACK`;
- failed outer commit when a deferred violation remains outstanding.

## Evidence

- Focused test: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicFkey6LifecycleTest.php`
- Result: `1 test files, 1195 assertions, 0 failures`
- Non-overlap: avoids the accepted `fkey6` deferred RESTRICT trigger repair coverage and existing `trigger7`, `triggerB`, `triggerC`, `fkey1`, `fkey2`, and `fkey3` trigger/FK dynamic batches by owning only deferred-FK pragma lifecycle/status counter behavior from `fkey6.test`.
- Dependency closure: no new support component is needed; the slice reuses the existing lane-local trigger/FK planner.
