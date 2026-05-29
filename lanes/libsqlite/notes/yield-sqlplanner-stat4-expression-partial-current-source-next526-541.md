# SQLite planner STAT4 expression partial current-source next526-541

Behavior: extends `SQLitePlannerStat4ExpressionPartialCurrentSourceNext224Plan` with `materializeNext526541()`, a direct follow-on to the merged next510-525 preparation fence. The new fence threads the next510-525 handoff signature, rechecks each carried current-source row projection, and prepares slices 526-541 only when the prior projected rows still match the current source.

Scope: additive libsqlite planner coverage only. It does not change earlier next510-525 behavior, payload validation, page anchors, JSON, WAL, VFS, B-tree, trigger, PRAGMA, compound SELECT, or UTF lanes.

Validation:

- `php -l lanes/libsqlite/src/SQLitePlannerStat4ExpressionPartialCurrentSourceNext224Plan.php`
- `php -l lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext526541Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext526541Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext510525Test.php`
- `git diff --check`
