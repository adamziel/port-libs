# trigger-recursive-view-returning-current-source-next196

Status: focused PHP behavior growth for recursive view-trigger `RETURNING` current-source handoff.

This slice adds `SQLiteTriggerRecursiveViewReturningCurrentSourceNext196Plan`. It builds on accepted next192 following-current admission, then models recursive child rows spawned by that following current source. The next source stays fenced until the child `RETURNING` rows are acknowledged and the recursive child source token matches, so recursive trigger output is yielded from the current source before any later next-source publication.

WordPress smoke: `wordpress-trigger-recursive-view-returning-current-source-next196.php` covers copied `wp_options` import rows where `blogdescription` and `template` spawn child option rows through an `INSTEAD OF` recursive view trigger while `stylesheet` does not.

Verification:

- `php -l lanes/libsqlite/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNext196Plan.php`
- `php -l lanes/libsqlite/examples/wordpress-trigger-recursive-view-returning-current-source-next196.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext196Test.php`
- `php lanes/libsqlite/examples/wordpress-trigger-recursive-view-returning-current-source-next196.php`
- `git diff --check -- lanes/libsqlite`

Non-overlap: this extends next192 cursor-close following-current admission with recursive child `RETURNING` drain fencing. It avoids accepted next189 row-ack admission, next191 fingerprint fencing, next192 cursor-close admission, row-value `RETURNING`, UPSERT, schema reparse, FK, WAL, VFS, JSON, planner, and B-tree clusters.

Dependency closure: no new support component is needed. The slice reuses lane-local recursive view trigger and current-source RETURNING planners.
