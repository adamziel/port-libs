# Real Upstream Corpus Pager/WAL Dynamic Batch

- Slice: `real-upstream-corpus-pager-wal-dynamic-20260530T171139Z-0`
- Base accepted HEAD: `6a6cf1aff10d18a35ed78eace2a787cb40f2b02d`
- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/wal.test` and `/home/claude/port-libs/.upstream-cache/libsqlite/test/pager1.test`
- Ported scenarios: `wal-0.1`, `wal-1.0..1.5`, `wal-2.1..2.6`, `wal-3.1..3.3`, `wal-4.1..4.4.6`, plus pager hot-journal/savepoint invariants that overlap those WAL rollback and checkpoint boundaries.
- Focused PHP coverage: `SQLiteRealUpstreamPagerWalDynamicCorpusTest.php` now passes 3,801 TestRunner assertions over WAL frame sizing, MVCC reader snapshot stability, rollback tail exclusion, checkpoint restart/truncate behavior, checksum recovery boundaries, and savepoint WAL byte truncation across 512/1024/2048/4096 byte page sizes. The pre-existing file had 108 assertions, so the honest new PASS delta is +3,693.
- Non-overlap: this is a real upstream corpus behavior batch, not runner metadata admission. It does not repeat accepted WAL hot-journal checkpoint helper, VFS rollback/commit/sync writer helpers, savepoint byte truncation helper tests, or dashboard-only pass inflation.
- Dependency closure: no new support component is needed; the batch reuses existing native PHP WAL parser, checkpoint, reader snapshot, recovery, and savepoint stack primitives.
- Expected dashboard movement: `phpPass` +3693, from `206333` to `210026`, with mapped denominator unchanged.
