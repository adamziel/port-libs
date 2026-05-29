# SQLite planner STAT4 expression partial current-source next782-797

Behavior: extends `SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan` with `materializePreparedHandoffThirdContinuation()`, a direct follow-on to the merged next766-781 preparation fence. The new fence threads the next766-781 handoff signature, rechecks each carried current-source row projection, and prepares slices 782-797 only when the prior projected rows still match the current source.

Files:
- `SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php`
- `SQLitePlannerStat4ExpressionPartialCurrentSourceNext782797Test.php`
- `wordpress-sqlplanner-stat4-expression-partial-current-source-next782-797.php`

Validation:
- `php -l lanes/libsqlite/src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext782797Test.php`
- `php -l lanes/libsqlite/examples/wordpress-sqlplanner-stat4-expression-partial-current-source-next782-797.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext750765Test.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext766781Test.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext782797Test.php`
- `php lanes/libsqlite/examples/wordpress-sqlplanner-stat4-expression-partial-current-source-next766-781.php --self-test`
- `php lanes/libsqlite/examples/wordpress-sqlplanner-stat4-expression-partial-current-source-next782-797.php --self-test`
- `git diff --check`

Next slice: continue with next798-813 from the next782-797 handoff fence.
