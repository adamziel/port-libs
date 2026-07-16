# Application Import Transaction Current Next21

Status: focused PHP corpus growth for copied `wp_options` current import transaction planning.

Behavior:

- Added `SQLiteImportTransactionPlan` for bounded current-row Application option imports under `BEGIN IMMEDIATE` / `BEGIN EXCLUSIVE`.
- The plan computes staged updates, inserts, optional delete-missing cleanup, unique `option_name` replace-conflict deletion, final row images, dirty leaf pages, rollback-journal byte estimates, and VFS sync sequence targets.
- Added `application-import-transaction-current-next21.php` to smoke copied `wp_options` current import rows without requiring `ext/sqlite`.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteImportTransactionCurrentNext21Test.php`
- Result: `1 test files, 56 assertions, 0 failures` with 56 PASS lines.
- `php lanes/libsqlite/examples/application-import-transaction-current-next21.php`

Dashboard:

- `phpPass` moves by the verified focused PASS-line delta: `7262 -> 7318`.
- `benchmarkDenominator.mapped` unchanged; this is lane-scoped PHP behavior coverage over existing transaction/VFS concepts, not a newly mapped upstream inventory unit.

Non-overlap:

- Avoids accepted rollback-journal commit/apply, savepoint rollback, WAL byte truncation, VFS sync apply, super-journal, process locks, SELECT SQL, JSON table, Unicode GLOB, and B-tree page/freelist clusters.
- This slice is bounded to Application current import transaction row planning and conflict/delete-missing effects before native file-handle application.

Dependency closure:

- No new support component is needed. The implementation reuses bounded `SQLiteTransactionBeginLockPlan` and `SQLiteVfsSyncPlan` primitives.

Next:

- Wire this transaction plan to a native pager/VFS apply path once the current import executor owns real table/index page images.
