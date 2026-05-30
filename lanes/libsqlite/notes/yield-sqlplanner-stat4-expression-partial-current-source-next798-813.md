# SQLite planner STAT4 expression partial current-source next798-813

Behavior: extends `SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan` with `materializePreparedHandoffFourthContinuation()`, a direct follow-on to the merged next782-797 preparation fence. The new fence threads the next782-797 handoff signature, rechecks each carried current-source row projection, and prepares slices 798-813 only when the prior projected rows still match the current source.

Files:
- `SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php`
- `SQLitePlannerStat4ExpressionPartialCurrentSourceNext798813Test.php`
- `application-sqlplanner-stat4-expression-partial-current-source-next798-813.php`

Validation:
- `php -l lanes/libsqlite/src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext782797Test.php`
- `php -l lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext798813Test.php`
- `php -l lanes/libsqlite/examples/application-sqlplanner-stat4-expression-partial-current-source-next782-797.php`
- `php -l lanes/libsqlite/examples/application-sqlplanner-stat4-expression-partial-current-source-next798-813.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext782797Test.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext798813Test.php`
- `php lanes/libsqlite/examples/application-sqlplanner-stat4-expression-partial-current-source-next782-797.php --self-test`
- `php lanes/libsqlite/examples/application-sqlplanner-stat4-expression-partial-current-source-next798-813.php --self-test`
- `git diff --check`

Next slice: continue with planner814-829 from the next798-813 handoff fence.
