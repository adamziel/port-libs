# Real Upstream Corpus VFS IOERR Pointer Map Dynamic

Slice: `real-upstream-corpus-vfs-io-dynamic-20260530T203829Z-0`

Accepted base: `80c609b1de0bbfd42f2c3e021c00d868ce6dbc14`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/ioerr.test`
- Scenarios: `ioerr-13`, `ioerr-14`, `ioerr-15`, and `ioerr-16`.

Patch contents:

- Added `SQLiteVfsIoTrafficPlan::ioerrPointerMapFault()` for the late `ioerr.test` pointer-map/overflow fault contract: incremental auto-vacuum setup, balance-quick pointer-map page updates, balance-deeper overflow-parent pointer-map rewrites, index delete plus large overflow statement rollback, incremental-vacuum tkt3762 branch, rollback/refcount checks, preserved integrity, and no leaked open files.
- Added `SQLiteRealUpstreamCorpusVfsIoerrPointerMapDynamicTest.php` with 1,400 dynamic VFS fault PASS cases plus upstream citation and malformed-input guards.

Focused evidence:

- `php -l lanes/libsqlite/src/SQLiteVfsIoTrafficPlan.php` passed.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoerrPointerMapDynamicTest.php` passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoerrPointerMapDynamicTest.php` passed: `1 test files, 32234 assertions, 0 failures`, `1402` PASS lines.

Non-overlap:

- This does not repeat the accepted `io.test` traffic/default-page-size coverage, `ioerr2.test`, `ioerr3.test`, `ioerr4.test`, `ioerr5.test`, `ioerr6.test`, atomic2, VFS file writer, lock-state, sync-plan, rollback-journal apply, or WAL checkpoint/savepoint clusters.
- The owned upstream section is `ioerr.test` late pointer-map and overflow fault behavior, specifically `ioerr-13` through `ioerr-16`.

Dependency closure:

- No new support component is required. The patch reuses the existing VFS I/O traffic planning surface and adds a bounded native PHP helper for the upstream pointer-map I/O error behavior.
