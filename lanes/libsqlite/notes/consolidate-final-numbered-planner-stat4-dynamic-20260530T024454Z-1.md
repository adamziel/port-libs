# consolidate-final-numbered-planner-stat4-dynamic-20260530T024454Z-1

Scope: planner STAT4 expression-partial consolidation only.

Change:

- Renamed the production entrypoint `materializeNext172()` to
  `materializeCurrentSourceRangeRefresh()`.
- Renamed the private helper family for that current-source range refresh away
  from `Next172` suffixes.
- Renamed the direct focused test and WordPress smoke to stable descriptive
  filenames and migrated their production calls.

Observable result metadata intentionally remains unchanged: `next172` status
strings, dependency labels, detail text, exception wording, result array keys,
test case names, and sample/current-next proof keys are preserved.

Verification:

- `php -l lanes/libsqlite/src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php`
  passed.
- `php -l lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceRangeRefreshTest.php`
  passed.
- `php -l lanes/libsqlite/examples/wordpress-planner-stat4-expression-partial-current-source-range-refresh.php`
  passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceRangeRefreshTest.php`
  passed: 1 file / 63 assertions / 0 failures.
- `php lanes/libsqlite/examples/wordpress-planner-stat4-expression-partial-current-source-range-refresh.php --self-test`
  passed: printed `wordpress-planner-stat4-expression-partial-current-source-range-refresh self-test passed`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartial*Test.php`
  passed: 133 files / 7543 assertions / 0 failures.
- `git diff --check -- lanes/libsqlite` passed.

Dependency closure: no new support component is needed; this is a production
identifier consolidation over the existing native STAT4 expression-partial
current-source range refresh implementation.

Non-overlap: this cleanup only touches the planner STAT4 current-source range
refresh entrypoint/helper family and its direct test/smoke. It avoids pager,
WAL/VFS, B-tree, JSON, compound SELECT, trigger, PRAGMA, and UTF behavior
clusters.
