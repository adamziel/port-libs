# real-upstream-corpus-btree-index-dynamic-whereE-20260531T034053Z

- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/whereE.test`.
- Ported sections: `whereE-1.1` through `whereE-1.4`.
- Coverage added: 1,000 dynamic PHP TestRunner cases plus source-range, invalid-size, and dependency-closure checks for ALTER TABLE materialized join columns, ANALYZE stability, reversed FROM clause planning, and unique `(z,x)` index probes.
- Focused verification: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBtreeWhereEAlterPlannerDynamicTest.php` passed with `1 test files, 20008 assertions, 0 failures`.
- Dependency closure: no new support component needed; the slice reuses lane-local B-tree/index dynamic corpus planning, ALTER TABLE materialized-column metadata, ANALYZE statistics, and composite index join-plan helpers.
- Non-overlap: this targets `whereE.test` join planner behavior, not accepted B-tree page move/root-collapse/overflow release, indexA/index6/index8/index9, autoindex, or bestindex dynamic corpus clusters.
