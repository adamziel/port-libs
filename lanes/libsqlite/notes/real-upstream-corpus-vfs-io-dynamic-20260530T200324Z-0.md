# Real upstream corpus VFS IO traffic matrix

Micro-slice: `real-upstream-corpus-vfs-io-dynamic-20260530T200324Z-0`

Accepted base: `688b5b5b02ee30d2a82f4468b5b909f17254ae0e`

## Upstream source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/io.test`
  - `io-2.2` through `io-2.11`: rollback-journal vs atomic-write transaction traffic, sector-size gates, atomic page-size capability flags, appended-page fallback, and multifile fallback.
  - `io-3.*`: sequential-device sync elision.
  - `io-4.*`: safe-append journal header behavior.
  - `io-5.*`: default page-size selection from VFS capabilities.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/walvfs.test`
  - `walvfs-1.1` and `walvfs-1.3`: WAL VFS sync behavior.

## Patch

- Added `SQLiteRealUpstreamVfsIoTrafficMatrixTest.php`.
- The new test adds 1,034 focused TestRunner PASS cases and 17,551 behavior assertions.
- The generated matrix covers 1,032 distinct upstream-derived VFS I/O transaction combinations across device capability flags, page sizes, sector sizes, sync modes, and dirty-page shapes, plus two guard/citation cases.

## Non-overlap

This is a real upstream `io.test`/`walvfs.test` VFS I/O traffic matrix. It does not repeat accepted VFS file writer, locked writer, lock-state, process locks, rollback-journal apply/commit, sync plan/apply, file-control/nolock, appendvfs growth/tiny-open refusal, safe-append sizing singletons, default-page-size singletons, IOERR recovery, or atomic reader-visibility coverage. The new surface is high-volume dynamic transaction traffic parity over the accepted `SQLiteVfsIoTrafficPlan::transaction()` behavior.

## Verification

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamVfsIoTrafficMatrixTest.php`
  - PASS: no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamVfsIoTrafficMatrixTest.php`
  - PASS: `1 test files, 17551 assertions, 0 failures`.

## Dependency closure

No new support component is needed. This reuses the existing bounded native PHP VFS I/O traffic model and upstream hydrated SQLite corpus files.

## Next

Next VFS corpus work should move to unmapped upstream VFS rows or real behavior fixes, not another status-only note or a duplicate singleton around accepted VFS transaction helpers.
