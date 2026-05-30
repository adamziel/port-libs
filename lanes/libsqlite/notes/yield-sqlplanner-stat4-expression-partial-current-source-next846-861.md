# SQLite planner STAT4 expression partial prepared handoff continuation window

Behavior: extends `SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializePreparedHandoffContinuationWindow()`, a direct follow-on to the merged prepared handoff window fence. The stable continuation fence threads the prepared handoff window handoff signature, rechecks each carried current-source row projection, and prepares its continuation window only when the prior projected rows still match the current source.

Files:
- `SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php`
- `SQLitePlannerStat4ExpressionPartialPreparedHandoffContinuationWindowTest.php`
- `application-sqlplanner-stat4-expression-partial-prepared-handoff-continuation-window.php`

Validation:
- `php -l lanes/libsqlite/src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialPreparedHandoffWindowTest.php`
- `php -l lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialPreparedHandoffContinuationWindowTest.php`
- `php -l lanes/libsqlite/examples/application-sqlplanner-stat4-expression-partial-prepared-handoff-window.php`
- `php -l lanes/libsqlite/examples/application-sqlplanner-stat4-expression-partial-prepared-handoff-continuation-window.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialPreparedHandoffWindowTest.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialPreparedHandoffContinuationWindowTest.php`
- `php lanes/libsqlite/examples/application-sqlplanner-stat4-expression-partial-prepared-handoff-window.php --self-test`
- `php lanes/libsqlite/examples/application-sqlplanner-stat4-expression-partial-prepared-handoff-continuation-window.php --self-test`
- `git diff --check`

Next slice: continue with the prepared handoff resume-window from the prepared handoff continuation-window fence.
