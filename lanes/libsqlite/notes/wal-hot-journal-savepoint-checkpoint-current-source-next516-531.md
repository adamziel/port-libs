# WAL hot-journal checkpoint current-source next516-531

Scope: lane-local WAL checkpoint/hot-journal source follow-on after merged next500-515.

This slice extends the after-current checkpoint receipt chain through next516-next531. The first block verifies restart-salt schema generation, reader-mark schema generation, page-cache commit generation, schema-cookie checkpoint frame, commit-generation source token, hot-journal checkpoint frame, WAL-index checkpoint frame, and the next516-523 seal. The second block verifies restart-salt database-header sync, reader-mark WAL-index salt sync, page-cache reader release, schema-cookie hot-journal absence, commit-generation database-header sync, hot-journal WAL-index salt sync, WAL-index reader release, and the final next524-531 seal.

No new support component is introduced. The implementation reuses `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpoint()` and keeps the existing receipt contract: source token, database digest, page-cache digest, commit generation, schema cookie, checkpoint frame, database header sync, WAL-index salt sync, released reader marks, and absent hot journal.

Focused coverage:

- `SQLiteWalHotJournalSavepointCheckpointAfterCurrentStages516531Test.php` chains next516 through next531 from next515.
- The same test blocks source-token mismatch, unreleased reader marks, missing next522 base for the next523 seal, unsynced database header, unsynced WAL-index salt, visible hot journal, and duplicate final seal receipts.
- `wordpress-wal-hot-journal-savepoint-checkpoint-after-current-stage-531.php` provides a WordPress-shaped example for the final seal.

Non-overlap: this stays inside the WAL checkpoint/hot-journal current-source receipt chain and does not repeat next500-515, upstream suite evidence, status/dashboard artifacts, SQL, JSON, B-tree, VFS writer application, planner, or unrelated pager surfaces.
