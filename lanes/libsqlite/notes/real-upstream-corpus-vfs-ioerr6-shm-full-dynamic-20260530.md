# Real Upstream Corpus VFS IOERR6 SHM Full Dynamic

Slice: `real-upstream-corpus-vfs-io-dynamic-20260530T225809Z-0`

Base accepted HEAD: `6e94a67dd020b9cfec1567bd7fbc6ebe5e036bda`

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/ioerr6.test`
- Scenarios: `ioerr6-1.1`, `ioerr6-1.2`, and faultsim sections `2` and `3`.

## Behavior Ported

- Added `SQLiteRealUpstreamCorpusVfsIoerr6ShmFullDynamicTest.php` with 1,000 distinct dynamic PASS cases over the existing native `SQLiteVfsIoTrafficPlan::dynamicFaultRecovery()` VFS fault behavior.
- The corpus covers the upstream `testvfs shmfault` atomic-write path where `SQLITE_FULL` during VFS/SHM write activity returns `database or disk is full`, preserves database bytes, leaves integrity `ok`, closes all files, and keeps SHM integrity after reopen/faultsim restoration.
- The test matrix varies locking mode, VFS fault operation sets, read-cursor presence, and reopen handling while citing the real upstream `ioerr6.test` scenario sections.

## Non-Overlap

- This does not repeat accepted `ioerr4` incremental-vacuum faults, `ioerr5` memory reclaim, `ioerr2` rollback invariants, `ioerr3` soft-heap pressure, `pageropt`, appendvfs, WAL checkpoint/savepoint, VFS sync/lock/writer, or rollback-journal commit/apply clusters.
- The owned upstream section is `ioerr6.test` SHM/full fault behavior under atomic-write VFS capabilities.

## Verification

- Focused test:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoerr6ShmFullDynamicTest.php`
- PHP lint:
  - `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoerr6ShmFullDynamicTest.php`
- Diff hygiene:
  - `git diff --check -- lanes/libsqlite`

## Dependency Closure

No new support component is required. The patch reuses the existing bounded native VFS dynamic fault recovery helper and adds real upstream corpus assertions for `ioerr6.test`.
