# WAL hot-journal checkpoint current-source next436-451

Scope: lane-local WAL checkpoint/hot-journal source follow-on after the merged next420-435 chain.

This slice extends the after-current checkpoint receipt chain through next436-next451. The first block verifies restart-salt receipt database digest, reader-mark release database digest, page-cache digest source token, schema-cookie source token, commit-generation frame digest, hot-journal absence source token, WAL-index salt frame digest, and the next436-443 seal. The second block verifies restart-salt receipt schema cookie, reader-mark release schema cookie, page-cache digest frame, schema-cookie digest frame, commit-generation source frame, hot-journal delete source frame, WAL-index salt source frame, and the final next444-451 seal.

No new support component is introduced. The implementation reuses `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpoint()` and keeps the existing receipt contract: source token, database digest, page-cache digest, commit generation, schema cookie, checkpoint frame, database header sync, WAL-index salt sync, released reader marks, and absent hot journal.

Focused coverage:

- `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext436451Test.php` chains next436 through next451 from next435.
- The same test blocks database-digest mismatch, checkpoint-frame mismatch, missing next442 base for the next443 seal, unreleased reader marks, commit-generation mismatch, and duplicate final seal receipts.
- `application-wal-hot-journal-savepoint-checkpoint-current-source-next451.php` provides a Application-shaped example for the final seal.

Non-overlap: this stays inside the WAL checkpoint/hot-journal current-source receipt chain and does not repeat next420-435, upstream suite evidence, status/dashboard artifacts, SQL, JSON, B-tree, VFS writer application, planner, or unrelated pager surfaces.
