# WAL Hot-Journal Savepoint Checkpoint Current Source Next242

## Behavior

Adds the first post-publication writer-commit fence after the accepted next238 writer admission. The plan publishes the next current-source generation only when WAL commit, database backfill, directory sync, and reader-generation receipts all match the admitted writer generation, checkpoint database digest, schema cookie, WAL salt, and covered page set.

Blocked receipts hold the current source when a hot journal is visible, a savepoint scope remains open, reader cache is dirty, WAL frames do not follow the clean restart, sync/backfill receipts are missing, or any source token, path, digest, schema cookie, salt, page, or generation is stale.

## Evidence

- Focused test: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext242Test.php`
- Application smoke: `php lanes/libsqlite/examples/application-wal-hot-journal-savepoint-checkpoint-current-source-next242.php --self-test`

## Non-Overlap

This slice validates writer commit receipts after next238 writer admission. It does not repeat restart/truncate reset admission, durable publication receipt validation, reader reopen admission, WAL byte truncation, rollback-journal apply/commit, VFS writer apply, checkpoint transactions, or next223 publication receipt fences.

## Dependency Closure

No new support component is needed. The slice reuses existing next238 writer admission metadata plus WAL frame, database backfill, directory sync, and reader generation receipts.
