# SQLite planner STAT4 expression partial current-source next878-893

Behavior: extends `SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan` with `materializeNext878893()`, a direct follow-on to the merged prepared handoff resume-window preparation fence. The new fence threads the prepared handoff resume-window handoff signature, rechecks each carried current-source row projection, and prepares slices 878-893 only when the prior projected rows still match the current source.

Files:
- `SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php`
- `SQLitePlannerStat4ExpressionPartialCurrentSourceNext878893Test.php`
- `wordpress-sqlplanner-stat4-expression-partial-current-source-next878-893.php`

Validation:
- `php -l lanes/libsqlite/src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialPreparedHandoffResumeWindowTest.php`
- `php -l lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext878893Test.php`
- `php -l lanes/libsqlite/examples/wordpress-sqlplanner-stat4-expression-partial-prepared-handoff-resume-window.php`
- `php -l lanes/libsqlite/examples/wordpress-sqlplanner-stat4-expression-partial-current-source-next878-893.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialPreparedHandoffResumeWindowTest.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext878893Test.php`
- `php lanes/libsqlite/examples/wordpress-sqlplanner-stat4-expression-partial-prepared-handoff-resume-window.php --self-test`
- `php lanes/libsqlite/examples/wordpress-sqlplanner-stat4-expression-partial-current-source-next878-893.php --self-test`
- `git diff --check`

Next slice: continue with planner894-909 from the next878-893 handoff fence.
