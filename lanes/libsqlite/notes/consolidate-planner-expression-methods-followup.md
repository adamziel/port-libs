## Consolidation

Renamed the direct STAT4 expression partial handoff continuation entrypoint and
its local helpers in `SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan`
from generated `Next654669` method names to descriptive unsuffixed production
method names.

Direct callers were migrated:

- `SQLitePlannerStat4ExpressionPartialCurrentSourceNext654669Test.php`
- `application-sqlplanner-stat4-expression-partial-current-source-next654-669.php`

Behavior and output keys remain unchanged so the next handoff range can still
consume the same fence data.

## Verification

- `php -l lanes/libsqlite/src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext654669Test.php`
- `php -l lanes/libsqlite/examples/application-sqlplanner-stat4-expression-partial-current-source-next654-669.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext654669Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext670685Test.php`
- `php lanes/libsqlite/examples/application-sqlplanner-stat4-expression-partial-current-source-next654-669.php --self-test`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component is needed; this only consolidates
production method names and keeps the existing planner handoff behavior.
