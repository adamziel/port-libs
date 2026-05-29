# SQL planner STAT4 expression partial current-source next670-685

Behavior: extends `SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan` with `materializeNext670685()`, a direct follow-on to the merged next654-669 preparation fence. The new fence threads the next654-669 handoff signature, rechecks each carried current-source row projection, and prepares slices 670-685 only when the prior projected rows still match the current source.

Coverage:
- `SQLitePlannerStat4ExpressionPartialCurrentSourceNext670685Test.php`
- `wordpress-sqlplanner-stat4-expression-partial-current-source-next670-685.php`

Validation:
- `php -l lanes/libsqlite/src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext670685Test.php`
- `php -l lanes/libsqlite/examples/wordpress-sqlplanner-stat4-expression-partial-current-source-next670-685.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext670685Test.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext654669Test.php`
- `php lanes/libsqlite/examples/wordpress-sqlplanner-stat4-expression-partial-current-source-next670-685.php --self-test`
- `php lanes/libsqlite/examples/wordpress-sqlplanner-stat4-expression-partial-current-source-next654-669.php --self-test`
- `git diff --check`

Non-overlap: this slice only adds the next670-685 continuation handoff after the merged next654-669 STAT4 expression partial current-source fence. It does not add a new numbered source class and does not alter earlier handoff windows, payload row-image validation, page anchors, JSON, WAL, VFS, B-tree, trigger, PRAGMA, compound SELECT, or UTF clusters.

Next slice: continue with next686-701 from the next670-685 handoff fence.
