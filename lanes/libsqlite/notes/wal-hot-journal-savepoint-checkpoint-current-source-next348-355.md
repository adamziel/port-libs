# WAL hot-journal checkpoint current-source next348-355

Scope: lane-local WAL checkpoint/hot-journal source follow-on after the merged next340-347 chain.

This slice extends the after-current checkpoint receipt chain through next348-next355 without changing shared runner, dashboard, progress, lane-status, or supervisor artifacts. The added steps verify the checkpoint frame boundary receipt, reader mark release receipt, page-cache digest receipt, schema cookie receipt, commit generation receipt, hot-journal absence receipt, source token receipt, and final current-source seal.

No new support component is introduced. The implementation reuses `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpoint()` and keeps the existing receipt contract: source token, database digest, page-cache digest, commit generation, schema cookie, checkpoint frame, database header sync, WAL-index salt sync, released reader marks, and absent hot journal.

Focused coverage:

- `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext348355Test.php` chains next348 through next355 from next347.
- The same test blocks a stale checkpoint frame, stale page-cache digest, visible hot journal, duplicate receipt names, and a missing next354 base for the next355 seal.
- `wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next355.php` provides a WordPress-shaped example for the final seal.
