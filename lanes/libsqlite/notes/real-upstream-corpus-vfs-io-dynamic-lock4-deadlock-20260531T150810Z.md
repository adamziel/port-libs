# Real upstream VFS lock4 deadlock dynamic slice

## Scope

- Added `SQLiteTransactionBeginLockPlan::upstreamCrossDatabaseDeadlockProfile()` for hydrated upstream SQLite `test/lock4.test`.
- Ported upstream `lock4-1.1`, `lock4-1.2`, and `lock4-1.3` as a generic rollback-lock model: two databases are created, the parent holds an exclusive lock on the main database, the child holds an auxiliary-database transaction and waits on the main database, the parent auxiliary insert receives `database is locked`, then parent commit releases the child and the child commit leaves the auxiliary row visible.
- Preserved the upstream atomic-batch-write skip gate as an explicit skipped profile, matching `lock4.test` behavior when that VFS capability is available.
- Added 1000 focused dynamic variants over page size, initial row counts, busy timeout, and rollback journal mode. This is non-overlapping with existing VFS lock contention coverage because the previous focused family covered `lock.test`, `lock2.test`, `lock3.test`, `lock5.test`, and `lock7.test`, not the `lock4.test` parent/child cross-database deadlock sequence.

## Verification

- `php -l lanes/libsqlite/src/SQLiteTransactionBeginLockPlan.php`
  - `No syntax errors detected`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsLock4DeadlockDynamicTest.php`
  - `No syntax errors detected`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsLock4DeadlockDynamicTest.php`
  - `1 test files, 35015 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteTransactionBeginLockModeCorpusTest.php`
  - `1 test files, 30 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsLockContentionDynamicTest.php`
  - `1 test files, 12645 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 3 assertions, 0 failures`
- `git diff --check -- lanes/libsqlite`
  - Passed.

## Dependency Closure

No new support component is needed. The slice reuses the existing transaction-lock planning surface and records the remaining VFS behavior as generic rollback-lock state evidence against the hydrated SQLite upstream `lock4.test` source.
