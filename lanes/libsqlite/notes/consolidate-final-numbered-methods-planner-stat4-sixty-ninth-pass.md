# Planner STAT4 Numbered Method Consolidation Sixty-Ninth Pass

Consolidated the STAT4 partial-expression range-row stability variant in
`SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan` from
`materializeNext174()` plus private `*Next174()` helpers into the stable
`materializeRangeRows()` entrypoint and descriptive `rangeRows*` helpers.

Direct coverage was migrated from
`SQLitePlannerStat4ExpressionPartialCurrentSourceNext174Test.php` and
`application-planner-stat4-expression-partial-current-source-next174.php` to
stable range-row filenames and API calls. No compatibility shim or numbered
production helper was left for this variant.

Verification:

- `php -l lanes/libsqlite/src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceRangeRowsTest.php`
- `php -l lanes/libsqlite/examples/application-planner-stat4-expression-partial-current-source-range-rows.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceRangeRowsTest.php` -> `1 test files, 67 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-planner-stat4-expression-partial-current-source-range-rows.php --self-test`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component needed; this is a production
method/name consolidation only.
