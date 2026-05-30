# SQLite WAL hot-journal savepoint checkpoint current-source next932-947

This slice extends the consolidated `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpoint()` receipt chain from next931 through next947.

No new numbered source class is introduced. The implementation reuses the existing after-current checkpoint receipt contract: source token, database digest, page-cache digest, commit generation, schema cookie, checkpoint frame, database header sync, WAL-index salt sync, released reader marks, and absent hot journal.

Focused coverage:

- `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext932947Test.php` chains next932 through next947 from next931 and preserves the next916-931 receipt chain.
- `application-wal-hot-journal-savepoint-checkpoint-current-source-next947.php --self-test` verifies the example receipt seal.
