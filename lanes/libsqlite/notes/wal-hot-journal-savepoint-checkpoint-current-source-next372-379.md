# WAL hot-journal checkpoint current-source next372-379

Scope: lane-local WAL checkpoint/hot-journal source follow-on after the merged next364-371 chain.

This slice extends the after-current checkpoint receipt chain through next372-next379 without changing shared runner, dashboard, progress, lane-status, supervisor, or private state artifacts. The added steps verify restart-salt epoch receipt, reader-mark source receipt, page-cache generation receipt, schema-cookie source receipt, commit-generation source receipt, hot-journal delete epoch receipt, WAL-index salt source receipt, and the final current-source seal.

No new support component is introduced. The implementation reuses `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpoint()` and keeps the existing receipt contract: source token, database digest, page-cache digest, commit generation, schema cookie, checkpoint frame, database header sync, WAL-index salt sync, released reader marks, and absent hot journal.

Focused coverage:

- `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext372379Test.php` chains next372 through next379 from next371.
- The same test blocks unsynced WAL-index salt, unreleased reader marks, database digest mismatch, schema-cookie mismatch, visible hot journal, and a missing next378 base for the next379 seal.
- `wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next379.php` provides a WordPress-shaped example for the final seal.

Non-overlap: this stays inside the WAL checkpoint/hot-journal current-source receipt chain and does not repeat next364-371, suite/status/dashboard artifacts, SQL, JSON, B-tree, VFS writer application, planner, or unrelated pager surfaces.
