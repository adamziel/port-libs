# WAL hot-journal checkpoint current-source next580-595

Scope: lane-local WAL checkpoint/hot-journal source follow-on after merged next564-579.

This slice extends the after-current checkpoint receipt chain through next580-next595. The first block verifies restart-salt/source-token receipts, reader-release database digest, page-cache schema cookie, checkpoint-frame WAL-index salt, commit-generation database-header sync, hot-journal absence with checkpoint frame, WAL-index reader release, and the next580-587 seal. The second block verifies restart-salt page-cache receipt, reader-release schema cookie, database-digest WAL-index salt, checkpoint-frame database-header sync, commit-generation reader release, hot-journal delete source token, WAL-index page-cache digest, and the final next588-595 seal.

No new numbered source class is introduced. The implementation extends `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan` because its existing private `afterCurrentCheckpoint()` helper is the canonical source-class contract for these WAL hot-journal/savepoint/checkpoint current-source receipt-chain slices.

Focused coverage:

- `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext580595Test.php` chains next580 through next595 from next579.
- The same test blocks source-token mismatch, page-cache digest mismatch, missing next586 base for the next587 seal, visible hot journal, unsynced database header, and duplicate final seal receipts.
- `wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next595.php` provides a WordPress-shaped example for the final seal.

Non-overlap: this stays inside the WAL checkpoint/hot-journal current-source receipt chain and does not repeat next564-579, upstream suite evidence, status/dashboard artifacts, SQL, JSON, B-tree, VFS writer application, planner, or unrelated pager surfaces.
