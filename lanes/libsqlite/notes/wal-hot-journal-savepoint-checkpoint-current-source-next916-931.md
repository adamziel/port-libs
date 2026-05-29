# SQLite WAL hot-journal savepoint checkpoint current-source next916-931

This slice extends the consolidated `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpoint()` receipt chain from next915 through next931.

No new numbered source class is introduced. The implementation reuses the existing after-current checkpoint receipt contract: source token, database digest, page-cache digest, commit generation, schema cookie, checkpoint frame, database header sync, WAL-index salt sync, released reader marks, and absent hot journal.

Focused coverage:

- `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext916931Test.php` chains next916 through next931 from next915 and preserves the next900-915 receipt chain.
- `wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next931.php --self-test` verifies the example receipt seal.
