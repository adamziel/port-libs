# real-upstream-corpus-trigger-fkey-dynamic-before-update-self-mutation-20260530T214510Z-0

Status: ready focused real-upstream trigger/FK dynamic behavior slice.

Upstream source truth:
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/triggerC.test`
- Sections: `triggerC-10.1`, `triggerC-10.2`, and `triggerC-10.3`.

Behavior added:
- Added `SQLiteDynamicTriggerForeignKeyPlan::beforeUpdateSelfMutationPreservesColumns()` for the upstream triggerC case where a `BEFORE UPDATE` trigger mutates the same row as the parent UPDATE.
- The parent UPDATE applies only its assigned columns, so trigger-written columns not assigned by the parent statement survive into the final row image.
- The focused corpus covers 125 generic application row shapes, narrow and wide rows, parent-assigned columns, trigger-assigned counters, preserved trigger columns, original-row image retention, and malformed input guards.

Focused verification:
- `php -l lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicBeforeUpdateSelfMutationTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicBeforeUpdateSelfMutationTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicBeforeUpdateSelfMutationTest.php`
  - `1 test files, 2886 assertions, 0 failures`
  - 2757 selected PASS cases.

Non-overlap:
- This does not repeat accepted trigger/FK deferred restrict, fkey1 replacement cascade, fkey2 cascade recursion, fkey5 undo SQL, triggerC OR REPLACE delete-trigger firing, triggerG recursive SELECT, statement preservation, UPSERT/RETURNING trigger paths, PRAGMA FK-list/index-xinfo, WAL/VFS, B-tree, JSON, planner, or source-neutral cleanup slices.
- The new surface is specifically upstream `triggerC.test triggerC-10.*` BEFORE UPDATE self-mutation preservation.

Dependency closure:
- No new support component is needed. The slice reuses lane-local trigger/FK row-image planning and identifier validation.
