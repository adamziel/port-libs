# WAL Hot-Journal Savepoint Checkpoint Current Source Next223

## Behavior

Adds a post-reset publication receipt fence after the accepted next218 restart/truncate admission. The new plan advances the current-source epoch only when database, WAL, journal, and reader-cache receipts all match the checkpointed frame, writer generation, database digest, WAL digest, and writer digest.

Blocked receipts preserve the previous current-source epoch when a hot journal remains visible, a savepoint scope is still open, a reader cache is dirty, a sync receipt is missing, or any digest/frame/generation is stale.

## Evidence

- Focused test: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext223Test.php`
- Application smoke: `php lanes/libsqlite/examples/application-wal-hot-journal-savepoint-checkpoint-current-source-next223.php --self-test`

## Non-Overlap

This slice does not repeat next218 restart/truncate reset admission, next212 PASSIVE reader-pin frame accounting, next209 writer fences, WAL byte truncation, VFS savepoint rollback, rollback-journal commit/apply, or checkpoint transaction planning.

## Dependency Closure

No new support component is needed. The slice reuses existing next218 reset admission metadata, current-source digests, writer generation receipts, and reader-cache reopen fences.
