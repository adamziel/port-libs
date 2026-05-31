# real-upstream-corpus-trigger-fkey-dynamic-triggerC-affinity-timing-20260531

Status: ready for integration from accepted base `d470482ec8f04bd52049cae518f9a06a2103fe0c`.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/triggerC.test`
- Ported sections: `triggerC-4.1.1` through `triggerC-4.1.9`.
- Behavior: INSERT/UPDATE/DELETE trigger OLD/NEW images observe affinity-coerced non-rowid values before BEFORE triggers, auto-assigned rowid is visible as `-1` to BEFORE INSERT, and REAL affinity reports `real` in trigger images even for exact integer values.

Implementation:

- Added `SQLiteDynamicTriggerForeignKeyPlan::triggerCAffinityTimingPlan()`.
- Expanded `SQLiteRealUpstreamTriggerFkeyDynamicTriggerCAffinityTimingTest.php` with 120 new native dynamic triggerC affinity timing cases plus upstream source citations, while preserving the existing upstream-plan assertions in that file.

Verification:

- `php -l lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php` passed.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTriggerCAffinityTimingTest.php` passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTriggerCAffinityTimingTest.php` passed: `1 test files, 6948 assertions, 0 failures`.
- `git diff --check -- lanes/libsqlite` passed.

Expected dashboard movement:

- `phpPass`: `2168479 -> 2171003` if accepted, counting only the 2524 newly added native-plan assertions in the existing focused file.
- `benchmarkDenominator.mapped`: unchanged at `1589 / 1589`; mapped inventory is already complete.

Non-overlap:

- This does not repeat accepted trigger/FK malloc retry, fkey8 action journal, fkey7 authorizer, trigger2 conflict/count/cascade, triggerD rowid alias, triggerE variable, triggerF WITHOUT ROWID, triggerG recursive once, fkey2 deferred graph/counter, or fkey2 statement-transaction coverage.
- The owned surface is specifically `triggerC.test` `triggerC-4.1.*` affinity timing of trigger OLD/NEW images.

Dependency closure:

- No new support component is needed. This reuses lane-local trigger/FK dynamic planning and models SQLite affinity coercion directly in native PHP.
