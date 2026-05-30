# WAL hot-journal checkpoint current-source next596-611

Scope: lane-local WAL checkpoint/hot-journal source follow-on after merged next580-595.

This slice extends the after-current checkpoint receipt chain through next596-next611. The first block verifies restart-salt database digest, reader-release source token, page-cache commit generation, checkpoint-frame schema cookie, commit-generation WAL-index salt, hot-journal absence with database-header sync, WAL-index source token, and the next596-603 seal. The second block verifies restart-salt schema cookie, reader-release page cache, database-digest commit generation, checkpoint-frame reader release, commit-generation source token, hot-journal delete page cache, WAL-index database digest, and the final next604-611 seal.

No new numbered source class is introduced. The implementation extends `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan` because its existing private `afterCurrentCheckpoint()` helper is the canonical source-class contract for these WAL hot-journal/savepoint/checkpoint current-source receipt-chain slices.

Focused coverage:

- `SQLiteWalHotJournalSavepointCheckpointAfterCurrentStages596611Test.php` chains next596 through next611 from next595.
- The same test blocks database digest mismatch, commit generation mismatch, missing next602 base for the next603 seal, schema cookie mismatch, held reader marks, and duplicate final seal receipts.
- `application-wal-hot-journal-savepoint-checkpoint-after-current-stage-611.php` provides a Application-shaped example for the final seal.

Non-overlap: this stays inside the WAL checkpoint/hot-journal current-source receipt chain and does not repeat next580-595, upstream suite evidence, status/dashboard artifacts, SQL, JSON, B-tree, VFS writer application, planner, or unrelated pager surfaces.
