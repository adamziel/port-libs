Micro-slice: real-upstream-corpus-btree-index-dynamic-20260530T200210Z-0
Base accepted HEAD: 688b5b5b02ee30d2a82f4468b5b909f17254ae0e

Upstream source:
- Hydrated SQLite upstream file: /home/claude/port-libs/.upstream-cache/libsqlite/test/index7.test
- Ported sections: index7-1.1, index7-1.10, index7-1.11, index7-1.11b, index7-1.12, index7-1.13, index7-1.14, index7-1.15.

Behavior added:
- Added `SQLiteBTreeIndexDynamicCorpusPlan::index7PartialIndexStatMutationCases()` with 1000 distinct focused cases preserving the upstream partial-index stat transitions for t1a, t1b, and t1c.
- Extended `SQLiteRealUpstreamBTreeIndexDynamicCorpusTest.php` with 1000 real upstream TestRunner PASS cases covering partial-index cardinality after INSERT, ANALYZE, UPDATE, DELETE, REINDEX, and full-index creation.

Non-overlap:
- This does not touch accepted B-tree page move, overflow freelist release, index-interior merge, index expression, index5 write-order, or index7 section-2 WITHOUT ROWID partial-index lookup cases.
- The new source is index7.test section 1 sqlite_stat1/index_list mutation behavior.

Focused evidence:
- `php -l lanes/libsqlite/src/SQLiteBTreeIndexDynamicCorpusPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamBTreeIndexDynamicCorpusTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBTreeIndexDynamicCorpusTest.php`
  - Result: 1 test files, 81560 assertions, 0 failures, 5713 PASS lines.
- New focused PASS-line delta: +1000.
- New behavior assertion delta from added block: +13375.

Dependency closure:
- No new support component needed. This reuses the lane-local B-tree/index corpus plan model and TestRunner.
