# Real Upstream Corpus B-tree/Index Dynamic Slice

- Session: `port-dev-sqlite-yield-dyn-real-btree-20260531T053843Z`
- Base accepted HEAD: `4492e9529d6540daf2941a27323f36260b8cf64c`
- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/whereJ.test`
- Owned upstream sections: `whereJ-4.2` and `whereJ-5.1` through `whereJ-5.3`
- Focused PHP coverage: `SQLiteRealUpstreamBtreeWhereJRangeCostDynamicTest.php`
- Focused result: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBtreeWhereJRangeCostDynamicTest.php` passed with `1 test files, 26758 assertions, 0 failures`.
- PASS-line movement: `1003` focused TestRunner PASS cases in the new file.
- Non-overlap: this slice covers `whereJ.test` STAT4/stat1 range-cost and join-order choices. It does not repeat accepted `indexA`, `index8`, `where8`, `where9`, `indexedby`, B-tree page-move, overflow freelist, root-collapse, or VFS/WAL storage clusters.
- Dependency closure: no new support component is needed; the existing PHP corpus plan helper is extended with hydrated upstream planner metadata.
