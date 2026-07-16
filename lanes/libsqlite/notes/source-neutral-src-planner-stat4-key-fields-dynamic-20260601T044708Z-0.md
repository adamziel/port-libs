# Source-Neutral STAT4 Key Fields Dynamic

Slice: `source-neutral-src-planner-stat4-key-fields-dynamic-20260601T044708Z-0`
Base: `5a7dc1daad24ba95a3c58d82c78018bfc7722899`

## Scope

- Updated `SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan` prepared-handoff window helpers to compute `expressionKey` from dynamic STAT4 key metadata instead of a fixed legacy field.
- Updated STAT4 current-range helpers so `lower(<column>)` and `json_extract(<column>, $.path)` expression values read from generic row fields.
- Added a focused source-neutral guard/behavior test for the owned methods and retained the existing next638-653 and next782-797 prepared-handoff behavior tests.

## Verification

- `php -l lanes/libsqlite/src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteSourceNeutralPlannerStat4KeyFieldsDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext638653Test.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialPreparedHandoffThirdContinuationTest.php lanes/libsqlite/tests/SQLiteSourceNeutralPlannerStat4KeyFieldsDynamicTest.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - Result: `4 test files, 92 assertions, 0 failures`

## Dependency Closure

No new support component is needed. The cleanup reuses existing STAT4 expression-column parsing and planner metadata.

## Follow-Up

Older STAT4 helper regions in the same production file still contain historical option/key-value fixture debt. This slice deliberately owns only the prepared-handoff/current-range method group.
