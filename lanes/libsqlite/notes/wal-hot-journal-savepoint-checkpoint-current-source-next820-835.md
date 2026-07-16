# SQLite WAL hot-journal savepoint checkpoint current-source next820-835

This slice extends the consolidated `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpoint()` receipt chain from the completed next804-819 handoff through next835.

No new numbered source class is introduced. The implementation reuses the existing local shared after-current checkpoint receipt contract: source token, database digest, page-cache digest, commit generation, schema cookie, checkpoint frame, database header sync, WAL-index salt sync, released reader marks, and absent hot journal.

Focused coverage:

- `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext820835Test.php` chains next820 through next835 from next819 and preserves the next804-819 receipt chain.
- `application-wal-hot-journal-savepoint-checkpoint-current-source-next835.php --self-test` verifies the example receipt seal.
