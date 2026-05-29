# SQLite planner STAT4 expression partial current-source next366-381

Status: focused PHP behavior growth for `sqlplanner-stat4-expression-partial-current-source-next366-381`.

Behavior: extends `SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan` with `materializeNext366381()`, a direct follow-on to the merged next350-365 preparation fence. The new fence threads the prior handoff signature, rechecks each carried current-source row projection, and prepares slices 366-381 only when the prior projected rows still match the current source.

WordPress path: `wordpress-sqlplanner-stat4-expression-partial-current-source-next366-381.php` models copied `wp_options` plugin-admin pagination over a descending partial `lower(option_name)` covering index. A stale payload mutation or missing current row blocks the continuation before the next prepared handoff can be reused.

Validation:

- `php -l lanes/libsqlite/src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext366381Test.php`
- `php -l lanes/libsqlite/examples/wordpress-sqlplanner-stat4-expression-partial-current-source-next366-381.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext366381Test.php`
- `php lanes/libsqlite/examples/wordpress-sqlplanner-stat4-expression-partial-current-source-next366-381.php --self-test`
- `git diff --check`

Non-overlap: prepares next366-381 current-source handoff slices only. It avoids next350-365 handoff-window changes, next334-349 handoff-window changes, next318-333 handoff-window changes, next302-317 handoff-window changes, next286-301 handoff-window changes, next270-285 handoff-window changes, next254-269 handoff-window changes, next253 payload row-image validation, page anchors, JSON, WAL, VFS, B-tree, trigger, PRAGMA, compound SELECT, and UTF clusters.
