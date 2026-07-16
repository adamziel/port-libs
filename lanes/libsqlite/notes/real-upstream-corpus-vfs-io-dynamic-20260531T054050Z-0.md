# Real Upstream Corpus VFS IO Dynamic 20260531T054050Z-0

- Base accepted HEAD: `4492e9529d6540daf2941a27323f36260b8cf64c`.
- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/ioerr2.test`.
- Ported upstream section: `ioerr2.test ioerr2-7`, the auto-vacuum two-table `UPDATE`/`DELETE`/`COMMIT` faultsim loop.
- Added behavior: `SQLiteVfsIoTrafficPlan::ioerr2AutoVacuumCommitFault()` models the full auto-vacuum commit fault boundary with pointer-map, freelist, checksum, integrity, pager-refcount, and open-file cleanup invariants.
- Focused PHP coverage: `SQLiteRealUpstreamCorpusVfsIoerr2AutoVacuumCommitDynamicTest.php` owns 1,000 distinct fault positions plus source-citation and malformed-input guards.
- Verified focused result: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoerr2AutoVacuumCommitDynamicTest.php` passed with `1 test files, 24009 assertions, 0 failures` and 1,003 PASS lines.
- Non-overlap: this slice avoids accepted VFS file writer, rollback journal apply/commit, WAL checkpoint/savepoint byte paths, mmap, quick-balance, ioerr6 full-disk, existing ioerr2 rollback-batch/temp-directory coverage, JSON, SQL, B-tree, and WordPress-shaped compatibility surfaces.
- Dependency closure: reuses existing lane-local VFS/pager planning primitives only; no new support component is needed.
