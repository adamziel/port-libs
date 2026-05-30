# Planner STAT4 Numbered Method Consolidation Seventh Pass

Scope:

- Consolidated the `SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan` next622-637 and next638-653 production entry/helper method names into descriptive stable methods.
- Updated the direct focused tests and Application examples to call the descriptive entrypoints.
- Preserved the existing asserted STAT4 handoff statuses, dependency markers, scenario strings, and cursor opcodes.

Verification:

- `php -l lanes/libsqlite/src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext622637Test.php`
- `php -l lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext638653Test.php`
- `php -l lanes/libsqlite/examples/application-sqlplanner-stat4-expression-partial-current-source-next622-637.php`
- `php -l lanes/libsqlite/examples/application-sqlplanner-stat4-expression-partial-current-source-next638-653.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext622637Test.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext638653Test.php`
  - Result: `2 test files, 78 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-sqlplanner-stat4-expression-partial-current-source-next622-637.php --self-test`
- `php lanes/libsqlite/examples/application-sqlplanner-stat4-expression-partial-current-source-next638-653.php --self-test`
- `git diff --check -- lanes/libsqlite`

Dependency closure:

- No new support component needed; this is a production method-name consolidation inside the existing STAT4 planner helper.
