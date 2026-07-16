# real-upstream-corpus-vfs-io-dynamic-20260531T034238Z-0

Source truth:
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/io.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/atomic2.test`

Owned upstream sections:
- `io.test` `io-2.5.1` through `io-2.5.3`: multi-page atomic-write transactions must create/use the rollback journal after a second dirty page disables the single-page atomic path.
- `io.test` `io-2.6.1` through `io-2.11.2`: atomic journal admission, rollback visibility, blocked journal paths, multi-file commit, explicit rollback, and exclusive-lock variants.
- `atomic2.test` `1.0` and `2.0`: batch-atomic VFS write fallback under injected `xWrite` failures.

Non-overlap:
- This batch does not touch accepted syscall retry, `delete_db.test`, `ioerr2`/`ioerr5`, WAL SHM/read-mark, mmap, quota, safe-delete journal, file writer, lock-state, rollback commit, or VFS sync apply clusters.
- It adds a new focused TestRunner file for atomic write and batch-atomic VFS behavior using existing generic `SQLiteVfsIoDynamicPlan` behavior.

Dependency closure:
- No new support component is needed. The batch reuses existing bounded native PHP VFS I/O dynamic planning primitives and cites hydrated upstream SQLite scripts directly.

Verification:
- Passed: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoAtomicBatchDynamicTest.php`
  - `1 test files, 20013 assertions, 0 failures`
  - `1202` focused TestRunner PASS lines
- Passed: `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoAtomicBatchDynamicTest.php`
