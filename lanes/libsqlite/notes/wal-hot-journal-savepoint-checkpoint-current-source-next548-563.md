# WAL hot-journal checkpoint current-source next548-563

Scope: lane-local WAL checkpoint/hot-journal source follow-on after merged next532-547.

This slice extends the after-current checkpoint receipt chain through next548-next563. The first block verifies restart-salt database digest, reader-mark source token, page-cache commit generation, schema-cookie checkpoint frame, commit-generation database-header sync, hot-journal WAL-index salt, WAL-index reader release, and the next548-555 seal. The second block verifies restart-salt checkpoint frame, reader-mark page-cache digest, page-cache database-header sync, schema-cookie WAL-index salt, commit-generation reader release, hot-journal source token, WAL-index database digest, and the final next556-563 seal.

No new support component is introduced. The implementation reuses `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpoint()` and keeps the existing receipt contract: source token, database digest, page-cache digest, commit generation, schema cookie, checkpoint frame, database header sync, WAL-index salt sync, released reader marks, and absent hot journal.

Focused coverage:

- `SQLiteWalHotJournalSavepointCheckpointAfterCurrentStages548563Test.php` chains next548 through next563 from next547.
- The same test blocks page-cache mismatch, checkpoint-frame mismatch, missing next554 base for the next555 seal, unsynced database header, visible hot journal, and duplicate final seal receipts.
- `wordpress-wal-hot-journal-savepoint-checkpoint-after-current-stage-563.php` provides a WordPress-shaped example for the final seal.

Non-overlap: this stays inside the WAL checkpoint/hot-journal current-source receipt chain and does not repeat next532-547, upstream suite evidence, status/dashboard artifacts, SQL, JSON, B-tree, VFS writer application, planner, or unrelated pager surfaces.
