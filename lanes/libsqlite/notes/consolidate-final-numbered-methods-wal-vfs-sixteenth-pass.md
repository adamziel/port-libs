2026-05-29T12:44Z - consolidate-final-numbered-methods-wal-vfs-sixteenth-pass

Scope:
- Consolidated the WAL hot-journal savepoint checkpoint `next1156` through `next1171` production wrappers into `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterReadyCheckpointVerification()`.
- Updated the direct focused test and WordPress smoke to call the canonical descriptive entrypoint instead of numbered production methods.
- No compatibility shims were left for the removed numbered production method names.

Verification:
- `php -l lanes/libsqlite/src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext11561171Test.php`
- `php -l lanes/libsqlite/examples/wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next1171.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext11561171Test.php` -> `1 test files, 76 assertions, 0 failures`
- `php lanes/libsqlite/examples/wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next1171.php --self-test`

Dependency closure:
- No new support component needed; this is a production API consolidation over the existing WAL checkpoint verification helper.
