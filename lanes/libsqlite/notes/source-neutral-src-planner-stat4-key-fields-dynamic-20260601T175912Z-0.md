# Source-neutral STAT4 key-field proof cleanup

- Session: `port-dev-sqlite-yield-dyn-neutral-stat4-20260601T175912Z`.
- Micro-slice: `source-neutral-src-planner-stat4-key-fields-dynamic-20260601T175912Z-0`.
- Base accepted HEAD: `8c95fb5eb5c88b1afad15ed63cfd1fa69585122d`.
- Scope: neutralized the remaining fixed `keyName` proof slots in late STAT4 expression-partial current-source key-field fences. `windowFenceCurrentSourceLimitOffsetWindowValidation()` and `boundaryPeerFenceStat4BoundaryPeer()` now expose the selected dynamic key field as `keyColumn` and the current row value as `keyValue`.
- Behavior preserved: next244/stat4BoundaryPeer ordering, peer grouping, proof signatures, and downstream next250/next251 fences still pass. The source-neutral guard now proves generic `key_name` metadata and verifies the old fixed proof key is absent.
- Focused evidence:
  - `php -l lanes/libsqlite/src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php` -> no syntax errors.
  - `php -l lanes/libsqlite/tests/SQLiteSourceNeutralPlannerStat4KeyFieldsDynamicTest.php` -> no syntax errors.
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteSourceNeutralPlannerStat4KeyFieldsDynamicTest.php` -> 1 file, 46 assertions, 0 failures.
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext244Test.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialStat4BoundaryPeerFenceTest.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentPartialPredicateFenceTest.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentCoveringPayloadFenceTest.php` -> 4 files, 272 assertions, 0 failures.
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartial*Test.php lanes/libsqlite/tests/SQLiteSourceNeutralPlannerStat4KeyFieldsDynamicTest.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` -> 136 files, 7649 assertions, 0 failures.
- Dependency closure: no new support component needed; this reuses the existing STAT4 key-field metadata and native expression-key helpers.
- Lane status: no `phpPass` or mapped-coverage counter movement; this is production-source/observable-internal neutralization.
- Root harness: not run - isolated micro-slice.
