# Consolidate Final Numbered Planner STAT4 Dynamic

Consolidated the final planner STAT4 dynamic handoff surface by adding stable
canonical fence-key proof fields for both `stat4FinalPreparedHandoffFence` and
`stat4TerminalPreparedHandoffFence` while preserving the existing numbered
`stat4FinalPreparedHandoffPreparationFence`, `stat4Next958973PreparationFence`,
and `next958973Prepared` observable keys for dependent tests and handoff
consumers.

Verification:

- `php -l lanes/libsqlite/src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialFinalPreparedHandoffTest.php`
- `php -l lanes/libsqlite/examples/application-sqlplanner-stat4-expression-partial-final-prepared-handoff.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialFinalPreparedHandoffTest.php`
  - `1 test files, 48 assertions, 0 failures`
- `php tools/run-tests.php $(printf '%s\n' lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartial*Test.php | sort)`
  - `134 test files, 7594 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-sqlplanner-stat4-expression-partial-final-prepared-handoff.php --self-test`

Dependency closure: no new support component needed; this reuses the existing
planner STAT4 expression partial handoff implementation and only adds stable
canonical aliases for the final dynamic proof.

Non-overlap: this is consolidation-only for the final planner STAT4 prepared
handoff alias surface. It does not change STAT4 range costing, expression
ORDER BY, JSON table, WAL/VFS, B-tree, trigger, PRAGMA, compound SELECT, or UTF
behavior.
