# WAL hot-journal checkpoint current-source next564-579

Scope: lane-local WAL checkpoint/hot-journal source follow-on after merged next548-563.

This slice extends the after-current checkpoint receipt chain through next564-next579. The first block verifies restart-salt reader release, source-token page-cache digest, database-digest schema cookie, checkpoint-frame database-header sync, commit-generation WAL-index salt, hot-journal absence with reader release, page-cache WAL-index salt, and the next564-571 seal. The second block verifies restart-salt database-header sync, reader-mark checkpoint frame, database-digest commit generation, schema-cookie reader release, page-cache source token, hot-journal database digest, WAL-index checkpoint frame, and the final next572-579 seal.

No new support component is introduced. The implementation reuses `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpoint()` and keeps the existing receipt contract: source token, database digest, page-cache digest, commit generation, schema cookie, checkpoint frame, database header sync, WAL-index salt sync, released reader marks, and absent hot journal.

Focused coverage:

- `SQLiteWalHotJournalSavepointCheckpointAfterCurrentStages564579Test.php` chains next564 through next579 from next563.
- The same test blocks unreleased reader marks, schema-cookie mismatch, missing next570 base for the next571 seal, unsynced WAL-index salt, database-digest mismatch, and duplicate final seal receipts.
- `application-wal-hot-journal-savepoint-checkpoint-after-current-stage-579.php` provides a Application-shaped example for the final seal.

Non-overlap: this stays inside the WAL checkpoint/hot-journal current-source receipt chain and does not repeat next548-563, upstream suite evidence, status/dashboard artifacts, SQL, JSON, B-tree, VFS writer application, planner, or unrelated pager surfaces.
