# SQLite WAL hot-journal savepoint checkpoint current-source next980-995

This slice extends the consolidated `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpoint()` receipt chain from next979 through next995.

No new numbered source class is introduced. The implementation reuses the existing after-current checkpoint receipt contract: source token, database digest, page-cache digest, commit generation, schema cookie, checkpoint frame, database header sync, WAL-index salt sync, released reader marks, and absent hot journal.

Focused coverage:

- `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext980995Test.php` chains next980 through next995 from next979 and preserves the next964-979 receipt handoff.
- `wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next995.php --self-test` verifies the example receipt seal.

Recommended next slice: continue with `wal996-1011` on the same consolidated after-current checkpoint receipt chain.
