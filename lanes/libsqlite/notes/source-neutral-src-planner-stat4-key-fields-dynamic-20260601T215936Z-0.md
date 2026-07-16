# Source-neutral STAT4 selected key fields

- Session: `port-dev-sqlite-yield-dyn-neutral-stat4-20260601T215936Z`.
- Micro-slice: `source-neutral-src-planner-stat4-key-fields-dynamic-20260601T215936Z-0`.
- Base accepted HEAD: `7b6b747e54eb6630d159571cf9785d0872a67c29`.
- Scope: tightened `SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan` selected-index key-field lookup so a named STAT4 handoff index must be present before dynamic key fields are read. The helper no longer falls back to an unrelated selected or first index when the selected plan name is stale.
- Behavior preserved: prepared handoff key-field lookup still derives generic `key_name` metadata from `stat4KeyFields`, selected indexes, and expression metadata. The broad STAT4 expression-partial family still passes.
- Focused evidence:
  - `php -l lanes/libsqlite/src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php` -> no syntax errors.
  - `php -l lanes/libsqlite/tests/SQLiteSourceNeutralPlannerStat4KeyFieldsDynamicTest.php` -> no syntax errors.
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteSourceNeutralPlannerStat4KeyFieldsDynamicTest.php` -> 1 file, 53 assertions, 0 failures.
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartial*Test.php lanes/libsqlite/tests/SQLiteSourceNeutralPlannerStat4KeyFieldsDynamicTest.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` -> 136 files, 7657 assertions, 0 failures.
- Dependency closure: no new support component needed; this reuses existing STAT4 index metadata and source-neutral key-field helpers.
- Lane status: no `phpPass` or mapped-coverage movement; this is a source-neutral production-helper hardening slice.
- Root harness: not run - isolated micro-slice.
