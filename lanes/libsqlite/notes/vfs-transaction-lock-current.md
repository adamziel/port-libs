# VFS Transaction Lock Current

## Scope

- Added `SQLiteVfsTransactionLockPlan` as a stable, unsuffixed production helper under `lanes/libsqlite/src`.
- Composes existing native PHP `SQLiteTransactionBeginLockPlan`, `SQLiteLockByteRangePlan`, and `SQLiteVfsLockState`.
- Covers deferred BEGIN first-read shared locks, BEGIN IMMEDIATE writer reservation, rollback-journal commit promotion to exclusive, WAL exclusive-as-immediate reservation, exclusive `PRAGMA locking_mode`, read-only write blockers, and commit/rollback release.

## Application Smoke

- Added `examples/application-vfs-transaction-lock-current.php`.
- Models a copied Application SQLite import where a CLI export holds a deferred shared read lock, an admin import reserves the writer slot with `BEGIN IMMEDIATE`, commit promotion waits for the reader to finish, then the exclusive rollback-journal commit lock is released.

## Verification

- `php -l lanes/libsqlite/src/SQLiteVfsTransactionLockPlan.php && php -l lanes/libsqlite/tests/SQLiteVfsTransactionLockPlanTest.php && php -l lanes/libsqlite/examples/application-vfs-transaction-lock-current.php`
  - No syntax errors detected in all three changed PHP files.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsTransactionLockPlanTest.php`
  - Focused test run: 1 selected test files (root lock skipped)
  - 5 PASS lines
  - 1 test files, 72 assertions, 0 failures
- `php lanes/libsqlite/examples/application-vfs-transaction-lock-current.php --self-test`
  - `application-vfs-transaction-lock-current self-test passed`

## Non-Overlap

- Does not reintroduce numbered production classes or compatibility shims.
- Avoids accepted VFS lock byte ranges, process file locks, locked writer, sync apply, rollback-journal apply/commit, WAL byte truncation, checkpoint transaction, and file-writer clusters.
- This slice wires transaction-level BEGIN/first-read/commit-promotion/release state over the existing lock planner instead of duplicating byte-range or writer application behavior.

## Dependency Closure

- No new support component is needed. The slice reuses native PHP BEGIN lock planning, byte-range lock planning, and in-memory VFS lock-state application.
