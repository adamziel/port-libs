# SQLite planner STAT4 expression partial current-source next574-589

Behavior: extends `SQLitePlannerStat4ExpressionPartialCurrentSourceNext224Plan` with `materializeNext574589()`, a direct follow-on to the merged next558-573 preparation fence. The new fence threads the next558-573 handoff signature, rechecks each carried current-source row projection, and prepares slices 574-589 only when the prior projected rows still match the current source.

Scope: additive libsqlite planner coverage only. It does not change earlier next558-573 behavior, payload validation, page anchors, JSON, WAL, VFS, B-tree, trigger, PRAGMA, compound SELECT, or UTF lanes.

Validation:

- `php -l lanes/libsqlite/src/SQLitePlannerStat4ExpressionPartialCurrentSourceNext224Plan.php`
- `php -l lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext574589Test.php`
- `php -l lanes/libsqlite/examples/wordpress-sqlplanner-stat4-expression-partial-current-source-next574-589.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext574589Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext558573Test.php`
- `php lanes/libsqlite/examples/wordpress-sqlplanner-stat4-expression-partial-current-source-next574-589.php --self-test`
- `git diff --check`
