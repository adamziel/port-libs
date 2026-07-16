# WAL hot-journal checkpoint current-source next660-675

Scope: lane-local WAL checkpoint/hot-journal source follow-on after merged next644-659.

This slice extends the after-current checkpoint receipt chain through next660-next675. The first block verifies restart-salt page-cache sync, reader-release schema cookie, database-digest WAL-index salt, commit-generation source token, checkpoint-frame database header, hot-journal absence with WAL-index salt, page-cache reader release, and the next660-667 seal. The second block verifies restart-salt commit generation, reader-release page cache, database-header source token, schema-cookie checkpoint frame, WAL-index salt database digest, hot-journal delete reader release, page-cache commit generation, and the final next668-675 seal.

No new numbered source class is introduced. The implementation extends `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan` because its private `afterCurrentCheckpoint()` helper remains the established canonical source-class contract for these WAL hot-journal/savepoint/checkpoint current-source receipt-chain slices.

Focused coverage:

- `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext660675Test.php` chains next660 through next675 from next659.
- The same test blocks visible hot journal, page-cache digest mismatch, missing next666 base for the next667 seal, schema-cookie mismatch, checkpoint-frame mismatch, and duplicate final seal receipts.
- `application-wal-hot-journal-savepoint-checkpoint-current-source-next675.php` provides a Application-shaped example for the final seal.

Non-overlap: this stays inside the WAL checkpoint/hot-journal current-source receipt chain and does not repeat next644-659, upstream suite evidence, status/dashboard artifacts, SQL, JSON, B-tree, VFS writer application, planner, or unrelated pager surfaces.
