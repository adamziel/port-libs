# SQLite planner STAT4 expression partial current-source next766-781

Behavior: extends `SQLitePlannerStat4ExpressionPartialCurrentSourceNext224Plan` with `materializeNext766781()`, a direct follow-on to the merged next750-765 preparation fence. The new fence threads the next750-765 handoff signature, rechecks each carried current-source row projection, and prepares slices 766-781 only when the prior projected rows still match the current source.

Files:
- `SQLitePlannerStat4ExpressionPartialCurrentSourceNext224Plan.php`
- `SQLitePlannerStat4ExpressionPartialCurrentSourceNext766781Test.php`
- `wordpress-sqlplanner-stat4-expression-partial-current-source-next766-781.php`

Validation:
- `php -l lanes/libsqlite/src/SQLitePlannerStat4ExpressionPartialCurrentSourceNext224Plan.php`
- `php -l lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext766781Test.php`
- `php -l lanes/libsqlite/examples/wordpress-sqlplanner-stat4-expression-partial-current-source-next766-781.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext718733Test.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext734749Test.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext750765Test.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext766781Test.php`
- `php lanes/libsqlite/examples/wordpress-sqlplanner-stat4-expression-partial-current-source-next750-765.php --self-test`
- `php lanes/libsqlite/examples/wordpress-sqlplanner-stat4-expression-partial-current-source-next766-781.php --self-test`
- `git diff --check`

Next slice: continue with next782-797 from the next766-781 handoff fence.
