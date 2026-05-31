# real-upstream-corpus-vfs-io-dynamic-20260531T053118Z-0

## Upstream source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/ioerr2.test`
  - `ioerr2-2` through `ioerr2-7`: rollback/checksum/refcount preservation,
    temp-directory access failures, and reopened-connection persistence.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/ioerr3.test`
  - `ioerr3-1` and `ioerr3-2`: soft-heap/cache-pressure I/O error recovery.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/ioerr4.test`
  - `ioerr4-1.1` through `ioerr4-2`: shared-cache incremental-vacuum fault
    recovery and pointer-map preservation.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/ioerr.test`
  - `ioerr-13` through `ioerr-16`: pointer-map faults around balance_quick,
    balance_deeper, index-delete statement rollback, and incremental vacuum.

## Added coverage

- Added `lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoErrDynamicTest.php`.
- The file adds 1,001 distinct focused TestRunner PASS cases.
- Focused assertion count: 18,501 assertions.
- This is non-overlapping with the existing VFS I/O expanded corpus that
  already covered temp-file lifecycle, SQL file controls, byte-range locking,
  dynamic `ioerr5`/`ioerr6`/`pagerfault` recovery, transaction sequences, and
  default page-size selection.

## Verification

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoErrDynamicTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoErrDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoErrDynamicTest.php`
  - `1 test files, 18501 assertions, 0 failures`

## Dependency closure

No new support component is needed. The slice reuses existing generic
`SQLiteVfsIoTrafficPlan` behavior for upstream VFS I/O fault recovery,
soft-heap/cache-pressure recovery, shared-cache incremental vacuum recovery,
and pointer-map fault preservation.
