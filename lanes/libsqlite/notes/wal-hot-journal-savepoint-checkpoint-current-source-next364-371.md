# WAL hot-journal checkpoint current-source next364-371

Scope: lane-local WAL checkpoint/hot-journal source follow-on after the merged next356-363 chain.

This slice extends the after-current checkpoint receipt chain through next364-next371 without changing shared runner, dashboard, progress, lane-status, supervisor, or private state artifacts. The added steps verify restart-salt source receipt, reader-epoch source receipt, page-cache epoch receipt, schema-cookie epoch receipt, commit-generation epoch receipt, hot-journal absence source receipt, WAL-index salt epoch receipt, and the final current-source seal.

No new support component is introduced. The implementation reuses `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpoint()` and keeps the existing receipt contract: source token, database digest, page-cache digest, commit generation, schema cookie, checkpoint frame, database header sync, WAL-index salt sync, released reader marks, and absent hot journal.

Focused coverage:

- `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext364371Test.php` chains next364 through next371 from next363.
- The same test blocks an unsynced database header, source-token mismatch, page-cache digest mismatch, visible hot journal, checkpoint-frame mismatch, and a missing next370 base for the next371 seal.
- `application-wal-hot-journal-savepoint-checkpoint-current-source-next371.php` provides a Application-shaped example for the final seal.

Non-overlap: this stays inside the WAL checkpoint/hot-journal current-source receipt chain and does not repeat next356-363, suite/status/dashboard artifacts, SQL, JSON, B-tree, VFS writer application, planner, or unrelated pager surfaces.
