# WAL hot-journal checkpoint current-source next388-403

Scope: lane-local WAL checkpoint/hot-journal source follow-on after the merged next380-387 chain.

This slice extends the after-current checkpoint receipt chain through next388-next403. The first block verifies restart-salt receipt generation, reader-mark release generation, page-cache source-token generation, schema-cookie source generation, commit-generation source token, hot-journal absence generation, WAL-index salt generation, and the next388-395 seal. The second block verifies restart-salt receipt source token, reader-mark release source token, page-cache digest generation, schema-cookie digest generation, commit-generation digest source, hot-journal delete digest generation, WAL-index salt digest generation, and the final next396-403 seal.

No new support component is introduced. The implementation reuses `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpoint()` and keeps the existing receipt contract: source token, database digest, page-cache digest, commit generation, schema cookie, checkpoint frame, database header sync, WAL-index salt sync, released reader marks, and absent hot journal.

Focused coverage:

- `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext388403Test.php` chains next388 through next403 from next387.
- The same test blocks unsynced WAL-index salt, source-token mismatch, visible hot journal, missing next394 base for the next395 seal, page-cache digest mismatch, schema-cookie mismatch, and duplicate final seal receipts.
- `wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next403.php` provides a WordPress-shaped example for the final seal.

Non-overlap: this stays inside the WAL checkpoint/hot-journal current-source receipt chain and does not repeat next380-387, suite/status/dashboard artifacts, SQL, JSON, B-tree, VFS writer application, planner, or unrelated pager surfaces.
