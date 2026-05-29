# Planner STAT4 Numbered Method Consolidation Fifteenth Pass

Consolidated the remaining `Next124` method/helper names in
`SQLitePlannerStat4PartialRangeCurrentSourceNextPlan` into stable canonical
entrypoints:

- the numbered public entrypoint is now `compare()`.
- Private helpers such as the numbered private helpers now use unsuffixed descriptive names.
- The direct focused test and WordPress example were renamed to unsuffixed
  files and migrated to the canonical method.

Verification:

- `php -l lanes/libsqlite/src/SQLitePlannerStat4PartialRangeCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePlannerStat4PartialRangeCurrentSourceNextTest.php`
- `php -l lanes/libsqlite/examples/wordpress-stat4-partial-range-current-source-next.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4PartialRangeCurrentSourceNextTest.php`
- `php lanes/libsqlite/examples/wordpress-stat4-partial-range-current-source-next.php --self-test`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component is needed; this is a production
method-name consolidation that preserves the existing planner behavior.
