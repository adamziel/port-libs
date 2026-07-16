# Source-neutral STAT4 key-field cleanup follow-up

- Base accepted HEAD: `f14b805f8bba2ce3c9267da7d424f3116ed98e7e`.
- Scope: removed the remaining hardcoded `plugin_%` LIKE branches from late `SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan` STAT4 current-source helpers and routed them through one source-neutral SQLite LIKE matcher. Also neutralized a STAT4 range-order `option-name` non-overlap string to `key-name`.
- Behavior preserved: existing STAT4 planner fixtures still pass, and the source-neutral guard now checks the changed LIKE helpers for generic `%` and `_` wildcard semantics using `module_%` sample data.
- Focused evidence:
  - `php -l lanes/libsqlite/src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php` -> no syntax errors.
  - `php -l lanes/libsqlite/src/SQLiteStat4RangeOrderCurrentSourceNextPlan.php` -> no syntax errors.
  - `php -l lanes/libsqlite/tests/SQLiteSourceNeutralPlannerStat4KeyFieldsDynamicTest.php` -> no syntax errors.
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteSourceNeutralPlannerStat4KeyFieldsDynamicTest.php` -> 1 file, 39 assertions, 0 failures.
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext230Test.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext233Test.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext236Test.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext242Test.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialStat4SampleAnchorFenceTest.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext249Test.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentScanDirectionFenceTest.php lanes/libsqlite/tests/SQLitePlannerRangeOrderStat4CursorTapeCurrentSourceTest.php` -> 8 files, 563 assertions, 0 failures.
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartial*Test.php lanes/libsqlite/tests/SQLiteSourceNeutralPlannerStat4KeyFieldsDynamicTest.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` -> 136 files, 7642 assertions, 0 failures.
  - `git diff --check -- lanes/libsqlite` -> pass.
- Dependency closure: no new support component needed; this reuses the existing native `SQLiteDatabase::likeMatches()` implementation for STAT4 source-neutral LIKE checks.
- Lane status: no `phpPass` or mapped-coverage counter movement; this is a production-source neutralization slice.
