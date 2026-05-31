# real-upstream-corpus-vfs-io-dynamic-20260531T033356Z-0

Accepted base for this isolated lane: `9f3a6190507c2ea8ee290883ee3ce143ab18c8c9`.

Added focused PHP coverage in `SQLiteRealUpstreamCorpusVfsIoAtomicFaultMatrixTest.php` for hydrated upstream VFS I/O scripts:

- `io.test` `io-2.4` through `io-2.11`: atomic-write visibility, deferred rollback-journal creation, multi-file commit rollback, sector-specific atomic flags, and exclusive locking.
- `io.test` `io-2.5.1` through `io-2.5.3`: first atomic write followed by a second dirty page that requires rollback-journal creation.
- `atomic2.test` `1.0` and `2.0`: F2FS/atomic-batch-write I/O fault fallback to legacy rollback-journal commit.
- `ioerr.test` `ioerr-7`, `ioerr-9`, `ioerr-10`, and `ioerr-11`: journal playback and UPDATE cursor assertion recovery after read/write/sync/truncate I/O faults.

The batch owns `1001` distinct TestRunner cases in a new, non-overlapping VFS I/O atomic/fault matrix. It does not add metadata-only rows, new upstream script ids, domain-specific API names, or source-neutral compatibility wrappers.

Dependency closure: no new support component is needed. The tests reuse existing native PHP VFS/pager planning helpers in `SQLiteVfsIoDynamicPlan` and the existing lane TestRunner.

Focused verification:

- PASS: `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoAtomicFaultMatrixTest.php`
- PASS: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoAtomicFaultMatrixTest.php`
  - `1 test files, 19601 assertions, 0 failures`
  - `1001` PASS lines
- PASS: `php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'`
- PASS: `git diff --check -- lanes/libsqlite`
- API guard not run because the generic no-domain guard file is not present in this accepted worktree.
