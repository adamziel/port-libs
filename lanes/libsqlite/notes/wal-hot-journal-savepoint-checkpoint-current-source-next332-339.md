# WAL hot-journal checkpoint current-source next332-339

Scope: lane-local WAL checkpoint/hot-journal source follow-on after the merged next324-331 chain.

This slice extends the current-source checkpoint receipt chain through next332-next339 without changing shared runner, dashboard, or progress artifacts. The added steps verify the restart generation, reader reopen token, savepoint release token, hot-journal delete token, database header epoch, WAL-index reader boundary, savepoint retry absence token, and final current-source seal.

No new support component is introduced. The implementation reuses `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpoint()` and keeps the same receipt contract: source token, database digest, page-cache digest, commit generation, schema cookie, checkpoint frame, database header sync, WAL-index salt sync, released reader marks, and absent hot journal.

Focused coverage:

- `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext332339Test.php` chains next332 through next339 from next331.
- The same test blocks stale source tokens, visible hot journals, duplicate receipt names, and a missing next338 base for the next339 seal.
- `application-wal-hot-journal-savepoint-checkpoint-current-source-next339.php` provides a Application-shaped example for the final seal.
