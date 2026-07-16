# Real Upstream Corpus VFS I/O Dynamic Reserve Bytes

Session: `port-dev-sqlite-yield-dyn-real-vfs-20260531T082320Z`
Micro-slice: `real-upstream-corpus-vfs-io-dynamic-20260531T082320Z-0`
Base accepted HEAD: `b9873c852a7f5b8dd171221d5d3abd96ee2031c8`

## Source Truth

- Hydrated upstream file: `/home/claude/port-libs/.upstream-cache/libsqlite/test/reservebytes.test`
- Ported sections:
  - `reservebytes.test 1.0` create table/index and populate rows.
  - `reservebytes.test 1.1` second connection integrity before reserve change.
  - `reservebytes.test 1.2.1` `file_control_reservebytes db 8` leaves header byte at offset 20 unchanged.
  - `reservebytes.test 1.2.2` second connection integrity after pending reserve-byte change.
  - `reservebytes.test 1.3.2` first `VACUUM` rebuild applies the first reserve byte.
  - `reservebytes.test 1.3.4` integrity after first `VACUUM`.
  - `reservebytes.test 1.3.5` header byte records the first reserve value.
  - `reservebytes.test 1.4.1` second reserve request remains pending until `VACUUM`.
  - `reservebytes.test 1.4.2` second `VACUUM` rebuild applies the second reserve byte.
  - `reservebytes.test 1.4.3` integrity after second `VACUUM`.
  - `reservebytes.test 1.4.4` header byte records the second reserve value.

## Focused Delta

- Added `SQLiteVfsIoDynamicPlan::reserveBytesVacuumHeaderProfile()` to model the upstream reserve-byte header lifecycle with generic table/index names.
- Added 1,000 generated focused cases across reserve byte requests, page sizes, row counts, and randomblob sizes.
- Added source-citation, malformed-input guard, ownership, non-overlap, and dependency-closure tests.
- Expected dashboard movement: `phpPass` +1004, mapped coverage unchanged.

## Verification

- `php -l lanes/libsqlite/src/SQLiteVfsIoDynamicPlan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteVfsIoDynamicPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoDynamicReserveBytes20260531T082320ZTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoDynamicReserveBytes20260531T082320ZTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoDynamicReserveBytes20260531T082320ZTest.php`
  - `1 test files, 40020 assertions, 0 failures`
  - 1004 focused PASS cases.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 3 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoDynamicCksumReserve20260531Test.php`
  - `1 test files, 72511 assertions, 0 failures`

## Non-Overlap

This slice covers `reservebytes.test` header-byte and `VACUUM` reserve-byte application. It avoids the existing cksumvfs checksum reserve-byte WAL lifecycle, `io.test` atomic/default/cache-spill/short-name VFS cases, rollback-journal apply/commit paths, VFS writer/sync/lock paths, ioerr/pagerfault, mmap/quota, and WAL checkpoint/savepoint clusters.

## Dependency Closure

No new support component is needed. The slice reuses the existing `SQLiteVfsIoDynamicPlan` arithmetic-profile pattern and the hydrated upstream `reservebytes.test` source truth.
