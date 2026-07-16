# WAL Hot-Journal Savepoint Checkpoint Current Source Next236

## Behavior

Adds `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan`, a post-next233 finalizer gate before opening the next WAL writer generation. It admits the next writer only after every prepared statement admitted against the checkpoint current source has a matching finalizer receipt: source token, generation, schema cookie, database digest, `SQLITE_DONE`, reset, reader-lease release, WAL-hook receipt, and autocheckpoint receipt.

Blocked finalizers retain the checkpoint reader leases and suppress the next writer when a statement is missing, stale, still inside a savepoint, still sees the hot journal, has dirty reader cache state, or lacks WAL-hook/autocheckpoint receipts.

## Evidence

- Focused test: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext236Test.php`
- Application smoke: `php lanes/libsqlite/examples/application-wal-hot-journal-savepoint-checkpoint-current-source-next236.php --self-test`

## Non-Overlap

This slice does not repeat checkpoint reset admission, publication receipts, reopened handle coverage, prepared-statement admission, WAL byte truncation, VFS savepoint rollback apply, rollback-journal commit/apply, or checkpoint transaction planning. The new surface is the statement-finalizer handoff that releases checkpoint current-source readers before the next writer generation opens.

## Dependency Closure

No new support component is needed. The slice reuses next233 statement admission metadata, current-source source tokens, reader lease receipts, and WAL-hook/autocheckpoint fences.
