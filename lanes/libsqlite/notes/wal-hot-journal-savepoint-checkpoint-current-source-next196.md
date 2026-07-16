# WAL Hot-Journal Savepoint Checkpoint Current Source Next196

## Behavior

Adds `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan`, a
post-next192 WAL sidecar publication guard. After hot-journal recovery,
savepoint rollback, and checkpoint page-image publication, the plan verifies
that the persisted `-wal` sidecar matches the selected checkpoint mode:

- `restart` requires a restarted empty WAL header with the next checkpoint
  sequence and changed salt.
- `truncate` requires an empty sidecar and rejects caches that still require a
  WAL sidecar.
- `preserve_busy` requires a reader-pinned current WAL sidecar.

Prepared statement and reader cache rows are admitted only when their observed
WAL sidecar digest matches the durable sidecar publication state.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext196Test.php`
  - `1 test files, 65 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-wal-hot-journal-savepoint-checkpoint-current-source-next196.php`
  - JSON self-test reports status
    `wal-hot-journal-savepoint-checkpoint-current-source-next196`

## Non-Overlap

This slice composes the accepted next192 checkpoint page-image guard and adds
WAL sidecar publication admission. It does not repeat next192 page digest
checks, next188 commit-hook/schema-cookie checks, next185 WAL generation
checks, VFS savepoint rollback, rollback-journal apply, VFS sync/write wrappers,
or WAL byte truncation planning.

## Dependency Closure

No new support component is needed. The implementation reuses the native WAL
parser/checksum validator, durable checkpoint result, and existing
current-source admission chain.
