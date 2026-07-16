# WAL hot-journal checkpoint current-source next380-387

Scope: lane-local WAL checkpoint/hot-journal source follow-on after the merged next372-379 chain.

This slice extends the after-current checkpoint receipt chain through next380-next387 without changing shared runner, dashboard, progress, lane-status, supervisor, or private state artifacts. The added steps verify restart-salt source epoch, reader-mark release source, page-cache source generation, schema-cookie source epoch, commit-generation source epoch, hot-journal delete source epoch, WAL-index salt source epoch, and the final current-source seal.

No new support component is introduced. The implementation reuses `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpoint()` and keeps the existing receipt contract: source token, database digest, page-cache digest, commit generation, schema cookie, checkpoint frame, database header sync, WAL-index salt sync, released reader marks, and absent hot journal.

Focused coverage:

- `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext380387Test.php` chains next380 through next387 from next379.
- The same test blocks unsynced database header, duplicate receipt names, page-cache digest mismatch, commit-generation mismatch, checkpoint-frame mismatch, and a missing next386 base for the next387 seal.
- `application-wal-hot-journal-savepoint-checkpoint-current-source-next387.php` provides a Application-shaped example for the final seal.

Non-overlap: this stays inside the WAL checkpoint/hot-journal current-source receipt chain and does not repeat next372-379, suite/status/dashboard artifacts, SQL, JSON, B-tree, VFS writer application, planner, or unrelated pager surfaces.
