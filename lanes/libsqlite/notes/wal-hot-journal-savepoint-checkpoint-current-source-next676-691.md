# WAL hot-journal checkpoint current-source next676-691

Scope: lane-local WAL checkpoint/hot-journal source follow-on after integrated next660-675.

This slice extends the admitted after-current checkpoint receipt chain through next676-next691. It begins from a next675 checkpoint handoff, verifies restart-salt database digest, reader-release source token, page-cache schema cookie, checkpoint-frame WAL-index salt, commit-generation database header, hot-journal absence with reader release, WAL-index salt page cache, and the next676-683 seal. The second block verifies restart-salt checkpoint frame, reader-release database header, page-cache source token, schema-cookie database digest, commit-generation WAL-index salt, hot-journal delete page cache, WAL-index salt reader release, and the final next684-691 seal.

No new numbered source class is introduced. The implementation extends `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan` because its private `afterCurrentCheckpoint()` helper remains the established canonical source-class contract for these WAL hot-journal/savepoint/checkpoint current-source receipt-chain slices.

Focused coverage:

- `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext676691Test.php` chains next676 through next691 directly from next675.
- The same test proves the next675 handoff is required, and blocks source-token mismatch, unreleased reader marks, missing next682 base for the next683 seal, database-digest mismatch, and duplicate final seal receipts.
- `wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next691.php` provides a WordPress-shaped example for the final seal.

Non-overlap: this stays inside the WAL checkpoint/hot-journal current-source receipt chain and does not repeat next660-675, upstream suite evidence, status/dashboard artifacts, SQL, JSON, B-tree, VFS writer application, planner, or unrelated pager surfaces.
