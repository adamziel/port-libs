# sqlplanner-stat4-partial-range-current-source-next

Implemented `SQLitePlannerStat4PartialRangeCurrentSourceNextPlan` for a
current-source STAT4 partial-index range edge: when ANALYZE/schema changes
narrow a partial index's range predicate, a stale prepared plan is forced to
reprepare and select the current partial range/root/stat4 source. The plan
reports schema/stat4/index-signature changes, partial range lower/upper deltas,
stale-range admission risk, STAT4 matched sample deltas, selected root page,
and current-source detail.

WordPress path: `wordpress-stat4-partial-range-current-source-next.php`
models copied `wp_options` plugin-option imports where a prepared partial
`option_name >= 'plugin_'` STAT4 index is replaced by a current
`option_name >= 'plugin_cache' AND option_name < 'plugin_seo'` index after
ANALYZE.

Verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4PartialRangeCurrentSourceNextTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 53 assertions, 0 failures

php lanes/libsqlite/examples/wordpress-stat4-partial-range-current-source-next.php --self-test
wordpress-stat4-partial-range-current-source-next self-test passed

php -l lanes/libsqlite/src/SQLitePlannerStat4PartialRangeCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLitePlannerStat4PartialRangeCurrentSourceNextTest.php
php -l lanes/libsqlite/examples/wordpress-stat4-partial-range-current-source-next.php
No syntax errors detected in all changed PHP files.
```

Expected dashboard movement: `phpPass` `49426 -> 49479` from the verified 53
focused PASS lines. Mapped upstream coverage is unchanged because this is
focused PHP behavior over already mapped STAT4/partial-index planner surfaces.

Dependency closure: no new support component is needed. The slice reuses
lane-local `SQLitePartialIndexOrderCurrentSourcePlan`,
`SQLiteMultiColumnRangePlan`, `SQLiteCreateIndex`, and `SQLiteIndexPredicate`.

Non-overlap: avoids accepted STAT4 skip-scan/order current-source, covering
expression STAT4, subquery covering partial indexes, expression-index
range-cost, partial-index order, JSON table, VFS/WAL, B-tree, encoding, and
suite-runner clusters. The new surface is current-source invalidation when the
partial-index range predicate itself changes.
