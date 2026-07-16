# SQLite planner STAT4 expression partial current-source next334-349

Status: focused PHP behavior growth for `sqlplanner-stat4-expression-partial-current-source-next334-349`.

Behavior: extends `SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan` with `materializePreparedHandoffBridgeSeed()`, a direct follow-on to the merged next318-333 preparation fence. The fence threads the prior handoff signature, rechecks each carried current-source row projection, and prepares slices 334-349 only when the prior projected rows still match the current source.

Application path: `application-sqlplanner-stat4-expression-partial-prepared-handoff-bridge-seed.php` models copied `wp_options` plugin-admin pagination over a descending partial `lower(option_name)` covering index. A stale payload mutation or missing current row blocks the continuation before the next prepared handoff can be reused.

Validation:

- `php -l lanes/libsqlite/src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialPreparedHandoffBridgeSeedTest.php`
- `php -l lanes/libsqlite/examples/application-sqlplanner-stat4-expression-partial-prepared-handoff-bridge-seed.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialPreparedHandoffBridgeSeedTest.php`
- `php lanes/libsqlite/examples/application-sqlplanner-stat4-expression-partial-prepared-handoff-bridge-seed.php --self-test`
- `git diff --check`

Non-overlap: prepares next334-349 current-source handoff slices only. It avoids next318-333 handoff-window changes, next302-317 handoff-window changes, next286-301 handoff-window changes, next270-285 handoff-window changes, next254-269 handoff-window changes, next253 payload row-image validation, page anchors, JSON, WAL, VFS, B-tree, trigger, PRAGMA, compound SELECT, and UTF clusters.
