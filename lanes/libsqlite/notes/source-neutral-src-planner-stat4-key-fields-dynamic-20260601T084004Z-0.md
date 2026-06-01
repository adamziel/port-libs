# Source-neutral STAT4 key-field cleanup

- Base accepted HEAD: `56d05df2fec029b5e619e6a16107a698092a4221`.
- Scope: neutralized late `SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan` current-source STAT4 helpers for covering payloads, partial estimates, residual WHERE, sample tape, duplicate cardinality, limit/offset window, boundary peers, duplicate runs, and current partial predicates.
- Behavior preserved: existing fixture-backed STAT4 planner tests still pass; hardcoded key/tenant internals now route through generic STAT4 key metadata (`keyColumn`, `tenantColumn`) and source-neutral expression helpers.
- Additional cleanup: replaced a hardcoded LIKE-prefix branch with generic SQL LIKE wildcard matching for `%` and `_`.
- Focused evidence:
  - `php -l lanes/libsqlite/src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php` -> no syntax errors.
  - `php -l lanes/libsqlite/tests/SQLiteSourceNeutralPlannerStat4KeyFieldsDynamicTest.php` -> no syntax errors.
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteSourceNeutralPlannerStat4KeyFieldsDynamicTest.php` -> 1 file, 19 assertions, 0 failures.
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext238Test.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext239Test.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext241Test.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext243Test.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext244Test.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext246Test.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialStat4BoundaryPeerFenceTest.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext248Test.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentPartialPredicateFenceTest.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` -> 10 files, 605 assertions, 0 failures.
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartial*Test.php lanes/libsqlite/tests/SQLiteSourceNeutralPlannerStat4KeyFieldsDynamicTest.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` -> 136 files, 7619 assertions, 0 failures.
  - `git diff --check -- lanes/libsqlite` -> pass.
- Dependency closure: no new support component needed; this reuses existing STAT4 key-field metadata and source-neutral expression helpers.
- Lane status: no `phpPass` or mapped-coverage counter movement; this is a source-neutral production cleanup slice.
