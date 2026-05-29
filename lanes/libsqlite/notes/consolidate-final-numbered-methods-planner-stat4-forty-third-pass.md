# consolidate-final-numbered-methods-planner-stat4-forty-third-pass

Consolidated three remaining STAT4 expression-partial production method/helper families in `SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan` by renaming numbered entrypoints and private helpers to stable behavior names:

- `materializeStat4PartialPredicateDrift`
- `materializeStat4YieldCoveringRows`
- `materializeStat4OrRowidUnion`

The direct tests and WordPress examples now call the stable entrypoints. Legacy returned status strings, dependency labels, scenario names, and test labels are intentionally preserved so the behavior remains unchanged while production method/helper identifiers no longer carry those worker-number suffixes.

Verification:

- `php -l lanes/libsqlite/src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php`: pass.
- `php -l` for the three changed tests and three changed examples: pass.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext155Test.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext159Test.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext160Test.php`: `3 test files, 205 assertions, 0 failures`.
- `php lanes/libsqlite/examples/wordpress-stat4-expression-partial-current-source-next155.php --self-test`: pass.
- `php lanes/libsqlite/examples/wordpress-planner-stat4-expression-partial-current-source-next159.php --self-test`: pass.
- `php lanes/libsqlite/examples/wordpress-planner-stat4-expression-partial-current-source-next160.php --self-test`: pass.
- Exact banned user-named removed suffix scan across `src`, `tests`, `examples`, and `fixtures`: no matches.

Dependency closure: no new support component is needed; this is a production identifier consolidation only and reuses the existing STAT4 expression-partial planner implementation and fixtures.
