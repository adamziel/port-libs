# Source-Neutral STAT4 Key Fields Dynamic

## Scope

- Neutralized dynamic STAT4 key and payload derivation in `src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php`.
- Planner fences now derive left-key columns, expression payload keys, and partial-predicate terms from selected index metadata instead of legacy hardcoded application field names.
- Added source-neutral helper coverage for expression keys such as `lower(key_name)` and `length(key_value)` while preserving existing STAT4 planner behavior.

## Verification

- `php -l lanes/libsqlite/src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteSourceNeutralPlannerStat4KeyFieldsDynamicTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteSourceNeutralPlannerStat4KeyFieldsDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteSourceNeutralPlannerStat4KeyFieldsDynamicTest.php`
  - `1 test files, 30 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialPayloadExpressionFenceTest.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext203Test.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext205Test.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext207Test.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext209Test.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext211Test.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext219Test.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialStat4SamplePartialPredicateFenceTest.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext230Test.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext231Test.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext233Test.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext234Test.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext236Test.php`
  - `13 test files, 890 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 6 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartial*Test.php`
  - `134 test files, 7596 assertions, 0 failures`
- Production-file source scan for the targeted legacy tokens returned no matches.
- `git diff --check -- lanes/libsqlite`
  - Passed with no output.

## Handoff

- Root harness: not run - isolated micro-slice.
- Dependency closure: no new support component is needed; this reuses and extends the existing generic STAT4 metadata and source-neutral expression helpers.
- Dashboard counters: not changed; this is a source-neutral cleanup slice, not focused pass-line growth.
