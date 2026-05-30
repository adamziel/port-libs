# WAL Hot-Journal Savepoint Checkpoint Current Source Next220

Adds a reader-reopen fence after next219 checkpoint source publication. The plan admits reopened Application readers only when their source token, epoch, checkpoint frame, checkpoint cookie, schema cookie, hot-journal visibility, cache reopen state, and savepoint depth match the published checkpoint source.

Focused verification:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext220Test.php
php lanes/libsqlite/examples/application-wal-hot-journal-savepoint-checkpoint-current-source-next220.php --self-test
```

Non-overlap: next220 does not repeat next219 savepoint-scope finalization, next217 durable reader admission, WAL byte truncation, rollback-journal apply, or checkpoint transaction planning.

Dependency closure: no new support component needed; it reuses next219 source publication, checkpoint/schema cookies, and reader reopen receipts.
