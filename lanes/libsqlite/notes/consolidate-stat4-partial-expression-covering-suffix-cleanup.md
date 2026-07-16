STAT4 partial expression covering suffix cleanup

- Consolidated `SQLitePlannerStat4PartialExpressionCoveringCurrentSourceNextPlan::materializeNext118()` to `materialize()` and removed the `Next118` suffix from its private production helpers.
- Migrated the direct focused test and Application smoke filenames/call sites to stable descriptive names.
- Preserved observable STAT4 handoff metadata by keeping the accepted `next958973` final-prepared aliases in the canonical final prepared handoff implementation.
- Dependency closure: no new support component needed; this reuses existing STAT4 expression-index and final prepared handoff planner primitives.
- Non-overlap: cleanup only; it avoids changing STAT4 planning behavior and preserves dependency strings, handoff keys, action labels, and non-overlap text required by the affected domain tests.

Verification:

- `php -l lanes/libsqlite/src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php && php -l lanes/libsqlite/src/SQLitePlannerStat4PartialExpressionCoveringCurrentSourceNextPlan.php && php -l lanes/libsqlite/tests/SQLitePlannerStat4PartialExpressionCoveringCurrentSourceTest.php && php -l lanes/libsqlite/examples/application-stat4-partial-expression-covering-current-source.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4PartialExpressionCoveringCurrentSourceTest.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialFinalPreparedHandoffTest.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialFinalPreparedHandoffTailTest.php` -> 3 files / 142 assertions / 0 failures.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4*Test.php` -> 162 files / 9259 assertions / 0 failures.
- `php lanes/libsqlite/examples/application-stat4-partial-expression-covering-current-source.php --self-test`
- `git diff --check -- lanes/libsqlite`
