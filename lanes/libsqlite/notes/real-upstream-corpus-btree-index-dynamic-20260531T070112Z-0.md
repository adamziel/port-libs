# real-upstream-corpus-btree-index-dynamic-20260531T070112Z-0

Implemented a non-overlapping real upstream B-tree/index dynamic corpus batch from the hydrated SQLite checkout.

- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/delete.test`.
- Owned upstream sections: `delete-3.1.1` through `delete-6.11`.
- Focused PHP behavior: `SQLiteBTreeIndexDynamicCorpusPlan::deleteIndexedRowListDynamicCases(1200)` plus `SQLiteRealUpstreamDeleteIndexedRowListDynamicTest.php`.
- Coverage shape: 1,200 distinct dynamic TestRunner cases plus source-range, empty-batch, and dependency-closure guards.
- Behavior covered: indexed equality DELETE hit/miss, `PRAGMA count_changes` result shape, delete-all table/index drain, repeated point deletes, range deletes, survivor key order, 3,000-row row-list overflow deletes, and empty btree reuse after full delete.
- Non-overlap: avoids accepted B-tree page relocation, root collapse, overflow freelist/freeblock release, index-interior merge, `index.test` duplicate-key/lifecycle/affinity batches, `index2`, `index3`, `index4`, `index5`, `index6`, `index7`, `index8`, `index9`, `indexA`, `indexedby`, `bestindex`, `indexfault`, `indexexpr`, JSON, WAL, VFS, PRAGMA, and source-neutral cleanup clusters.

Verification:

- `php -l lanes/libsqlite/src/SQLiteBTreeIndexDynamicCorpusPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamDeleteIndexedRowListDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamDeleteIndexedRowListDynamicTest.php`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component is needed. This reuses the lane-local B-tree/index dynamic corpus planner and models real upstream DELETE row-list/index survivor semantics.
