# real-upstream-corpus-btree-index-dynamic-20260531T042157Z-0

Added a non-overlapping real upstream B-tree/index dynamic corpus batch from:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/where8.test`

Upstream sections ported:

- `where8-3.2`, `where8-3.3`, `where8-3.5`, `where8-3.8`, `where8-3.9`, `where8-3.10`, `where8-3.11`, `where8-3.12`, `where8-3.14`, `where8-3.15`, and `where8-3.21` through `where8-3.23`.
- The new batch targets multi-table OR planning, OR join predicates, parenthesized FROM sources, scalar subquery fallback, search-count status, and temp-sort status.

Focused PHP coverage:

- Added `SQLiteBTreeIndexDynamicCorpusPlan::where8MultiTableOrOptimizationCases(1200)`.
- Added `SQLiteRealUpstreamBtreeWhere8MultiTableDynamicTest.php`.
- Focused test result: `1 test files, 25420 assertions, 0 failures`.
- Focused PASS growth: `1203` TestRunner PASS lines.

Non-overlap:

- This extends the accepted `where8` real upstream corpus beyond the existing single-table `where8-1.*` OR optimization shard.
- It does not repeat accepted B-tree page relocation, root collapse, overflow freelist/freeblock release, index-interior merge, `index8` ORDER BY/LIMIT behavior, `whereK` OR factoring, `whereL/M/N` constant propagation, `whereE` ALTER TABLE join planning, `bestindex*` virtual table batches, JSON, WAL, VFS, PRAGMA, or source-neutral cleanup clusters.
- Mapped denominator coverage remains complete at `1589 / 1589`; this is countable PHP PASS-line growth against already mapped real upstream B-tree/index inventory.

Verification:

- `php -l lanes/libsqlite/src/SQLiteBTreeIndexDynamicCorpusPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamBtreeWhere8MultiTableDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBtreeWhere8MultiTableDynamicTest.php`

Dependency closure:

- No new support component is needed. The slice reuses the existing lane-local B-tree/index dynamic corpus planner and existing OR/join/subquery result metadata helpers.
