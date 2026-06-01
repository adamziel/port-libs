# Source-Neutral Planner STAT4 Key Fields Dynamic

- Slice: `source-neutral-src-planner-stat4-key-fields-dynamic-20260601T103217Z-0`
- Base accepted HEAD: `25bfd8b5291a9dba8331a5a3b17363ea2ce51f4a`
- Production change: `SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeCurrentSourcePayloadWindowFence()` now derives the provenance key column from STAT4 index metadata instead of emitting a hardcoded domain-shaped key field.
- Direct behavior: `currentSourceRowProvenance` now reports generic `keyColumn` and `keyValue` fields while preserving source, sample key, anchor, and rowid provenance.
- Dependency closure: no new support component needed; this reuses the existing STAT4 key-field metadata helper.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteSourceNeutralPlannerStat4KeyFieldsDynamicTest.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext185Test.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext188Test.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext189Test.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialPayloadExpressionFenceTest.php` -> 5 test files, 282 assertions, 0 failures.
- `php lanes/libsqlite/examples/application-sqlplanner-stat4-expression-partial-current-source-next185.php --self-test` -> self-test passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` -> 1 test file, 5 assertions, 0 failures.
- `php -l` passed for changed PHP files.
- `git diff --check -- lanes/libsqlite` passed.

Root harness: not run - isolated micro-slice.
