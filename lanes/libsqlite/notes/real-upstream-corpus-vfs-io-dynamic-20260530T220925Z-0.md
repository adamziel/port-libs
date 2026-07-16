# real-upstream-corpus-vfs-io-dynamic-20260530T220925Z-0

Added `SQLiteRealUpstreamCorpusVfsIoErrorDynamicBatchTest.php`, a focused real-upstream VFS I/O error-injection batch sourced from hydrated SQLite upstream files:

- `ioerr.test`: `ioerr-1`, `ioerr-2`, `ioerr-5`, `ioerr-10`, `ioerr-14`
- `ioerr2.test`: `ioerr2-2`, `ioerr2-7`
- `ioerr3.test`: `ioerr3-1`, `ioerr3-2`
- `ioerr4.test`: `ioerr4-2`

The batch owns 1,040 distinct dynamic TestRunner cases over 10 upstream scenarios, 8 VFS operations, and 13 failpoints, plus source-section and malformed-input guards. It exercises the existing PHP VFS I/O recovery planner for expected SQLite result codes, pager recovery actions, excluded failpoints, checksum/refcount checks, stable database-image handling, and dependency/upstream provenance. This is non-overlapping with the existing `avfs.test`, `cksumvfs.test`, `walvfs.test`, `io.test`, `ioerr5.test`, `ioerr6.test`, and `pagerfault.test` VFS dynamic batches.

Dependency closure: no new support component is needed; this reuses the existing bounded native PHP VFS I/O error-injection planner.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoErrorDynamicBatchTest.php` -> `1 test files, 18729 assertions, 0 failures`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoErrorDynamicBatchTest.php` -> no syntax errors
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` -> `1 test files, 3 assertions, 0 failures`
- `git diff --check -- lanes/libsqlite` -> passed
