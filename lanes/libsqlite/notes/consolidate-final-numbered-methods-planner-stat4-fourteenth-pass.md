# Planner STAT4 final numbered method consolidation fourteenth pass

Consolidated the shared private helper names behind the STAT4 prepared-handoff tail in
`SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan`.

- Renamed the `prepared handoff` shared private helper family to descriptive
  `ForPreparedHandoff` names.
- Kept the public compatibility surface and existing result keys unchanged for the direct
  handoff tests while removing six numbered production method declarations.
- Remaining numbered production method declarations in `lanes/libsqlite/src`: 5735.

Verification:

- `php -l lanes/libsqlite/src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialPreparedHandoffFinalWindowTest.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialPreparedHandoffFirstContinuationTest.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialPreparedHandoffSecondContinuationTest.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialPreparedHandoffThirdContinuationTest.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialPreparedHandoffFourthContinuationTest.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialPreparedHandoffFifthContinuationTest.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext942957Test.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext958973Test.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialFinalPreparedHandoffTest.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialFinalPreparedHandoffTailTest.php`
  - `10 test files, 390 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-sqlplanner-stat4-expression-partial-current-source-next942-957.php --self-test`
  - `application-sqlplanner-stat4-expression-partial-current-source-next942-957 self-test passed`
- `php lanes/libsqlite/examples/application-sqlplanner-stat4-expression-partial-current-source-next958-973.php --self-test`
  - `application-sqlplanner-stat4-expression-partial-current-source-next958-973 self-test passed`

Dependency closure: no new support component is needed; this is a production-name
consolidation of existing planner STAT4 handoff helpers.
