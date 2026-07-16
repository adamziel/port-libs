# SQL planner STAT4 expression partial current-source next606-621

Behavior: extends the canonical `SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan` with `materializeNext606621()`, a direct follow-on to merged next590-605 preparation. The new fence threads the next590-605 handoff signature, rechecks each carried current-source row projection, and prepares slices 606-621 only when the prior projected rows still match the current source.

Coverage:
- `SQLitePlannerStat4ExpressionPartialCurrentSourceNext606621Test.php` verifies status, prior fence continuity, prepared/blocked slices, cursor opcode, dependency marker, malformed projected-column rejection, and repeated handoff signature stability.
- `application-sqlplanner-stat4-expression-partial-current-source-next606-621.php --self-test` covers the matching Application-shaped example.

Validation:
- `php -l lanes/libsqlite/src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext606621Test.php`
- `php -l lanes/libsqlite/examples/application-sqlplanner-stat4-expression-partial-current-source-next606-621.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext590605Test.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext606621Test.php`
- `php lanes/libsqlite/examples/application-sqlplanner-stat4-expression-partial-current-source-next606-621.php --self-test`
- `git diff --check`
