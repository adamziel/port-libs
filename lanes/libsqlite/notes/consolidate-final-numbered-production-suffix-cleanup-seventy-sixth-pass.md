# Consolidate Final Numbered Production Suffix Cleanup Seventy-Sixth Pass

Consolidated the planner covering range-order current-source numbered helper chain into descriptive unsuffixed helpers on `SQLitePlannerCoveringRangeOrderCurrentSourceNextPlan`.

Changed surfaces:

- The numbered public materializer is now `materializeCoveringRangeOrderCurrentSource()`.
- Private production helpers in that class no longer carry worker-number suffixes.
- `SQLitePlannerCoveringStat4RangeCurrentSourceNextPlan` now calls the stable entry point.
- The direct focused test and WordPress example were renamed away from numbered current-source filenames.

Verification:

- `php -l lanes/libsqlite/src/SQLitePlannerCoveringRangeOrderCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/src/SQLitePlannerCoveringStat4RangeCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePlannerCoveringRangeOrderCurrentSourceTest.php`
- `php -l lanes/libsqlite/examples/wordpress-planner-covering-range-order-current-source.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerCoveringRangeOrderCurrentSourceTest.php`
  - `1 test files, 77 assertions, 0 failures`
- `php lanes/libsqlite/examples/wordpress-planner-covering-range-order-current-source.php --self-test`
  - `wordpress-planner-covering-range-order-current-source self-test passed`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component needed; this is a suffix consolidation over existing planner and CREATE INDEX parsing behavior.

Non-overlap: this cleanup only removes a numbered helper/test/example surface from the planner covering range-order family and does not change accepted functional behavior or inflate `phpPass`.
