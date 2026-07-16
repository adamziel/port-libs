# WAL hot-journal checkpoint current-source next340-347

Scope: lane-local WAL checkpoint/hot-journal source follow-on after the merged next332-339 chain.

This slice extends the after-current checkpoint receipt chain through next340-next347 without changing shared runner, dashboard, progress, lane-status, or supervisor artifacts. The added steps verify the restart salt receipt, reader reopen source receipt, savepoint release digest receipt, hot-journal delete digest receipt, database header source receipt, WAL-index reader epoch receipt, savepoint retry absence receipt, and final current-source seal.

No new support component is introduced. The implementation reuses `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpoint()` and keeps the existing receipt contract: source token, database digest, page-cache digest, commit generation, schema cookie, checkpoint frame, database header sync, WAL-index salt sync, released reader marks, and absent hot journal.

Focused coverage:

- `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext340347Test.php` chains next340 through next347 from next339.
- The same test blocks stale database digests, unreleased reader marks, unsynced WAL-index salt, and a missing next346 base for the next347 seal.
- `application-wal-hot-journal-savepoint-checkpoint-current-source-next347.php` provides a Application-shaped example for the final seal.
