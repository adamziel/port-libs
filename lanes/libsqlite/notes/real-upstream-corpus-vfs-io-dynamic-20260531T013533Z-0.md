# real-upstream-corpus-vfs-io-dynamic-20260531T013533Z-0

Base accepted HEAD: `472430c1daaad1016852e97d68cabd3ea687d289`.

Owned upstream source:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/io.test`
- `io.test io-1.1` schema root-page and change-counter write count
- `io.test io-1.2` root leaf insert write count
- `io.test io-1.3` root split write count
- `io.test io-1.4` existing leaf insert write count
- `io.test io-1.5` quick-balance third-leaf write count

Patch summary:

- Added `SQLiteRealUpstreamCorpusVfsIoQuickBalanceMatrix20260531T013533ZTest.php`.
- The new focused matrix covers 1,024 distinct page-size/payload/row-count/probe combinations using `SQLiteVfsIoDynamicPlan::quickBalanceDynamicWriteProfile()`, plus canonical upstream sequence and source-citation checks.
- This is non-overlapping with the accepted VFS IO device matrix, atomic/safe-append/default-page-size/sync slices, mmap slices, WAL/SHM fault slices, and ioerr pointer-map slices. It specifically owns the `io.test` `io-1.*` quick-balance write-count shape.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoQuickBalanceMatrix20260531T013533ZTest.php` passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoQuickBalanceMatrix20260531T013533ZTest.php` passed: `1 test files, 20487 assertions, 0 failures`.
- PASS-line growth from this focused file: 1,026 PASS lines.

Dependency closure:

- No new support component is needed. The slice reuses existing bounded native VFS IO traffic planning and real upstream `io.test` source semantics.

Root harness:

- Not run; isolated micro-slice.
