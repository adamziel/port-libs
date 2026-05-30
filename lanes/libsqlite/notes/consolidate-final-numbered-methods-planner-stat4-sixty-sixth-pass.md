## Planner STAT4 Numbered Method Consolidation Sixty-Sixth Pass

Consolidated the early STAT4 expression-partial current-source production
method surface in `SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan`.

The former worker-number entry points and private helper families for the OR
split partial-expression probe and partial-predicate equality-bucket fence now
use stable descriptive names:

- `materializeCurrentOrSplitPartialExpression()`
- `materializeCurrentPartialPredicateDeltaEquality()`

The two direct focused tests, Application examples, and direct notes were renamed
away from the former worker-number suffixes and migrated to the stable
production calls. Focused behavior is preserved.

Verification:

- `php -l lanes/libsqlite/src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentOrSplitPartialExpressionTest.php`
- `php -l lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentPartialPredicateDeltaEqualityTest.php`
- `php -l lanes/libsqlite/examples/application-sqlplanner-stat4-expression-partial-current-or-split-partial-expression.php`
- `php -l lanes/libsqlite/examples/application-sqlplanner-stat4-expression-partial-current-partial-predicate-delta-equality.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentOrSplitPartialExpressionTest.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentPartialPredicateDeltaEqualityTest.php` -> `2 test files, 126 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-sqlplanner-stat4-expression-partial-current-or-split-partial-expression.php --self-test`
- `php lanes/libsqlite/examples/application-sqlplanner-stat4-expression-partial-current-partial-predicate-delta-equality.php --self-test`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component is needed; this only renames a
bounded existing STAT4 planner method/helper surface and its direct callers.
