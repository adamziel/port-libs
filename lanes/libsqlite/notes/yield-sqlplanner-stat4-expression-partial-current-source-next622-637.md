# SQL planner STAT4 expression partial current-source next622-637

Behavior: extends `SQLitePlannerStat4ExpressionPartialCurrentSourceNext224Plan` with `materializeNext622637()`, a direct follow-on to the merged next606-621 preparation fence. The new fence threads the next606-621 handoff signature, rechecks each carried current-source row projection, and prepares slices 622-637 only when the prior projected rows still match the current source.

Coverage:
- `SQLitePlannerStat4ExpressionPartialCurrentSourceNext622637Test.php`
- `wordpress-sqlplanner-stat4-expression-partial-current-source-next622-637.php`

Validation:
- `php -l lanes/libsqlite/src/SQLitePlannerStat4ExpressionPartialCurrentSourceNext224Plan.php`
- `php -l lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext622637Test.php`
- `php -l lanes/libsqlite/examples/wordpress-sqlplanner-stat4-expression-partial-current-source-next622-637.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext622637Test.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext606621Test.php`
- `php lanes/libsqlite/examples/wordpress-sqlplanner-stat4-expression-partial-current-source-next622-637.php --self-test`
- `php lanes/libsqlite/examples/wordpress-sqlplanner-stat4-expression-partial-current-source-next606-621.php --self-test`
- `git diff --check`

Non-overlap: this slice only adds the next622-637 continuation handoff after the merged next606-621 STAT4 expression partial current-source fence. It does not add a new numbered source class and does not alter earlier handoff windows, payload row-image validation, page anchors, JSON, WAL, VFS, B-tree, trigger, PRAGMA, compound SELECT, or UTF clusters.

Next slice: continue with next638-653 from the next622-637 handoff fence.
