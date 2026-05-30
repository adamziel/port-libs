# Real upstream corpus VFS I/O dynamic

Slice: `real-upstream-corpus-vfs-io-dynamic-20260530T160319Z-0`
Base accepted HEAD: `8bf0d9f81b29a5601901bb34dfd730670ed39bbc`

## Upstream source truth

Hydrated upstream files read from `/home/claude/port-libs/.upstream-cache/libsqlite/test`:

- `io.test`: `io-2.2`, `io-2.3`, `io-2.5`, `io-2.6`, `io-2.7`, `io-2.8`, `io-2.9`, `io-2.10`, `io-2.11`, `io-3.2`, `io-3.3`, `io-4.1`, `io-4.2`, `io-4.3`, `io-5`, and `io-6`.
- `ioerr.test`: `ioerr-1`, `ioerr-2`, `ioerr-3`, `ioerr-4`, `ioerr-5`, `ioerr-7`, `ioerr-8`, and `ioerr-9`.
- `walvfs.test`: `1.1` and `1.3`.

## Behavior added

Added `SQLiteVfsIoTrafficPlan` for upstream-backed VFS/pager I/O decisions:

- atomic-write eligibility by device characteristic, page size, sector size, append count, and multi-file commit boundary;
- rollback-journal creation/deferred creation and sync target planning;
- `IOCAP_SEQUENTIAL` cache-spill and WAL-header sync deferral;
- `IOCAP_SAFE_APPEND` journal-header sync reduction and `nRec` sentinel handling;
- default page-size selection for atomic device characteristics;
- focused I/O error boundary classification for read-past-EOF suppression, VACUUM checksum checks, hot-journal rollback, and master-journal-name reads.

## Verification

- `php -l lanes/libsqlite/src/SQLiteVfsIoTrafficPlan.php`: no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamVfsIoDynamicCorpusTest.php`: no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamVfsIoDynamicCorpusTest.php`: `1 test files, 58 assertions, 0 failures`.

## Countability

This handoff owns `+58` focused PHP TestRunner PASS lines. It does not claim mapped denominator growth, release/all parity, or upstream Tcl runner pass rows.

## Dependency closure

No new support component is needed. The implementation reuses existing VFS capability flag names from `SQLiteVfsCapabilityPlan` and keeps the new behavior lane-local.
