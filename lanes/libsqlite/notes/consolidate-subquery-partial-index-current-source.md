2026-05-29 consolidation suffix cleanup: subquery partial-index planner

- Removed the generated numbered production method/helper suffixes from
  `SQLitePlannerSubqueryPartialIndexCurrentSourceNextPlan`.
- Migrated the direct focused test and WordPress smoke to stable unsuffixed
  filenames and the canonical `materializeSubqueryPartialIndexPlan()` entry
  point.
- Focused verification:
  - `php -l lanes/libsqlite/src/SQLitePlannerSubqueryPartialIndexCurrentSourceNextPlan.php`
  - `php -l lanes/libsqlite/tests/SQLitePlannerSubqueryPartialIndexCurrentSourceTest.php`
  - `php -l lanes/libsqlite/examples/wordpress-planner-subquery-partial-index-current-source.php`
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerSubqueryPartialIndexCurrentSourceTest.php`
    passed with `1 test files, 64 assertions, 0 failures`.
  - `php lanes/libsqlite/examples/wordpress-planner-subquery-partial-index-current-source.php --self-test`
    passed.
- Dependency closure: no new support component needed; this is a production
  naming consolidation only.
- Dashboard counters unchanged; this slice removes generated production
  suffixes without adding new upstream behavior coverage.
