# WAL hot-journal checkpoint current-source next500-515

Scope: lane-local WAL checkpoint/hot-journal source follow-on after merged next484-499.

This slice extends the after-current checkpoint receipt chain through next500-next515. The first block verifies restart-salt database-header sync, reader-mark WAL-index salt sync, page-cache reader release, schema-cookie hot-journal absence, commit-generation database-header sync, hot-journal WAL-index salt sync, WAL-index reader release, and the next500-507 seal. The second block verifies restart-salt source frame, reader-mark source frame, page-cache database-header sync, schema-cookie WAL-index salt sync, commit-generation reader release, hot-journal absence database-header sync, WAL-index page-cache digest, and the final next508-515 seal.

No new support component is introduced. The implementation reuses `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpoint()` and keeps the existing receipt contract: source token, database digest, page-cache digest, commit generation, schema cookie, checkpoint frame, database header sync, WAL-index salt sync, released reader marks, and absent hot journal.

Focused coverage:

- `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext500515Test.php` chains next500 through next515 from next499.
- The same test blocks source-token mismatch, unreleased reader marks, missing next506 base for the next507 seal, unsynced database header, unsynced WAL-index salt, visible hot journal, and duplicate final seal receipts.
- `wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next515.php` provides a WordPress-shaped example for the final seal.

Non-overlap: this stays inside the WAL checkpoint/hot-journal current-source receipt chain and does not repeat next484-499, upstream suite evidence, status/dashboard artifacts, SQL, JSON, B-tree, VFS writer application, planner, or unrelated pager surfaces.
