# SQLite planner STAT4 expression partial current-source next478-493

Status: focused PHP behavior growth for `sqlplanner-stat4-expression-partial-current-source-next478-493`.

Behavior: extends the established canonical `SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan` source with `materializeNext478493()`, a direct follow-on to the merged next462-477 preparation fence. No new numbered source class was created because the local pattern keeps the chained planner STAT4 expression/partial handoff slices in this class.

WordPress path: `wordpress-sqlplanner-stat4-expression-partial-current-source-next478-493.php` models copied `wp_options` plugin-admin pagination over a descending partial `lower(option_name)` covering index. It carries the next462-477 current-source STAT4 handoff into next478-493 only when the projected current rows still match.

Validation:

- `php -l lanes/libsqlite/src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext478493Test.php`
- `php -l lanes/libsqlite/examples/wordpress-sqlplanner-stat4-expression-partial-current-source-next478-493.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext478493Test.php`
- `php lanes/libsqlite/examples/wordpress-sqlplanner-stat4-expression-partial-current-source-next478-493.php --self-test`
- `git diff --check`

Non-overlap: prepares next478-493 current-source handoff slices only. It avoids next462-477 handoff-window changes, next430-445 handoff-window changes, next414-429 handoff-window changes, next398-413 handoff-window changes, next382-397 handoff-window changes, next366-381 handoff-window changes, next334-349 handoff-window changes, next318-333 handoff-window changes, next302-317 handoff-window changes, next286-301 handoff-window changes, next270-285 handoff-window changes, next254-269 handoff-window changes, next253 payload row-image validation, page anchors, JSON, WAL, VFS, B-tree, trigger, PRAGMA, compound SELECT, and UTF clusters.
