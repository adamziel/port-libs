# SQLite planner STAT4 expression partial prepared handoff window

Behavior: consolidates `SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializePreparedHandoffWindow()`, a direct follow-on to the merged next814-829 preparation fence. The stable fence threads the next814-829 handoff signature, rechecks each carried current-source row projection, and prepares its handoff window only when the prior projected rows still match the current source.

Files:
- `SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php`
- `SQLitePlannerStat4ExpressionPartialPreparedHandoffWindowTest.php`
- `application-sqlplanner-stat4-expression-partial-prepared-handoff-window.php`

Validation:
- `php -l lanes/libsqlite/src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext814829Test.php`
- `php -l lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialPreparedHandoffWindowTest.php`
- `php -l lanes/libsqlite/examples/application-sqlplanner-stat4-expression-partial-current-source-next814-829.php`
- `php -l lanes/libsqlite/examples/application-sqlplanner-stat4-expression-partial-prepared-handoff-window.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext814829Test.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialPreparedHandoffWindowTest.php`
- `php lanes/libsqlite/examples/application-sqlplanner-stat4-expression-partial-current-source-next814-829.php --self-test`
- `php lanes/libsqlite/examples/application-sqlplanner-stat4-expression-partial-prepared-handoff-window.php --self-test`
- `git diff --check`

Next slice: continue with planner846-861 from the prepared handoff window fence.
