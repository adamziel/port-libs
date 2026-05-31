# real-upstream-corpus-vfs-io-dynamic-20260531T055010Z-0

- Base accepted HEAD: `db171f640e25dd929585c8e1b7a1c804219fdfee`.
- Upstream source: `/home/claude/port-libs/.upstream-cache/libsqlite/test/io.test`.
- Ported upstream scenarios: `io-6.1` cache-warm setup and `io-6.2.1`/`io-6.2.2` pager-cache no-spill checks after small commits.
- Added source behavior: `SQLiteVfsIoDynamicPlan::pagerCacheNoSpillAfterWarmReadProfile()` models the upstream invariant that with `PRAGMA mmap_size=0` and a warm pager cache, small post-read transactions must avoid flushing dirty pages; after disk bytes are corrupted, `PRAGMA integrity_check` remains satisfied from cached pages.
- Added focused test file: `SQLiteRealUpstreamCorpusVfsIoCacheNoSpillDynamicTest.php`.
- Focused evidence: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoCacheNoSpillDynamicTest.php` -> `1 test files, 23935 assertions, 0 failures`, `1187` PASS lines.
- Non-overlap: prior VFS I/O dynamic slices covered append VFS layout/content, transaction sync ordering, default page-size selection, size-hint chunk growth, mmap read/remap/vacuum behavior, file controls, lock matrices, and dynamic IO fault recovery. This slice is isolated to `io.test` `io-6.*` pager-cache retention after warmed reads and disk corruption.
- Dependency closure: no new support component needed; this reuses the existing bounded VFS I/O dynamic planner surface.
- Next task: continue VFS/pager corpus with another real upstream section that is not `io-6.*`, or use this behavior to reduce any default-memory pager pressure cluster that requires cache-retention evidence.
