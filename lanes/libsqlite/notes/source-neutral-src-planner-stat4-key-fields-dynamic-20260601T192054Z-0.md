# Source-neutral STAT4 tenant proof fields

- Session: `port-dev-sqlite-yield-dyn-neutral-stat4-20260601T192054Z`.
- Micro-slice: `source-neutral-src-planner-stat4-key-fields-dynamic-20260601T192054Z-0`.
- Base accepted HEAD: `a47f9d5c757d4d8a0ef8510a5f69d964a61ae992`.
- Scope: tightened the STAT4 expression-partial key-field source-neutral proof rows for next244 window fences and boundary-peer fences. The current proof rows keep the existing generic `tenantId` value and now also expose the resolved dynamic `tenantColumn` and `tenantValue`, matching the earlier `keyColumn`/`keyValue` neutralization.
- Behavior preserved: next244 LIMIT/OFFSET window ordering, stat4BoundaryPeer peer grouping, proof signature propagation, and the broader STAT4 expression-partial family still pass. The source-neutral guard now proves generic `tenant_id` metadata and verifies old fixed `keyName`/`blogId` proof keys are absent.
- Focused evidence:
  - `php -l lanes/libsqlite/src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php` -> no syntax errors.
  - `php -l lanes/libsqlite/tests/SQLiteSourceNeutralPlannerStat4KeyFieldsDynamicTest.php` -> no syntax errors.
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteSourceNeutralPlannerStat4KeyFieldsDynamicTest.php` -> 1 file, 52 assertions, 0 failures.
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext244Test.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialStat4BoundaryPeerFenceTest.php` -> 2 files, 127 assertions, 0 failures.
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartial*Test.php lanes/libsqlite/tests/SQLiteSourceNeutralPlannerStat4KeyFieldsDynamicTest.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` -> 136 files, 7656 assertions, 0 failures.
  - `rg -n "wp_options|wp_sitemeta|wp_|blog_id|blogId|BlogId|option_id|option_name|option_value|optionName|optionValue|optionId|Autoload|autoload|keyName|blogId" lanes/libsqlite/src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php` -> no matches.
  - `git diff --check -- lanes/libsqlite` -> pass.
- Dependency closure: no new support component needed; this reuses the existing dynamic STAT4 key-field metadata and tenant-key extraction helper.
- Lane status: no `phpPass` or mapped-coverage counter movement; this is production-source/observable-internal neutralization.
- Root harness: not run - isolated micro-slice.
