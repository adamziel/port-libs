# WAL Hot-Journal Savepoint Checkpoint Current Source Next159

Implemented `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan` for the Application import path where a hot rollback journal is recovered, the current savepoint retry is rolled back against the current WAL source, checkpoint materialization preserves an uncommitted WAL tail, and the next WAL generation is kept separate for subsequent readers.

Focused evidence:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext159Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 79 assertions, 0 failures
```

Application smoke:

```text
php lanes/libsqlite/examples/application-wal-hot-journal-savepoint-checkpoint-current-source-next159.php
```

Non-overlap: this slice avoids accepted WAL byte truncation, rollback-journal apply, checkpoint transaction, VFS savepoint rollback application, and next148 WAL source separation by asserting checkpoint materialization on the rolled-back hot-journal current source before installing the next WAL generation.

Dependency closure: no new support component is needed; the patch reuses native PHP hot-journal current-source recovery, WAL reader snapshots, savepoint page images, and durable checkpoint helpers.
