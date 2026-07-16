# Real Upstream Corpus VFS IOERR4 Incremental Vacuum Dynamic

Slice: `real-upstream-corpus-vfs-io-dynamic-20260530T201344Z-0`

Accepted base: `c1a0d2c80ea721e0595b20a5cbe43c5043856066`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/ioerr4.test`
- Scenarios: `ioerr4-1.1` through `ioerr4-1.6` setup and `ioerr4-2` shared-cache incremental vacuum I/O fault loop.

Patch contents:

- Added `SQLiteVfsIoTrafficPlan::incrementalVacuumSharedCacheIoError()` for the upstream ioerr4 contract: two shared-cache connections, incremental auto-vacuum, 32 inserted rows, 64-page freelist after delete, injected VFS faults during `PRAGMA incremental_vacuum(5)`, pointer-map validation, preserved freelist state, integrity check, and no leaked open files.
- Added `SQLiteRealUpstreamCorpusVfsIoerr4IncrementalVacuumDynamicTest.php` with 1,000 dynamic fault-injection PASS cases over `xWrite`, `xSync`, `xTruncate`, and `xRead`, plus setup citation, vacuum-page clamp, and malformed-input guards.

Focused evidence:

- `php -l lanes/libsqlite/src/SQLiteVfsIoTrafficPlan.php` passed.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoerr4IncrementalVacuumDynamicTest.php` passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoerr4IncrementalVacuumDynamicTest.php` passed: `1 test files, 22015 assertions, 0 failures`, `1003` PASS lines.

Non-overlap:

- This does not repeat the existing `io.test`, `ioerr2.test`, `ioerr3.test`, `ioerr5.test`, `ioerr6.test`, atomic2, VFS file writer, lock-state, sync-plan, rollback-journal apply, or WAL checkpoint/savepoint clusters.
- The owned upstream section is `ioerr4.test`, specifically incremental vacuum with shared cache under VFS I/O error injection.

Dependency closure:

- No new support component is required. The patch reuses the existing VFS I/O traffic planning surface and adds a bounded native PHP helper for the ioerr4 upstream behavior.
