# SQLite planner STAT4 expression partial current-source next910-925

Behavior: extends `SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan` with `materializeNext910925()`, a direct follow-on to the merged next894-909 preparation fence. The new fence threads the next894-909 handoff signature, rechecks each carried current-source row projection, and prepares slices 910-925 only when the prior projected rows still match the current source.

Files:
- `SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php`
- `SQLitePlannerStat4ExpressionPartialCurrentSourceNext910925Test.php`
- `application-sqlplanner-stat4-expression-partial-current-source-next910-925.php`

Validation:
- `php -l lanes/libsqlite/src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext894909Test.php`
- `php -l lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext910925Test.php`
- `php -l lanes/libsqlite/examples/application-sqlplanner-stat4-expression-partial-current-source-next894-909.php`
- `php -l lanes/libsqlite/examples/application-sqlplanner-stat4-expression-partial-current-source-next910-925.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext894909Test.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext910925Test.php`
- `php lanes/libsqlite/examples/application-sqlplanner-stat4-expression-partial-current-source-next894-909.php --self-test`
- `php lanes/libsqlite/examples/application-sqlplanner-stat4-expression-partial-current-source-next910-925.php --self-test`
- `git diff --check`

Next slice: continue with planner926-941 from the next910-925 handoff fence.
