# SQLite planner STAT4 expression partial current-source next846-861

Behavior: extends `SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan` with `materializeNext846861()`, a direct follow-on to the merged next830-845 preparation fence. The new fence threads the next830-845 handoff signature, rechecks each carried current-source row projection, and prepares slices 846-861 only when the prior projected rows still match the current source.

Files:
- `SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php`
- `SQLitePlannerStat4ExpressionPartialCurrentSourceNext846861Test.php`
- `wordpress-sqlplanner-stat4-expression-partial-current-source-next846-861.php`

Validation:
- `php -l lanes/libsqlite/src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext830845Test.php`
- `php -l lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext846861Test.php`
- `php -l lanes/libsqlite/examples/wordpress-sqlplanner-stat4-expression-partial-current-source-next846-861.php`
- `php -l lanes/libsqlite/examples/wordpress-sqlplanner-stat4-expression-partial-current-source-next846-861.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext830845Test.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext846861Test.php`
- `php lanes/libsqlite/examples/wordpress-sqlplanner-stat4-expression-partial-current-source-next846-861.php --self-test`
- `php lanes/libsqlite/examples/wordpress-sqlplanner-stat4-expression-partial-current-source-next846-861.php --self-test`
- `git diff --check`

Next slice: continue with the prepared handoff resume-window from the next846-861 handoff fence.
