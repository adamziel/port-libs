# Planner STAT4 final-tail method consolidation

This consolidation pass removes the final numbered production wrapper trio
`materializeNext974989()`, `handoffFenceNext974989()`, and
`cursorProgramNext974989()` from
`SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan`.

The stable `materializeFinalPreparedHandoff()` entrypoint now consumes the
accepted `next958-973` preparation fence directly. Direct planner coverage was
migrated to the stable final prepared handoff test/example names while keeping
the same projected-row continuity assertions.

Verification:

- `php -l lanes/libsqlite/src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialFinalPreparedHandoffTest.php`
- `php -l lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialFinalPreparedHandoffTailTest.php`
- `php -l lanes/libsqlite/examples/application-sqlplanner-stat4-expression-partial-final-prepared-handoff.php`
- `php -l lanes/libsqlite/examples/application-sqlplanner-stat4-expression-partial-final-prepared-handoff-tail.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialFinalPreparedHandoffTest.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialFinalPreparedHandoffTailTest.php`
  - `2 test files, 78 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-sqlplanner-stat4-expression-partial-final-prepared-handoff.php --self-test`
- `php lanes/libsqlite/examples/application-sqlplanner-stat4-expression-partial-final-prepared-handoff-tail.php --self-test`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component is needed; this is a production
method-name consolidation over the existing planner STAT4 handoff behavior.
