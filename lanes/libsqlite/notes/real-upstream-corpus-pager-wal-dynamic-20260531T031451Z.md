# real-upstream-corpus-pager-wal-dynamic-20260531T031451Z

Implemented a focused real upstream WAL validation cluster from hydrated SQLite
`wal2.test` sections:

- `wal2-7.1.1` through `wal2-7.1.3`: copied WAL checksum corruption.
- `wal2-8.1.2` through `wal2-8.1.4`: recovered WAL header and readable WAL
  state.
- `wal2-9.1` through `wal2-9.4`: wal-index header copies disagree.
- `wal2-10.1.1` through `wal2-10.2.3`: unsupported WAL and wal-index format
  versions.
- `wal2-11.2` through `wal2-11.3`: malformed WAL frame payload.

Added `SQLitePagerWalDynamicPlan::walHeaderValidationScenario()` and
`SQLiteRealUpstreamCorpusPagerWalDynamic20260531T031451ZTest.php`.

Focused evidence:

- `php -l lanes/libsqlite/src/SQLitePagerWalDynamicPlan.php`:
  no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalDynamic20260531T031451ZTest.php`:
  no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalDynamic20260531T031451ZTest.php`:
  `1 test files, 12112 assertions, 0 failures`.

PASS-line movement: 1103 focused TestRunner PASS cases.

Non-overlap: avoids accepted WAL byte truncation, checkpoint transactions,
persistent close, rollback-journal apply/commit, VFS sync/file writer/lock,
pager1 boundary, wal2 readmark/header-recovery lock-race, file-permission,
readonly-SHM, and page-size mapping batches.

Dependency closure: no new support component needed; the slice reuses
`SQLitePagerWalDynamicPlan` and the hydrated upstream SQLite `wal2.test` file as
source truth.
