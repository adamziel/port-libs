# WAL Hot-Journal Savepoint Checkpoint Current-Source Next184

## Behavior

Adds `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan`, a post-reopen WAL source fence for hot-journal recovery plus savepoint checkpoint publication. After next181 confirms the reopened files, this slice verifies that a retry WAL source has checksum-validated bytes, an advanced checkpoint sequence, a rotated salt pair, and reader pages separated from the pre-reopen WAL source before read marks can be reused.

This intentionally does not redo next178 receipt matching, next181 reopen validation, VFS writer/sync application, rollback-journal apply, or savepoint byte truncation.

## Evidence

Focused test:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext184Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 55 assertions, 0 failures
```

Application smoke:

```text
php lanes/libsqlite/examples/application-wal-hot-journal-savepoint-checkpoint-current-source-next184.php --self-test
application-wal-hot-journal-savepoint-checkpoint-current-source-next184 self-test passed
```

## Dependency Closure

No new support component is needed. The slice reuses native WAL checksum parsing and next181 reopen admission evidence.

## Expected Dashboard Movement

`phpPass` should increase by `+55` focused PASS lines from `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext184Test.php`. Mapped upstream coverage is unchanged because this slice does not claim a new manifest-backed upstream inventory row.
