# Source-Neutral STAT4 Key Fields Dynamic Slice

Session: `port-dev-sqlite-yield-dyn-neutral-stat4-20260601T065315Z`
Micro-slice: `source-neutral-src-planner-stat4-key-fields-dynamic-20260601T065315Z-0`
Base accepted HEAD: `cc9294ac19877407e3f202dbdfd54b6a9a8fb67d`

## Change

- Added a generic STAT4 expression/key-field evaluator for lower/upper/length/substr/json_extract expressions over caller-provided row columns.
- Replaced the next batch of hardcoded planner key-field internals in `SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan`:
  covering reprepare display fields, OR-split expression values, partial expression values, LIKE-prefix values, cost-fence covering columns, relevant-row churn canonical rows, unsampled bracket values, duplicate-fanout selected expression fallback, BETWEEN expression normalization, and ORDER-fence defaults/values.
- Extended `SQLiteSourceNeutralPlannerStat4KeyFieldsDynamicTest` to guard those helper methods and prove generic `key_name` / `key_value` expression behavior.

## Verification

- `php -l lanes/libsqlite/src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteSourceNeutralPlannerStat4KeyFieldsDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteSourceNeutralPlannerStat4KeyFieldsDynamicTest.php`
  - Result: `1 test files, 9 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceCoveringReprepareTest.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceCostFenceTest.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialRelevantRowChurnTest.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialDuplicateSampleFanoutTest.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialBetweenRangeFenceTest.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialLikePrefixWindowFenceTest.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialOrderFenceTest.php`
  - Result: `7 test files, 437 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartial*Test.php lanes/libsqlite/tests/SQLiteSourceNeutralPlannerStat4KeyFieldsDynamicTest.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - Result: `136 test files, 7609 assertions, 0 failures`

## Dependency Closure

No new support component is needed. The slice reuses the existing STAT4 planner source structures and only changes how owned helpers derive values from caller-supplied expression/index metadata.

## Non-Overlap

This source-neutral cleanup avoids accepted throughput clusters for JSON, WAL, VFS, B-tree, PRAGMA, expression ORDER BY, range-cost planning, and upstream-suite admission. It only touches STAT4 expression-partial key-field internals and the direct source-neutral guard.
