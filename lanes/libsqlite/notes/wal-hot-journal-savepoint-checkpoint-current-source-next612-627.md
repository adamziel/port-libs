# WAL hot-journal checkpoint current-source next612-627

Scope: lane-local WAL checkpoint/hot-journal source follow-on after merged next596-611.

This slice extends the after-current checkpoint receipt chain through next612-next627. The first block verifies restart-salt database-header sync, reader-release database digest, page-cache source token, checkpoint-frame commit generation, schema-cookie WAL-index salt, hot-journal absence with reader release, database-header page cache, and the next612-619 seal. The second block verifies restart-salt source token, reader-release schema cookie, database-digest page cache, checkpoint-frame database-header sync, commit-generation WAL-index salt, hot-journal delete database digest, WAL-index page cache, and the final next620-627 seal.

No new numbered source class is introduced. The implementation extends `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan` because its private `afterCurrentCheckpoint()` helper remains the established canonical source-class contract for these WAL hot-journal/savepoint/checkpoint current-source receipt-chain slices.

Focused coverage:

- `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext612627Test.php` chains next612 through next627 from next611.
- The same test blocks unsynced database header, source-token mismatch, missing next618 base for the next619 seal, schema-cookie mismatch, visible hot journal, and duplicate final seal receipts.
- `wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next627.php` provides a WordPress-shaped example for the final seal.

Non-overlap: this stays inside the WAL checkpoint/hot-journal current-source receipt chain and does not repeat next596-611, upstream suite evidence, status/dashboard artifacts, SQL, JSON, B-tree, VFS writer application, planner, or unrelated pager surfaces.
