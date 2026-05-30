# Consolidate Final Numbered Planner STAT4 Dynamic

Consolidated the direct final prepared handoff test helpers for
`SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan` by replacing the
remaining numeric `974989` helper suffix with descriptive final-prepared-handoff
helper names. Observable planner metadata remains unchanged, including the
legacy `stat4Next958973PreparationFence` alias and `next958973Prepared` receipt
key that downstream tests still assert.

Verification:

- `php -l lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialFinalPreparedHandoffTailTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialFinalPreparedHandoffTailTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialFinalPreparedHandoffTailTest.php`
  - `1 test files, 41 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartial*Test.php`
  - `133 test files, 7539 assertions, 0 failures`
- `git diff --check -- lanes/libsqlite`
  - passed

Dependency closure: no new support component needed; this is a focused
consolidation of test helper naming around the existing canonical planner STAT4
implementation.
