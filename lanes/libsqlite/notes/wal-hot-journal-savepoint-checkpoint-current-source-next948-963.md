# SQLite WAL hot-journal savepoint checkpoint current-source next948-963

This slice extends the consolidated `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpoint()` receipt chain from next947 through next963.

No new numbered source class is introduced. The implementation reuses the existing after-current checkpoint receipt contract: source token, database digest, page-cache digest, commit generation, schema cookie, checkpoint frame, database header sync, WAL-index salt sync, released reader marks, and absent hot journal.

Focused coverage:

- `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext948963Test.php` chains next948 through next963 from next947 and preserves the next932-947 receipt handoff.
- `application-wal-hot-journal-savepoint-checkpoint-current-source-next963.php --self-test` verifies the example receipt seal.

Recommended next slice: continue with `wal964-979` on the same consolidated after-current checkpoint receipt chain.
