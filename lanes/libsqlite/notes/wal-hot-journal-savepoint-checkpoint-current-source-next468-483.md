# WAL hot-journal checkpoint current-source next468-483

Scope: lane-local WAL checkpoint/hot-journal source follow-on after merged next452-467.

This slice extends the after-current checkpoint receipt chain through next468-next483. The first block verifies restart-salt receipt frame digest, reader-mark release frame digest, page-cache digest generation frame, schema-cookie database digest, commit-generation schema cookie, hot-journal absence frame digest, WAL-index salt source token, and the next468-475 seal. The second block verifies restart-salt receipt database header sync, reader-mark release WAL-index salt sync, page-cache digest reader release, schema-cookie hot-journal absence, commit-generation database header sync, hot-journal delete WAL-index salt sync, WAL-index salt reader release, and the final next476-483 seal.

No new support component is introduced. The implementation reuses `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpoint()` and keeps the existing receipt contract: source token, database digest, page-cache digest, commit generation, schema cookie, checkpoint frame, database header sync, WAL-index salt sync, released reader marks, and absent hot journal.

Focused coverage:

- `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext468483Test.php` chains next468 through next483 from next467.
- The same test blocks checkpoint-frame mismatch, database-digest mismatch, missing next474 base for the next475 seal, unsynced WAL-index salt, unreleased reader marks, commit-generation mismatch, and duplicate final seal receipts.
- `application-wal-hot-journal-savepoint-checkpoint-current-source-next483.php` provides a Application-shaped example for the final seal.

Non-overlap: this stays inside the WAL checkpoint/hot-journal current-source receipt chain and does not repeat next452-467, upstream suite evidence, status/dashboard artifacts, SQL, JSON, B-tree, VFS writer application, planner, or unrelated pager surfaces.
