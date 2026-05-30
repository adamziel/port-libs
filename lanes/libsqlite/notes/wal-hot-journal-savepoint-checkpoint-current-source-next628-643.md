# WAL hot-journal checkpoint current-source next628-643

Scope: lane-local WAL checkpoint/hot-journal source follow-on after merged next612-627.

This slice extends the after-current checkpoint receipt chain through next628-next643. The first block verifies restart-salt database digest, reader-release source token, page-cache database-header sync, checkpoint-frame WAL-index salt, commit-generation schema cookie, hot-journal delete with reader release, database-digest page cache, and the next628-635 seal. The second block verifies restart-salt source token, reader-release database-header sync, page-cache schema cookie, checkpoint-frame database digest, commit-generation WAL-index salt, hot-journal absence page cache, WAL-index salt with reader release, and the final next636-643 seal.

No new numbered source class is introduced. The implementation extends `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan` because its private `afterCurrentCheckpoint()` helper remains the established canonical source-class contract for these WAL hot-journal/savepoint/checkpoint current-source receipt-chain slices.

Focused coverage:

- `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext628643Test.php` chains next628 through next643 from next627.
- The same test blocks unsynced database header, source-token mismatch, missing next634 base for the next635 seal, schema-cookie mismatch, visible hot journal, and duplicate final seal receipts.
- `application-wal-hot-journal-savepoint-checkpoint-current-source-next643.php` provides a Application-shaped example for the final seal.

Non-overlap: this stays inside the WAL checkpoint/hot-journal current-source receipt chain and does not repeat next612-627, upstream suite evidence, status/dashboard artifacts, SQL, JSON, B-tree, VFS writer application, planner, or unrelated pager surfaces.
