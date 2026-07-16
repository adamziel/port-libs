# WAL hot-journal checkpoint current-source next484-499

Scope: lane-local WAL checkpoint/hot-journal source follow-on after merged next468-483.

This slice extends the after-current checkpoint receipt chain through next484-next499. The first block verifies restart-salt source frame, reader-mark source frame, page-cache database-header sync, schema-cookie WAL-index salt sync, commit-generation reader release, hot-journal absence database-header sync, WAL-index salt page-cache digest, and the next484-491 seal. The second block verifies restart-salt schema generation, reader-mark schema generation, page-cache commit generation, schema-cookie checkpoint frame, commit-generation source token, hot-journal checkpoint frame, WAL-index checkpoint frame, and the final next492-499 seal.

No new support component is introduced. The implementation reuses `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpoint()` and keeps the existing receipt contract: source token, database digest, page-cache digest, commit generation, schema cookie, checkpoint frame, database header sync, WAL-index salt sync, released reader marks, and absent hot journal.

Focused coverage:

- `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext484499Test.php` chains next484 through next499 from next483.
- The same test blocks source-token mismatch, page-cache digest mismatch, missing next490 base for the next491 seal, unsynced database header, schema-cookie mismatch, visible hot journal, and duplicate final seal receipts.
- `application-wal-hot-journal-savepoint-checkpoint-current-source-next499.php` provides a Application-shaped example for the final seal.

Non-overlap: this stays inside the WAL checkpoint/hot-journal current-source receipt chain and does not repeat next468-483, upstream suite evidence, status/dashboard artifacts, SQL, JSON, B-tree, VFS writer application, planner, or unrelated pager surfaces.
