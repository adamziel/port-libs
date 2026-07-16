# SQL planner STAT4 expression partial current-source next654-669

Behavior: extends `SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan` with the descriptive `materializeStat4ExpressionPartialHandoffContinuation()` entrypoint, a direct follow-on to the merged next638-653 preparation fence. The new fence threads the next638-653 handoff signature, rechecks each carried current-source row projection, and prepares slices 654-669 only when the prior projected rows still match the current source.

Coverage:
- `SQLitePlannerStat4ExpressionPartialCurrentSourceNext654669Test.php`
- `application-sqlplanner-stat4-expression-partial-current-source-next654-669.php`

Validation:
- `php -l lanes/libsqlite/src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext654669Test.php`
- `php -l lanes/libsqlite/examples/application-sqlplanner-stat4-expression-partial-current-source-next654-669.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext654669Test.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext638653Test.php`
- `php lanes/libsqlite/examples/application-sqlplanner-stat4-expression-partial-current-source-next654-669.php --self-test`
- `php lanes/libsqlite/examples/application-sqlplanner-stat4-expression-partial-current-source-next638-653.php --self-test`
- `git diff --check`

Non-overlap: this slice only adds the next654-669 continuation handoff after the merged next638-653 STAT4 expression partial current-source fence. It does not add a new numbered source class and does not alter earlier handoff windows, payload row-image validation, page anchors, JSON, WAL, VFS, B-tree, trigger, PRAGMA, compound SELECT, or UTF clusters.

Next slice: continue with next670-685 from the next654-669 handoff fence.
