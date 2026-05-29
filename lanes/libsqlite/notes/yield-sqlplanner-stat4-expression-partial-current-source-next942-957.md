# SQLite planner STAT4 expression partial current-source next942-957

Behavior: extends `SQLitePlannerStat4ExpressionPartialCurrentSourceNext224Plan` with `materializeNext942957()`, a direct follow-on to the merged next926-941 preparation fence. The new fence threads the next926-941 handoff signature, rechecks each carried current-source row projection, and prepares slices 942-957 only when the prior projected rows still match the current source.

Files:
- `SQLitePlannerStat4ExpressionPartialCurrentSourceNext224Plan.php`
- `SQLitePlannerStat4ExpressionPartialCurrentSourceNext942957Test.php`
- `wordpress-sqlplanner-stat4-expression-partial-current-source-next942-957.php`

Validation:
- `php -l lanes/libsqlite/src/SQLitePlannerStat4ExpressionPartialCurrentSourceNext224Plan.php`
- `php -l lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext926941Test.php`
- `php -l lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext942957Test.php`
- `php -l lanes/libsqlite/examples/wordpress-sqlplanner-stat4-expression-partial-current-source-next926-941.php`
- `php -l lanes/libsqlite/examples/wordpress-sqlplanner-stat4-expression-partial-current-source-next942-957.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext926941Test.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext942957Test.php`
- `php lanes/libsqlite/examples/wordpress-sqlplanner-stat4-expression-partial-current-source-next926-941.php --self-test`
- `php lanes/libsqlite/examples/wordpress-sqlplanner-stat4-expression-partial-current-source-next942-957.php --self-test`
- `git diff --check`

Next slice: continue with planner958-973 from the next942-957 handoff fence.
