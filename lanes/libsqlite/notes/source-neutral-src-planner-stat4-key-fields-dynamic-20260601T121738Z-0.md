# Source-Neutral STAT4 Key Fields Dynamic Slice

Session: `port-dev-sqlite-yield-dyn-neutral-stat4-20260601T121738Z`
Micro-slice: `source-neutral-src-planner-stat4-key-fields-dynamic-20260601T121738Z-0`
Base accepted HEAD: `104a9f5fce0ab0f0e77688b3f9277242f2f9e31c`

## Change

- Updated `SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan` prepared-handoff window helpers so `expressionKey` values derive from selected STAT4 key-field metadata instead of a hardcoded option-table key field.
- Updated the current STAT4 payload fence to compare current row keys through the same key-column metadata path.
- Extended `SQLiteSourceNeutralPlannerStat4KeyFieldsDynamicTest` to guard the prepared-handoff window helper source and prove generic `key_name` behavior for payload fences and reusable handoff ranges.

## Verification

- `php -l lanes/libsqlite/src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteSourceNeutralPlannerStat4KeyFieldsDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteSourceNeutralPlannerStat4KeyFieldsDynamicTest.php`
  - Result: `1 test files, 27 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - Result: `1 test files, 6 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartial*Test.php lanes/libsqlite/tests/SQLiteSourceNeutralPlannerStat4KeyFieldsDynamicTest.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - Result: `136 test files, 7629 assertions, 0 failures`

## Dependency Closure

No new support component is needed. The slice reuses existing STAT4 index metadata and source-neutral key-field helpers.

## Non-Overlap

This source-neutral cleanup avoids throughput clusters for JSON, WAL, VFS, B-tree, PRAGMA, expression ORDER BY, range-cost planning, and upstream-suite admission. It only removes legacy key-field assumptions from STAT4 expression-partial prepared-handoff internals and their direct guard test.

Root harness: not run - isolated micro-slice.
