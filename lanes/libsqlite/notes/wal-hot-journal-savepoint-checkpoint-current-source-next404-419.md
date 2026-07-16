# WAL hot-journal checkpoint current-source next404-419

Scope: lane-local WAL checkpoint/hot-journal source follow-on after the merged next388-403 chain.

This slice extends the after-current checkpoint receipt chain through next404-next419. The first block verifies restart-salt receipt database digest, reader-mark release database digest, page-cache digest source token, schema-cookie source token, commit-generation frame digest, hot-journal absence source token, WAL-index salt frame digest, and the next404-411 seal. The second block verifies restart-salt receipt schema cookie, reader-mark release schema cookie, page-cache digest frame, schema-cookie digest frame, commit-generation source frame, hot-journal delete source frame, WAL-index salt source frame, and the final next412-419 seal.

No new support component is introduced. The implementation reuses `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpoint()` and keeps the existing receipt contract: source token, database digest, page-cache digest, commit generation, schema cookie, checkpoint frame, database header sync, WAL-index salt sync, released reader marks, and absent hot journal.

Focused coverage:

- `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext404419Test.php` chains next404 through next419 from next403.
- The same test blocks database-digest mismatch, unsynced database header, checkpoint-frame mismatch, missing next410 base for the next411 seal, unreleased reader marks, commit-generation mismatch, and duplicate final seal receipts.
- `application-wal-hot-journal-savepoint-checkpoint-current-source-next419.php` provides a Application-shaped example for the final seal.

Non-overlap: this stays inside the WAL checkpoint/hot-journal current-source receipt chain and does not repeat next388-403, suite/status/dashboard artifacts, SQL, JSON, B-tree, VFS writer application, planner, or unrelated pager surfaces.
