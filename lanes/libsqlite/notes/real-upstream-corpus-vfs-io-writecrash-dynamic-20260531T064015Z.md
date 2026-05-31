# real-upstream-corpus-vfs-io-writecrash-dynamic-20260531T064015Z

Slice: `real-upstream-corpus-vfs-io-dynamic-20260531T064015Z-0`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/writecrash.test`
- Scenarios: `writecrash-1.0` setup, `writecrash-1.*` `crash_on_write` update loop, and integrity checks before/after reopen.

Implemented behavior:

- Added `SQLiteVfsIoDynamicPlan::writeCrashRecoveryProfile()` for the upstream writer-crash-in-`xWrite` recovery shape.
- Added 1,000 dynamic focused cases over row counts, page sizes, payload sizes, update modulo values, and failpoints.
- Added malformed-input guards for unsupported scenario names, invalid failpoints, invalid row/update/page/payload inputs.

Non-overlap:

- This does not cover accepted VFS sync/file-writer/lock-state/rollback-journal apply clusters.
- This does not cover existing `io.test` default page-size, atomic cache retention, `ioerr*`, `mmap*`, WAL SHM, or delete-database dynamic VFS tests.
- The new upstream filename is `writecrash.test`, which was not already represented by a dedicated focused VFS IO corpus test.

Dependency closure:

- No new support component is needed. The slice reuses the existing native PHP VFS dynamic planner and records the bounded writer-crash recovery semantics as lane-local PHP behavior.

Verification:

- `php -l lanes/libsqlite/src/SQLiteVfsIoDynamicPlan.php` passed.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoWriteCrashDynamicTest.php` passed.
- `php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'` passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoWriteCrashDynamicTest.php` passed: `1 test files, 25009 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoWriteCrashDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamVfsIoDynamicExpandedCorpusTest.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoTransactionSequenceTest.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoPagerCacheDynamicTest.php` passed: `4 test files, 64659 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` passed: `1 test files, 3 assertions, 0 failures`.
- `git diff --check -- lanes/libsqlite` passed.
