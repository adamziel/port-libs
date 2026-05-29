# WAL hot-journal checkpoint current-source next532-547

Scope: lane-local WAL checkpoint/hot-journal source follow-on after merged next516-531.

This slice extends the after-current checkpoint receipt chain through next532-next547. The first block verifies restart-salt source token, reader-mark database digest, page-cache schema cookie, schema-cookie commit generation, commit-generation checkpoint frame, hot-journal database digest, WAL-index source token, and the next532-539 seal. The second block verifies restart-salt reader release, reader-mark checkpoint frame, page-cache WAL-index salt sync, schema-cookie database-header sync, commit-generation hot-journal absence, hot-journal reader release, WAL-index database-header sync, and the final next540-547 seal.

No new support component is introduced. The implementation reuses `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpoint()` and keeps the existing receipt contract: source token, database digest, page-cache digest, commit generation, schema cookie, checkpoint frame, database header sync, WAL-index salt sync, released reader marks, and absent hot journal.

Focused coverage:

- `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext532547Test.php` chains next532 through next547 from next531.
- The same test blocks database-digest mismatch, commit-generation mismatch, missing next538 base for the next539 seal, unsynced WAL-index salt, unsynced database header, unreleased reader marks, and duplicate final seal receipts.
- `wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next547.php` provides a WordPress-shaped example for the final seal.

Non-overlap: this stays inside the WAL checkpoint/hot-journal current-source receipt chain and does not repeat next516-531, upstream suite evidence, status/dashboard artifacts, SQL, JSON, B-tree, VFS writer application, planner, or unrelated pager surfaces.
