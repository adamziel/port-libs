# SQLite planner STAT4 expression partial current-source prepared handoff

Behavior: extends `SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan` with `materializeprepared handoff()`, a direct follow-on to the merged next718-733 preparation fence. The new fence threads the next718-733 handoff signature, rechecks each carried current-source row projection, and prepares slices 734-749 only when the prior projected rows still match the current source.

Files:
- `SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php`
- `SQLitePlannerStat4ExpressionPartialPreparedHandoffFinalWindowTest.php`
- `application-sqlplanner-stat4-expression-partial-prepared-handoff-final-window.php`

Validation:
- `php -l lanes/libsqlite/src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialPreparedHandoffFinalWindowTest.php`
- `php -l lanes/libsqlite/examples/application-sqlplanner-stat4-expression-partial-prepared-handoff-final-window.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext718733Test.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialPreparedHandoffFinalWindowTest.php`
- `php lanes/libsqlite/examples/application-sqlplanner-stat4-expression-partial-current-source-next718-733.php --self-test`
- `php lanes/libsqlite/examples/application-sqlplanner-stat4-expression-partial-prepared-handoff-final-window.php --self-test`
- `git diff --check`

Next slice: continue with next750-765 from the prepared handoff handoff fence.
