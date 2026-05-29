# SQLite planner STAT4 expression partial current-source next558-573

Behavior: extends `SQLitePlannerStat4ExpressionPartialCurrentSourceNext224Plan` with `materializeNext558573()`, a direct follow-on to the merged next542-557 preparation fence. The new fence threads the next542-557 handoff signature, rechecks each carried current-source row projection, and prepares slices 558-573 only when the prior projected rows still match the current source.

Scope: additive libsqlite planner coverage only. It does not change earlier next542-557 behavior, payload validation, page anchors, JSON, WAL, VFS, B-tree, trigger, PRAGMA, compound SELECT, or UTF lanes.

Validation:

- `php -l lanes/libsqlite/src/SQLitePlannerStat4ExpressionPartialCurrentSourceNext224Plan.php`
- `php -l lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext558573Test.php`
- `php -l lanes/libsqlite/examples/wordpress-sqlplanner-stat4-expression-partial-current-source-next558-573.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext558573Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext542557Test.php`
- `php lanes/libsqlite/examples/wordpress-sqlplanner-stat4-expression-partial-current-source-next558-573.php --self-test`
- `git diff --check`
