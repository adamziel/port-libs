# SQL planner STAT4 expression partial current-source next638-653

Behavior: extends `SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan` with `materializeNext638653()`, a direct follow-on to the merged next622-637 preparation fence. The new fence threads the next622-637 handoff signature, rechecks each carried current-source row projection, and prepares slices 638-653 only when the prior projected rows still match the current source.

Coverage:
- `SQLitePlannerStat4ExpressionPartialCurrentSourceNext638653Test.php`
- `wordpress-sqlplanner-stat4-expression-partial-current-source-next638-653.php`

Validation:
- `php -l lanes/libsqlite/src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext638653Test.php`
- `php -l lanes/libsqlite/examples/wordpress-sqlplanner-stat4-expression-partial-current-source-next638-653.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext638653Test.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext622637Test.php`
- `php lanes/libsqlite/examples/wordpress-sqlplanner-stat4-expression-partial-current-source-next638-653.php --self-test`
- `php lanes/libsqlite/examples/wordpress-sqlplanner-stat4-expression-partial-current-source-next622-637.php --self-test`
- `git diff --check`

Non-overlap: this slice only adds the next638-653 continuation handoff after the merged next622-637 STAT4 expression partial current-source fence. It does not add a new numbered source class and does not alter earlier handoff windows, payload row-image validation, page anchors, JSON, WAL, VFS, B-tree, trigger, PRAGMA, compound SELECT, or UTF clusters.

Next slice: continue with next654-669 from the next638-653 handoff fence.
