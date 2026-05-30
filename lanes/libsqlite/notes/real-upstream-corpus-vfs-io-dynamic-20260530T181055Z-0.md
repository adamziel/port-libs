# real-upstream-corpus-vfs-io-dynamic-20260530T181055Z-0

Base accepted HEAD: `a9928e604a7d849ecf8aa28f83049e71a24f4b05`

Implemented one real upstream VFS/IO behavior cluster from:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/io.test`
  - `io-6.1`: creates and warms a database with `mmap_size=0`, a large pager cache, table/index data, and ordered scans.
  - `io-6.2.1.1` through `io-6.2.1.3`: multi-table commit after warm cache must not flush cached pages before the post-corruption integrity check.
  - `io-6.2.2.1` through `io-6.2.2.3`: single-table atomic-write commit after warm cache must not flush cached pages before the post-corruption integrity check.

New focused coverage:

- Added `SQLiteVfsIoDynamicPlan::atomicPagerCacheRetentionProfile()`.
- Added `SQLiteRealUpstreamCorpusVfsIoAtomicPagerCacheTest.php`.
- Focused result: `1 test files, 12636 assertions, 0 failures`.
- PASS-line delta: `+7` focused TestRunner PASS cases.
- Behavior-assertion delta: `+12636`, satisfying the real-corpus hard floor by assertion volume.

Non-overlap:

This does not repeat accepted VFS file writer, locked writer, process locks,
lock state, rollback-journal apply/commit, super-journal commit, sync plan/apply,
file-control/nolock, appendvfs, checksum/WAL VFS, atomic journal admission, or
`io.test` safe-append/default-page-size/transaction-sequence clusters. The new
surface is specifically upstream `io.test` `io-6` pager-cache retention after
atomic-capable commits and post-commit disk corruption.

Dependency closure:

No new support component is needed. The slice reuses existing bounded VFS
capability and dynamic I/O planning helpers in native PHP.
