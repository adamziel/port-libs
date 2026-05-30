# real-upstream-corpus-vfs-io-dynamic-20260530T193035Z-0

Implemented a focused WAL VFS SHM/readmark dynamic corpus batch against the existing
`SQLiteVfsIoDynamicPlan::walShmFaultProfile()` behavior.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/walvfs.test`
- Covered scenarios: `walvfs.test 4.0`, `4.1`, `4.2`, `5.2`, `5.3`,
  `5.4`, `5.5`, `5.6`, `6.1`, `6.2`, `7.1`, `8.2`, `8.3`, and `9.1`.

Focused coverage:

- Added `lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsWalShmDynamicTest.php`.
- The file contributes 1322 individual TestRunner PASS cases and 16302
  assertions.
- Behavior covered: readonly SHM map failure, readmark reset recovery,
  protocol retry/busy paths, checkpoint cache-flush visibility, and shared-lock
  I/O error classification.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsWalShmDynamicTest.php`
  passed: `1 test files, 16302 assertions, 0 failures`.

Non-overlap:

- This batch does not repeat prior VFS lock-state, file-writer, sync,
  rollback-journal, `io.test`, `avfs.test`, `cksumvfs.test`, or `ioerr.test`
  dynamic coverage. It targets the existing upstream `walvfs.test` SHM/readmark
  fault sections 4 through 9.

Dependency closure:

- No new support component is needed. The existing bounded
  `SQLiteVfsIoDynamicPlan` and WAL/SHM fault model are reused.
