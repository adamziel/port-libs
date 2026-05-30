# trigger-recursive-view-returning-following-child-drain

Status: focused numbered-method consolidation for recursive view-trigger `RETURNING` current-source handoff.

This consolidation slice exposes `SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeFollowingRecursiveChildDrain`. It builds on accepted next192 following-current admission, then models recursive child rows spawned by that following current source. The next source stays fenced until the child `RETURNING` rows are acknowledged and the recursive child source token matches, so recursive trigger output is yielded from the current source before any later next-source publication.

Application smoke: `application-trigger-recursive-view-returning-following-child-drain.php` covers copied `wp_options` import rows where `blogdescription` and `template` spawn child option rows through an `INSTEAD OF` recursive view trigger while `stylesheet` does not.

Verification performed for this consolidation pass:

- `php -l lanes/libsqlite/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/examples/application-trigger-recursive-view-returning-following-child-drain.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningFollowingChildDrainTest.php`
- `php lanes/libsqlite/examples/application-trigger-recursive-view-returning-following-child-drain.php`
- `git diff --check -- lanes/libsqlite`

Non-overlap: this extends next192 cursor-close following-current admission with recursive child `RETURNING` drain fencing. It avoids accepted next189 row-ack admission, next191 fingerprint fencing, next192 cursor-close admission, row-value `RETURNING`, UPSERT, schema reparse, FK, WAL, VFS, JSON, planner, and B-tree clusters.

Dependency closure: no new support component is needed. The slice reuses lane-local recursive view trigger and current-source RETURNING planners.
