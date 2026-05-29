# SQLite WAL hot-journal savepoint checkpoint current-source next964-979

This slice extends the consolidated `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpoint()` receipt chain from next963 through next979.

No new numbered source class is introduced. The implementation reuses the existing after-current checkpoint receipt contract: source token, database digest, page-cache digest, commit generation, schema cookie, checkpoint frame, database header sync, WAL-index salt sync, released reader marks, and absent hot journal.

Focused coverage:

- `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext964979Test.php` chains next964 through next979 from next963 and preserves the next948-963 receipt handoff.
- `wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next979.php --self-test` verifies the example receipt seal.

Recommended next slice: continue with `wal980-995` on the same consolidated after-current checkpoint receipt chain.
