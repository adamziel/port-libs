# Consolidate Final Numbered Methods WAL/VFS Ninetieth Pass

Consolidated the WAL hot-journal savepoint checkpoint `next1124AfterCurrentCheckpoint()` through `next1139AfterCurrentCheckpoint()` production wrappers into the stable `afterReadyCheckpointVerification()` entry point. The direct test now passes the same stage numbers and verification-step strings to the canonical method, preserving the generated status, reason, dependency, operation, and receipt values.

The affected WAL family also exposed a stale `next175Plan()` generated-key split: production had moved the page-cache seal surface to publish-apply keys while direct tests and the Application smoke still consumed the accepted `next175` keys. The canonical implementation now returns both key sets and preserves the legacy `next175` status/metadata aliases.

Verification:

- `php -l lanes/libsqlite/src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext11241139Test.php`
- `php -l lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext916931Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext11241139Test.php` -> `1 test files, 78 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext175Test.php` -> `1 test files, 22 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext916931Test.php` -> `1 test files, 78 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext*Test.php` -> `2 test files, 11236 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-wal-hot-journal-savepoint-checkpoint-current-source-next175.php --self-test`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component is needed; this reuses the existing WAL checkpoint receipt and page-cache seal implementation.

Non-overlap: this consolidation only removes thin WAL wrapper methods for stages 1124-1139 and restores accepted `next175` observable aliases. It does not repeat pager-master reader-cache, VFS lock/file-writer, WAL byte truncation, rollback-journal apply, or checkpoint transaction behavior.
