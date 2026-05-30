# SQLite planner STAT4 expression partial current-source next750-765

Behavior: extends `SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan` with `materializePreparedHandoffFirstContinuation()`, a direct follow-on to the merged next734-749 preparation fence. The new fence threads the next734-749 handoff signature, rechecks each carried current-source row projection, and prepares slices 750-765 only when the prior projected rows still match the current source.

Files:
- `SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php`
- `SQLitePlannerStat4ExpressionPartialCurrentSourceNext750765Test.php`
- `application-sqlplanner-stat4-expression-partial-current-source-next750-765.php`

Validation:
- `php -l lanes/libsqlite/src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext750765Test.php`
- `php -l lanes/libsqlite/examples/application-sqlplanner-stat4-expression-partial-current-source-next750-765.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext718733Test.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialPreparedHandoffFinalWindowTest.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext750765Test.php`
- `php lanes/libsqlite/examples/application-sqlplanner-stat4-expression-partial-prepared-handoff-final-window.php --self-test`
- `php lanes/libsqlite/examples/application-sqlplanner-stat4-expression-partial-current-source-next750-765.php --self-test`
- `git diff --check`

Next slice: continue with next766-781 from the next750-765 handoff fence.
