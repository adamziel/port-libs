# WAL After-Current Checkpoint Stage Consolidation

This cleanup removes explicit numbered production wrappers for WAL
after-current checkpoint stages 612 through 723 from
`SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan`.

The canonical production entrypoint is now
`afterCurrentCheckpointStage($checkpointPlan, $checkpointReceipts, $stage)`.
The old stage numbers remain data inputs, and the accepted verification step
strings are preserved in the canonical stage map so receipt/action metadata
does not change.

Focused verification:

- `php -l lanes/libsqlite/src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext612627Test.php`
- `php -l lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext628643Test.php`
- `php -l lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext644659Test.php`
- `php -l lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext660675Test.php`
- `php -l lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext676691Test.php`
- `php -l lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext692707Test.php`
- `php -l lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext708723Test.php`
- `php -l lanes/libsqlite/examples/application-wal-hot-journal-savepoint-checkpoint-current-source-next627.php`
- `php -l lanes/libsqlite/examples/application-wal-hot-journal-savepoint-checkpoint-current-source-next643.php`
- `php -l lanes/libsqlite/examples/application-wal-hot-journal-savepoint-checkpoint-current-source-next659.php`
- `php -l lanes/libsqlite/examples/application-wal-hot-journal-savepoint-checkpoint-current-source-next675.php`
- `php -l lanes/libsqlite/examples/application-wal-hot-journal-savepoint-checkpoint-current-source-next691.php`
- `php -l lanes/libsqlite/examples/application-wal-hot-journal-savepoint-checkpoint-current-source-next707.php`
- `php -l lanes/libsqlite/examples/application-wal-hot-journal-savepoint-checkpoint-current-source-next723.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext612627Test.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext628643Test.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext644659Test.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext660675Test.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext676691Test.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext692707Test.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext708723Test.php` -> `7 test files, 494 assertions, 0 failures`
- `php tools/run-tests.php $(find lanes/libsqlite/tests -name 'SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext*Test.php' | sort)` -> `2 test files, 10659 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-wal-hot-journal-savepoint-checkpoint-current-source-next627.php`
- `php lanes/libsqlite/examples/application-wal-hot-journal-savepoint-checkpoint-current-source-next643.php`
- `php lanes/libsqlite/examples/application-wal-hot-journal-savepoint-checkpoint-current-source-next659.php`
- `php lanes/libsqlite/examples/application-wal-hot-journal-savepoint-checkpoint-current-source-next675.php`
- `php lanes/libsqlite/examples/application-wal-hot-journal-savepoint-checkpoint-current-source-next691.php`
- `php lanes/libsqlite/examples/application-wal-hot-journal-savepoint-checkpoint-current-source-next707.php --self-test` -> `application-wal-hot-journal-savepoint-checkpoint-current-source-next707 self-test passed`
- `php lanes/libsqlite/examples/application-wal-hot-journal-savepoint-checkpoint-current-source-next723.php --self-test` -> `application-wal-hot-journal-savepoint-checkpoint-current-source-next723 self-test passed`

Dependency closure: no new support component is needed; this reuses the
existing WAL checkpoint receipt planner and only consolidates production
entrypoint naming.
