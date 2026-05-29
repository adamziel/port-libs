# WAL hot-journal checkpoint current-source next420-435

Scope: lane-local WAL checkpoint/hot-journal source follow-on after the merged next404-419 chain.

This slice extends the after-current checkpoint receipt chain through next420-next435. The first block verifies restart-salt receipt page-cache digest, reader-mark release page-cache digest, page-cache digest generation, schema-cookie generation, commit-generation schema cookie, hot-journal delete schema cookie, WAL-index salt schema cookie, and the next420-427 seal. The second block verifies restart-salt receipt generation, reader-mark release generation, page-cache digest schema cookie, schema-cookie source frame, commit-generation source digest, hot-journal absence generation, WAL-index salt generation, and the final next428-435 seal.

No new support component is introduced. The implementation reuses `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpoint()` and keeps the existing receipt contract: source token, database digest, page-cache digest, commit generation, schema cookie, checkpoint frame, database header sync, WAL-index salt sync, released reader marks, and absent hot journal.

Focused coverage:

- `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext420435Test.php` chains next420 through next435 from next419.
- The same test blocks page-cache digest mismatch, schema-cookie mismatch, missing next426 base for the next427 seal, visible hot journal, source-token mismatch, and duplicate final seal receipts.
- `wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next435.php` provides a WordPress-shaped example for the final seal.

Non-overlap: this stays inside the WAL checkpoint/hot-journal current-source receipt chain and does not repeat next404-419, suite/status/dashboard artifacts, SQL, JSON, B-tree, VFS writer application, planner, or unrelated pager surfaces.
