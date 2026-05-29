# WAL hot-journal checkpoint current-source next356-363

Scope: lane-local WAL checkpoint/hot-journal source follow-on after the merged next348-355 chain.

This slice extends the after-current checkpoint receipt chain through next356-next363 without changing shared runner, dashboard, progress, lane-status, supervisor, or private state artifacts. The added steps verify the WAL restart source receipt, reader-mark epoch receipt, page-cache source receipt, schema-cookie source receipt, commit-generation source receipt, hot-journal delete source receipt, WAL-index source-token receipt, and final current-source seal.

No new support component is introduced. The implementation reuses `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpoint()` and keeps the existing receipt contract: source token, database digest, page-cache digest, commit generation, schema cookie, checkpoint frame, database header sync, WAL-index salt sync, released reader marks, and absent hot journal.

Focused coverage:

- `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext356363Test.php` chains next356 through next363 from next355.
- The same test blocks an unsynced WAL-index salt, unreleased reader marks, stale schema cookie, stale commit generation, duplicate receipt names, and a missing next362 base for the next363 seal.
- `wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next363.php` provides a WordPress-shaped example for the final seal.

Non-overlap: this stays inside the WAL checkpoint/hot-journal current-source receipt chain and does not repeat next348-355, suite/status/dashboard artifacts, SQL, JSON, B-tree, VFS writer application, planner, or unrelated pager surfaces.
