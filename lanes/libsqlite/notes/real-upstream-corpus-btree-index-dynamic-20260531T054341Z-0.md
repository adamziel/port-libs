# real-upstream-corpus-btree-index-dynamic-20260531T054341Z-0

Status: ready for integration.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/reindex.test`

Owned upstream sections:

- `reindex-1.1` through `reindex-1.9`: whole-database, table-target,
  index-target, schema-qualified, and unknown-target REINDEX behavior.
- `reindex-2.1` through `reindex-2.8.1`: custom collation order, stale
  changed-collation index state, wrong-target no-op behavior, and matching
  collation REINDEX repair with integrity restored.

Focused growth:

- Added `SQLiteBTreeIndexDynamicCorpusPlan::reindexCollationRepairCases(1000)`.
- Added `SQLiteRealUpstreamBtreeReindexDynamicTest.php`.
- Focused result: `1 test files, 15169 assertions, 0 failures`, with 1003
  TestRunner PASS lines.

Non-overlap:

- This owns upstream `reindex.test` B-tree/index collation-repair behavior.
- It does not repeat accepted index build, `index2`, `index3`, `index4`,
  `index5`, `index6`, `index7`, `index8`, `index9`, `indexA`, `indexexpr`,
  `indexedby`, `indexfault`, `numindex1`, `autoindex*`, `bestindex*`,
  `whereK/L/M/N`, B-tree page relocation, root collapse, overflow freelist,
  bulk freeblock, VFS/WAL, JSON, PRAGMA, or metadata-only runner admission
  batches.

Dependency closure:

- No new support component is needed. This reuses lane-local B-tree/index
  dynamic corpus planner fixtures for collation order, REINDEX target
  resolution, and integrity-state modeling.

Verification:

- `php -l lanes/libsqlite/src/SQLiteBTreeIndexDynamicCorpusPlan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteBTreeIndexDynamicCorpusPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamBtreeReindexDynamicTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamBtreeReindexDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBtreeReindexDynamicTest.php`
  - `1 test files, 15169 assertions, 0 failures`
