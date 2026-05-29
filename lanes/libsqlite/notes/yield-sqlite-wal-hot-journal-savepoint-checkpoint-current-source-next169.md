# WAL Hot-Journal Savepoint Checkpoint Current Source Next169

## Behavior

Adds `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan`, a current-source crash-resume fence for the existing next165 hot-journal + WAL savepoint + checkpoint publish sequence.

The plan classifies partial VFS publish progress by completed operation reason and reports the next idempotent resume action. It keeps the rollback journal recoverable until the current-source checkpoint database bytes and retained WAL bytes are durable, admits reader release only after the hot journal is deleted, and admits WAL reset/restart only after the released checkpoint database payload has been written.

## Evidence

Focused commands run:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext169Test.php
# Focused test run: 1 selected test files (root lock skipped)
# 1 test files, 67 assertions, 0 failures

php lanes/libsqlite/examples/wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next169.php
# wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next169 self-test passed

php -l lanes/libsqlite/src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php
# No syntax errors detected in lanes/libsqlite/src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php

php -l lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext169Test.php
# No syntax errors detected in lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext169Test.php

php -l lanes/libsqlite/examples/wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next169.php
# No syntax errors detected in lanes/libsqlite/examples/wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next169.php

git diff --check -- lanes/libsqlite
```

Lane-local `phpPass` delta: `76154 -> 76221` (`+67`). Mapped upstream coverage remains `611 / 1589`.

## Non-Overlap

This extends next165 publish payload ordering with crash-resume admission fences. It does not repeat accepted WAL byte truncation, VFS writer/apply, rollback-journal commit, super-journal commit, checkpoint transaction, reader-token admission, or hot-journal current-source checkpoint payload generation.

## Dependency Closure

No new support component is needed. The slice reuses native PHP hot-journal recovery, WAL savepoint truncation, checkpoint payloads, and VFS publish operation metadata.
