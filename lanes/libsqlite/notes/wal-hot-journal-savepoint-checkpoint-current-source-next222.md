# WAL Hot-Journal Savepoint Checkpoint Current Source Next222

Adds a current-source ticket seal after next221 sidecar retirement. The plan seals the ticket only when database, WAL, hot-journal, and SHM ticket receipts all match the retired checkpoint source token, epoch, frame, cookie, sidecar-retired flag, and sync receipt.

Focused verification:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext222Test.php
php lanes/libsqlite/examples/wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next222.php --self-test
```

Non-overlap: next222 does not repeat next221 sidecar deletion, WAL restart, SHM readmark reset, reader admission, or checkpoint planning.

Dependency closure: no new support component needed; it reuses next221 sidecar-retirement token, checkpoint cookie, and ticket receipt metadata.
