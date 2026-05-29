# WAL hot-journal checkpoint current-source next644-659

Scope: lane-local WAL checkpoint/hot-journal source follow-on after merged next628-643.

This slice extends the after-current checkpoint receipt chain through next644-next659. The first block verifies restart-salt database-header sync, reader-release source token, page-cache database digest, schema-cookie WAL-index salt, commit-generation checkpoint frame, hot-journal absence with reader release, WAL-index salt page-cache sync, and the next644-651 seal. The second block verifies restart-salt source token, reader-release database digest, page-cache schema cookie, checkpoint-frame WAL-index salt, commit-generation database-header sync, hot-journal delete source token, WAL-index salt with reader release, and the final next652-659 seal.

No new numbered source class is introduced. The implementation extends `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan` because its private `afterCurrentCheckpoint()` helper remains the established canonical source-class contract for these WAL hot-journal/savepoint/checkpoint current-source receipt-chain slices.

Focused coverage:

- `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext644659Test.php` chains next644 through next659 from next643.
- The same test blocks unsynced WAL-index salt, database-digest mismatch, missing next650 base for the next651 seal, unreleased reader marks, source-token mismatch, and duplicate final seal receipts.
- `wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next659.php` provides a WordPress-shaped example for the final seal.

Non-overlap: this stays inside the WAL checkpoint/hot-journal current-source receipt chain and does not repeat next628-643, upstream suite evidence, status/dashboard artifacts, SQL, JSON, B-tree, VFS writer application, planner, or unrelated pager surfaces.
