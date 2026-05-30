# SQLite WAL hot-journal savepoint checkpoint current-source next836-851

This slice extends the consolidated `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpoint()` receipt chain from the completed next820-835 handoff through next851.

No new numbered source class is introduced. The implementation reuses the existing local shared after-current checkpoint receipt contract: source token, database digest, page-cache digest, commit generation, schema cookie, checkpoint frame, database header sync, WAL-index salt sync, released reader marks, and absent hot journal.

Focused coverage:

- `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext836851Test.php` chains next836 through next851 from next835 and preserves the next820-835 receipt chain.
- `application-wal-hot-journal-savepoint-checkpoint-current-source-next851.php --self-test` verifies the example receipt seal.
