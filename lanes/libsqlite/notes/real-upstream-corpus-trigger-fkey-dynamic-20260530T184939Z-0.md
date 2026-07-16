# real-upstream-corpus-trigger-fkey-dynamic-20260530T184939Z-0

Status: added a focused real-upstream trigger/FK dynamic corpus batch.

Upstream source truth:
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey2.test`
  - `fkey2-1.1.*`, `fkey2-1.2.*` immediate/deferred FK checks.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey6.test`
  - `fkey6-1.2` through `fkey6-1.10` ON UPDATE action behavior.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/trigger1.test`
  - `trigger1-3.*`, `trigger1-10.*`, `trigger1-17.*` through `trigger1-24.*` trigger timing/error behavior.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/trigger4.test`
  - `trigger4-1.*` through `trigger4-7.*` recursive trigger effects.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/trigger7.test`
  - `trigger7-1.*` through `trigger7-3.*` trigger/FK interaction.

Behavior added:
- `SQLiteRealUpstreamTriggerFkeyDynamicCurrentTest.php` adds 1,200 focused TestRunner cases using generic application rows.
- 600 update cases cover CASCADE, SET NULL, deferred NO ACTION, immediate NO ACTION rollback, trigger `RAISE(IGNORE)`, and trigger rollback.
- 600 recursive delete cases cover statement/trigger delete chains, child/grandchild FK cascade removal, savepoint rollback after RETURNING yield, non-recursive trigger mode, and depth-limit rejection.

Non-overlap:
- This does not add metadata-only suite rows or generated fake upstream script ids.
- It avoids accepted trigger/FK savepoint, OR REPLACE, action-matrix, trigger2, schema invalidation, UPSERT/RETURNING, PRAGMA FK-list, WAL/VFS, B-tree, JSON, SELECT, and source-neutral cleanup surfaces by exercising a fresh real-upstream dynamic corpus file with generic row names.

Dependency closure:
- No new support component is needed. The batch reuses existing native trigger/FK/savepoint and recursive trigger helpers.

Verification:
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicCurrentTest.php`
  - `1 test files, 5350 assertions, 0 failures`
  - 1,200 focused TestRunner PASS cases.
