2026-05-29 consolidate-final-numbered-methods-planner-stat4-ninth-pass

- Consolidated `SQLitePlannerPartialRangeCoveringCurrentSourceNextPlan` by renaming the numbered public entrypoint `materializeNext136()` to `materializePartialRangeCovering()`.
- Removed the matching `Next136` suffixes from the file's private production helpers and migrated the direct focused test and WordPress smoke to stable unsuffixed paths.
- Dependency closure: no new support component needed; the slice only renames existing native planner consolidation code and preserves the focused partial-range covering behavior.
- Verification:
  - `php -l lanes/libsqlite/src/SQLitePlannerPartialRangeCoveringCurrentSourceNextPlan.php`
  - `php -l lanes/libsqlite/tests/SQLitePlannerPartialRangeCoveringCurrentSourceTest.php`
  - `php -l lanes/libsqlite/examples/wordpress-planner-partial-range-covering-current-source.php`
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerPartialRangeCoveringCurrentSourceTest.php` => `1 test files, 60 assertions, 0 failures`
  - `php lanes/libsqlite/examples/wordpress-planner-partial-range-covering-current-source.php --self-test`
