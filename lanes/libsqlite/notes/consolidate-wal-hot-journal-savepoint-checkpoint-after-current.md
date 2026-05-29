# SQLite WAL hot-journal savepoint checkpoint after-current consolidation

This slice consolidates the numbered after-current checkpoint receipt wrappers for the 996-1011 stage window into the public `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterReadyCheckpointVerification()` entrypoint.

No new numbered source class, helper, or compatibility shim is introduced. The implementation reuses the existing after-current checkpoint receipt contract: source token, database digest, page-cache digest, commit generation, schema cookie, checkpoint frame, database header sync, WAL-index salt sync, released reader marks, and absent hot journal.

Focused coverage:

- `SQLiteWalHotJournalSavepointCheckpointAfterCurrentCheckpointTest.php` chains the 996-1011 receipt stages from the prior handoff and preserves the earlier receipt handoff.
- `wordpress-wal-hot-journal-savepoint-checkpoint-after-current.php --self-test` verifies the example receipt seal through the canonical entrypoint.

Recommended next slice: continue removing the remaining numbered WAL after-current checkpoint wrappers and migrate their direct tests/examples to `afterReadyCheckpointVerification()`.
