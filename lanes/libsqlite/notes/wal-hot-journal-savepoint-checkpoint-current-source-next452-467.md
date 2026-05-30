# WAL hot-journal checkpoint current-source next452-467

Scope: lane-local WAL checkpoint/hot-journal source follow-on after merged next436-451.

This slice extends the after-current checkpoint receipt chain through next452-next467. The first block verifies restart-salt receipt page-cache digest, reader-mark release page-cache digest, page-cache digest source frame, schema-cookie source digest, commit-generation page-cache digest, hot-journal absence page-cache digest, WAL-index salt page-cache digest, and the next452-459 seal. The second block verifies restart-salt receipt source generation, reader-mark release source generation, page-cache digest schema generation, schema-cookie page-cache digest, commit-generation database digest, hot-journal delete page-cache digest, WAL-index salt database digest, and the final next460-467 seal.

No new support component is introduced. The implementation reuses `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::afterCurrentCheckpoint()` and keeps the existing receipt contract: source token, database digest, page-cache digest, commit generation, schema cookie, checkpoint frame, database header sync, WAL-index salt sync, released reader marks, and absent hot journal.

Focused coverage:

- `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext452467Test.php` chains next452 through next467 from next451.
- The same test blocks page-cache digest mismatch, source-token mismatch, missing next458 base for the next459 seal, unsynced database header, visible hot journal, and duplicate final seal receipts.
- `application-wal-hot-journal-savepoint-checkpoint-current-source-next467.php` provides a Application-shaped example for the final seal.

Non-overlap: this stays inside the WAL checkpoint/hot-journal current-source receipt chain and does not repeat next436-451, upstream suite evidence, status/dashboard artifacts, SQL, JSON, B-tree, VFS writer application, planner, or unrelated pager surfaces.
