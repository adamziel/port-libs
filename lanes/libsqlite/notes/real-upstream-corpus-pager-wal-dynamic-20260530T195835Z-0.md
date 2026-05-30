# real-upstream-corpus-pager-wal-dynamic-20260530T195835Z-0

Status: ready focused upstream pager/WAL corpus growth on accepted base `688b5b5b02ee30d2a82f4468b5b909f17254ae0e`.

This slice adds `SQLiteRealUpstreamPagerWalDynamicMatrixTest.php` with 1,025 distinct TestRunner PASS cases and 11,009 focused assertions. The scenarios are sourced from the hydrated upstream SQLite checkout:

- `wal.test`: `wal-1.*`, `wal-2.*`, `wal-3.*`, `wal-4.*` reader, rollback, savepoint, and checkpoint boundaries.
- `wal2.test`: `wal2-1.*` reader recovery after wal-index header corruption.
- `walcksum.test`: checksum recovery truncating at a corrupt WAL frame.
- `walcrash.test` / `walcrash2.test`: crash recovery keeping only the committed WAL prefix.
- `walpersist.test`: `walpersist-1.*`, `walpersist-2.2`, `walpersist-3.3`, `walpersist-4.1` persistent WAL file-control transitions.
- `walro2.test`: readonly SHM clients do not mutate persistent WAL state.

Non-overlap: this does not repeat accepted pager/WAL mode/persist coverage in `SQLiteRealUpstreamPagerWalModePersistDynamicTest.php`, WAL byte truncation, VFS savepoint rollback, rollback-journal apply/commit, checkpoint transaction wrappers, or metadata-only runner admission. The new coverage builds valid WAL byte streams with native checksums and exercises `SQLiteWal::transactionRecoveryBoundary()`, committed reader snapshots, checkpoint image sizing, corrupt/truncated tail handling, and persistent WAL file-control state.

Dependency closure: no new support component is needed. The batch reuses existing native PHP WAL parsing/recovery and VFS file-control persistence primitives.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalDynamicMatrixTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalDynamicMatrixTest.php`
  - `1 test files, 11009 assertions, 0 failures`
- Root harness: not run - isolated micro-slice.
