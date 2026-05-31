# real-upstream-corpus-vfs-io-dynamic-20260531T043249Z-0

## Scope

- Upstream source: `/home/claude/port-libs/.upstream-cache/libsqlite/test/tempfault.test`
- Ported sections:
  - `tempfault-1`: temp database single-row insert fault injection.
  - `tempfault-2`: indexed temp database update fault injection.
  - `tempfault-2.1`: reused temp connection indexed update fault injection.
  - `tempfault-3`: temp database savepoint/update/rollback fault injection with integrity check.
  - `tempfault-4`: same savepoint sequence without the final upstream integrity check.

## Implementation

- Added `SQLiteVfsIoTransactionSequencePlan::tempDatabaseFaultOutcome()` for generic temp-database VFS fault recovery.
- Added focused dynamic corpus coverage in `SQLiteRealUpstreamCorpusVfsTempFaultDynamicTest.php`.
- The test creates 1,280 dynamic upstream behavior cases across 5 real upstream scenarios, 8 VFS operations, and 32 failpoints, plus 7 provenance/guard cases.

## Non-Overlap

This slice does not repeat accepted `io.test` quick-balance, atomic write, safe-append, sequential sync, default page-size, or pager-cache retention coverage. It also avoids accepted `ioerr*`, `mmap*`, `syscall`, `sysfault`, `walvfs`, append-vfs, quota, VFS writer/sync/lock, rollback-journal apply/commit, WAL checkpoint/savepoint, and temp lifecycle delete-on-close surfaces. The owned behavior is specifically `tempfault.test` temp database VFS fault recovery.

## Verification

- `php -l lanes/libsqlite/src/SQLiteVfsIoTransactionSequencePlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsTempFaultDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsTempFaultDynamicTest.php`
  - Result: `1 test files, 26888 assertions, 0 failures`
  - PASS lines: 1287

## Dependency Closure

No new support component is required. This reuses the lane-local VFS I/O transaction sequence and I/O error-injection modeling surfaces and adds a bounded native PHP helper for temp database fault recovery.
