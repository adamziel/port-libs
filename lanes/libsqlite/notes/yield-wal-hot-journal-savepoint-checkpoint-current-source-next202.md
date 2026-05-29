# WAL Hot-Journal Savepoint Checkpoint Current Source Next202

## Behavior

Adds `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan`, a current-source admission guard that sits after accepted next196 WAL sidecar publication. It admits statement, reader, and writer handles only when the checkpointed database digest, persisted WAL sidecar digest, sidecar publication digest, absent hot-journal bytes, released savepoint, exclusive checkpoint lock receipt, and database/WAL/directory sync receipts all line up.

## Verification

Passed in this lane:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext202Test.php` - `1 test files, 61 assertions, 0 failures`
- `php lanes/libsqlite/examples/wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next202.php` - self-test passed and emitted `wal-hot-journal-savepoint-checkpoint-current-source-next202`
- `php -l lanes/libsqlite/src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php && php -l lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext202Test.php && php -l lanes/libsqlite/examples/wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next202.php` - no syntax errors
- `php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'` - `lane-status json ok`
- `git diff --check -- lanes/libsqlite` - clean

## Non-Overlap

This slice does not repeat accepted WAL byte truncation, rollback-journal apply, VFS savepoint rollback, checkpoint transaction planning, VFS sync apply, next196 sidecar digest classification, or earlier hot-journal reader retry/cache admission. It adds the file-receipt and handle-admission layer after those behaviors.

## Dependency Closure

No new support component is needed. The slice reuses accepted WAL sidecar publication and lane-local file receipt, lock receipt, sync receipt, and handle cache metadata.
