# real-upstream-corpus-vfs-io-dynamic-syscall-temp-chunk-20260531T034756Z

Slice: `real-upstream-corpus-vfs-io-dynamic-20260531T034756Z-0`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/syscall.test`
- `syscall.test 6.1`: close several file-backed and in-memory handles.
- `syscall.test 6.2`: `temp_store=file` large temp-table close after cache spill.
- `syscall.test 8.3`: reset file-control chunk size to 16 bytes.
- `syscall.test 8.4.1-8.4.5`: `SQLITE_FCNTL_SIZE_HINT` rounds growth to chunk boundaries.

Behavior added:

- Added `SQLiteVfsIoDynamicPlan::syscallTempHandleCloseProfile()` for the
  upstream syscall temp-handle close contract: file-backed handles and an
  in-memory handle close cleanly, temp-store file spills are unlinked on close,
  no handles leak, and the main database can be reused.
- Added `SQLiteRealUpstreamCorpusVfsSyscallTempChunkDynamicTest.php` with 1,426
  distinct TestRunner PASS cases over temp rows, main/temp cache sizes,
  in-memory handle inclusion, and 16/32/64/128-byte chunk-size hint rounding.
- Reuses the existing `fileControlChunkSizeHintProfile()` for the remaining
  syscall 8.3/8.4 small chunk-size matrix.

Verification:

- `php -l lanes/libsqlite/src/SQLiteVfsIoDynamicPlan.php` passed.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsSyscallTempChunkDynamicTest.php` passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsSyscallTempChunkDynamicTest.php` passed: `1 test files, 21094 assertions, 0 failures`, `1426` PASS lines.

Non-overlap:

- This does not repeat accepted syscall registry, single-byte open, chunk-size
  8.1/8.2, syscall EINTR retry, peer-lock close preservation, VFS writer,
  locked writer, lock-state, process-lock sidecars, sync plan/apply,
  rollback-journal apply/commit, super-journal, appendvfs, cksumvfs, memory
  journal, subjournal, sysfault, ioerr, atomic/crash, WAL checkpoint/savepoint,
  mmap, quota/quota2, delete_db, or `io.test` device-matrix batches.
- The owned gap is the remaining `syscall.test` 6.1/6.2 temp/handle close
  behavior plus 8.3/8.4 small chunk-size hint rounding.

Dependency closure:

- No new support component is needed. The slice reuses the existing bounded
  VFS I/O dynamic corpus model and file-control size-hint helper.
