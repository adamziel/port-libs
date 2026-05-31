# real-upstream-corpus-vfs-atomic2-batch-fallback-20260531T020357Z

Slice: `real-upstream-corpus-vfs-io-dynamic-20260531T020357Z-0`

Accepted base: `e1f1e0a66bff0730bf5e4118bd715c8a11c33354`

Upstream source:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/atomic2.test`
- Sections: `atomic2-1.0` seed table/index setup and `atomic2-2.0` fault simulation where an injected `xWrite` I/O error before `COMMIT_ATOMIC_WRITE` falls back to a legacy journal commit.

Behavior added:

- `SQLiteVfsIoDynamicPlan::atomicBatchFaultFallbackProfile()` models batch-atomic VFS commit attempts, injected write failures, fallback to rollback-journal commit, final row visibility, and integrity preservation.
- `SQLiteRealUpstreamCorpusVfsAtomic2BatchFallbackDynamicTest.php` adds 1000 dynamic real-upstream PASS cases plus source-citation and invalid-input guards.

Non-overlap:

- Existing VFS I/O dynamic tests cover `io.test` atomic writes, cache-spill sync, safe-append, default page size, pager-cache retention, appendvfs, ioerr, WAL VFS, mmap, nolock URI, and device matrices.
- This slice is specific to `atomic2.test` F2FS-style `batch_atomic` fault fallback before `COMMIT_ATOMIC_WRITE`; it does not repeat accepted `io.test` atomic journal admission or WAL/rollback commit application clusters.

Dependency closure:

- No new support component is required. The slice reuses the existing VFS device flag model and pure-PHP VFS I/O dynamic plan helpers.

Expected movement:

- Focused test growth: `1001` TestRunner PASS cases from real upstream `atomic2.test`.
- Mapped denominator: no change; manifest coverage is already complete.
