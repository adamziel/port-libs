# STAT4 Sample Fence Method Consolidation

Consolidated four remaining numbered production entry/helper groups in
`SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan` into descriptive
methods:

- `materializeLikePrefixWindowFence()`
- `materializeStat4SampleOrderFence()`
- `materializeStat4SampleRangeWindowFence()`
- `materializeStat4SamplePartialPredicateFence()`

Direct STAT4 tests and Application examples were renamed to descriptive filenames
and migrated to the canonical method names. Existing status keys, dependency
strings, proof fields, and detail text were preserved so accepted evidence that
asserts those observable values remains stable.

Verification:

- `php -l lanes/libsqlite/src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php`
- `php -l` for the four renamed direct tests and four renamed examples
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialLikePrefixWindowFenceTest.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialStat4SampleOrderFenceTest.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialStat4SampleRangeWindowFenceTest.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialStat4SamplePartialPredicateFenceTest.php`
  - `4 test files, 280 assertions, 0 failures`
- `php tools/run-tests.php $(find lanes/libsqlite/tests -maxdepth 1 -name 'SQLitePlannerStat4ExpressionPartial*Test.php' | sort)`
  - `133 test files, 7547 assertions, 0 failures`
- renamed Application examples with `--self-test`

Dependency closure: no new support component needed; this is consolidation-only
and reuses the existing STAT4 expression partial planner implementation.

Non-overlap: avoids changing behavior for accepted STAT4 status/provenance keys,
range-cost work, expression ORDER BY, JSON, WAL, VFS, B-tree, trigger, and UTF
clusters.
