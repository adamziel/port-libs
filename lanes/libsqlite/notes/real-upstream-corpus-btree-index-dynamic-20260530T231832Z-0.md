# Real Upstream Corpus B-tree/Index Dynamic

- Base accepted HEAD: `97bde16e3221376c9c3d6c7f9b2330b164322c56`.
- Upstream source: `/home/claude/port-libs/.upstream-cache/libsqlite/test/index2.test`.
- Owned upstream scenarios: `index2-1.1` through `index2-2.2`.
- Added focused coverage: `SQLiteBTreeIndexDynamicCorpusIndex2Test.php` with 1000 dynamic behavior cases plus source-range, invalid-batch, and dependency-closure checks.
- Focused result: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeIndexDynamicCorpusIndex2Test.php` passed with `1 test files, 19995 assertions, 0 failures` and 1003 PASS lines.
- Family result: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeIndexDynamicCorpusIndex2Test.php lanes/libsqlite/tests/SQLiteBTreeIndexDynamicCorpusPlanTest.php` passed with `2 test files, 85160 assertions, 0 failures`.
- Non-overlap: this targets wide-column index construction and ORDER BY prefix result stability from `index2.test`; it does not repeat accepted B-tree page relocation, overflow freeblock/freelist release, index3 unique rollback, index4/index5/index7/index9/indexA/indexexpr dynamic batches, or WordPress-shaped APIs.
- Dependency closure: no new support component needed; reuses lane-local B-tree/index dynamic corpus planner, wide-column index ordering, aggregate column, and order-prefix helpers.
