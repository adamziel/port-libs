# real-upstream-corpus-pager-wal-dynamic-20260601T104107Z-0

## Scope

- Lane: libsqlite.
- Base accepted HEAD: `4d71d2c69326a2d3cad8d2b8fb0de26b66be4fbb`.
- Source truth: hydrated upstream SQLite `/home/claude/port-libs/.upstream-cache/libsqlite/test/walcksum.test`.
- Ported sections: `walcksum-3.0..3.2`, copied recovery `do_test 1.3`, `walcksum-4.0..4.3`, and `walcksum-5.0..5.3`.

## Behavior

Added `SQLiteRealUpstreamPagerWalDynamicCorpusPlan::walChecksumSavepointRegressionRows()` for 1,000 dynamic cache-size-1 WAL savepoint rollback rows. The rows exercise valid WAL tails after the last committed frame, copied-WAL recovery boundaries, checkpoint image application, expected row signatures, rolled-back row signatures, and `SQLiteSavepointStack` WAL rollback planning.

Non-overlap: this targets the upstream `walcksum` savepoint/cache-spill regression sections, not accepted `walcksum-1` checksum-endian cases, `walcksum-2` corrupt statement-tail recovery, stale reused-log prefix recovery, WAL byte truncation, VFS writer/sync/lock, rollback-journal apply/commit, or app-WAL scenarios.

## Evidence

- `php -l lanes/libsqlite/src/SQLiteRealUpstreamPagerWalDynamicCorpusPlan.php` -> no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalChecksumSavepointDynamic20260601Test.php` -> no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalChecksumSavepointDynamic20260601Test.php` -> `1 test files, 39019 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` -> `1 test files, 5 assertions, 0 failures`.

Expected focused PASS-case growth: `+1003` TestRunner PASS cases. `phpPass` in `lane-status.json` moved from `5792118` to `5793121`.

## Dependency Closure

No new support component is needed. This reuses existing native PHP `SQLiteWal` checksum parsing, transaction recovery boundary, checkpoint database application, `SQLiteSavepointStack` WAL rollback planning, and the hydrated upstream `walcksum.test` source truth.
