# real-upstream-corpus-vfs-io-dynamic-20260530T215309Z-0

Base accepted HEAD: `4d354e3a7fdb39040e393b5132f7de86a7766ad9`

Added a focused real-upstream VFS I/O corpus batch for `/home/claude/port-libs/.upstream-cache/libsqlite/test/ioerr5.test`.

Covered upstream scenarios:

- `ioerr5.test` `ioerr5-1`: pager error-state memory reclaim with a read cursor, `normal` and `exclusive` locking modes, failpoints 1 through 199.
- `ioerr5.test` `ioerr5-2`: `sqlite3_release_memory()` before commit from a dirty pager, `exclusive` and `normal` locking modes, failpoints 1 through 199.

Focused movement:

- New PHP TestRunner file: `SQLiteRealUpstreamCorpusVfsIoerr5MemoryReclaimFullRangeTest.php`.
- Focused PASS cases: 1,594.
- Focused behavior assertions: 25,480.
- Expected lane `phpPass` movement: `844276 -> 845870`.
- Mapped denominator movement: none; mapped coverage was already complete at `1589 / 1589`.

Non-overlap:

- This batch extends the prior `ioerr5.test` sampled coverage from the existing VFS I/O error dynamic tests into the full upstream failpoint range used by `ioerr5-1` and `ioerr5-2`.
- It does not repeat accepted VFS mmap, backup I/O error, default-page-size, ioerr2/ioerr3/ioerr4, pointer-map, rollback-journal, WAL/SHM, or traffic-matrix batches.
- It does not add metadata-only rows or fabricated `.test` names.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoerr5MemoryReclaimFullRangeTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoerr5MemoryReclaimFullRangeTest.php`
- Pending final gate in this worktree: `git diff --check -- lanes/libsqlite` and API/domain guard.

Dependency closure:

- No new support component is needed. The batch reuses existing lane-local `SQLiteVfsIoTransactionSequencePlan::ioErrorOutcome()` behavior for pager error-state and VFS I/O error classification.
