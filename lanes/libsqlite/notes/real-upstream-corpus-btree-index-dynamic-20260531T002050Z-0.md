# real-upstream-corpus-btree-index-dynamic-20260531T002050Z-0

- Base accepted HEAD: `aab498f11db56174605363e36ca7a662eb3a6384`.
- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/index3.test`, scenarios `index3-1.1` through `index3-1.4`.
- Added focused PHP coverage: `SQLiteRealUpstreamBtreeIndex3UniqueRollbackMatrixDynamicTest.php`, with 1000 dynamic upstream-anchored CREATE UNIQUE INDEX rollback cases plus source-range, invalid-batch, and dependency-closure checks.
- Non-overlap: this targets `index3.test` transactional UNIQUE index creation failure and failed-index residue cleanup. It does not repeat accepted B-tree page move, overflow freelist release, index late lifecycle/affinity, index catalog lifecycle, index4/index5 build, indexedby planner enforcement, JSON, WAL, VFS, or WordPress-shaped API coverage.
- Dependency closure: no new support component needed; reuses lane-local B-tree/index dynamic corpus planner, UNIQUE duplicate detection, transaction commit preservation, schema residue, and integrity-result helpers.
- Root harness: not run - isolated micro-slice.
