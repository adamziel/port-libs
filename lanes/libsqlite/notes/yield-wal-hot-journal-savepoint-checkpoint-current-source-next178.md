# WAL Hot-Journal Savepoint Checkpoint Current-Source Next178

## Behavior

Adds `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan`, a post-apply receipt check for the WAL hot-journal savepoint checkpoint publication path. The receipt verifies that after the guarded next175 VFS publication:

- the database bytes match the durable checkpoint payload,
- the hot journal is removed,
- the WAL bytes match the next-generation durable WAL payload,
- operation order remains write/truncate/sync/delete/write/truncate/sync/directory-sync,
- database/WAL syncs and directory sync are present before reopen/publication is considered complete.

This is intentionally a post-apply admission receipt. It does not repeat next173 source-hash gating, next174 partial-file replay admission, or next175 VFS write application.

## Evidence

Focused test:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext178Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 50 assertions, 0 failures
```

Application smoke:

```text
php lanes/libsqlite/examples/application-wal-hot-journal-savepoint-checkpoint-current-source-next178.php
{
    "status": "wal-hot-journal-savepoint-checkpoint-current-source-next178",
    "can_publish_receipt": true,
    "matched_source_names": [
        "database",
        "journal",
        "wal"
    ],
    "blocked_reasons": [],
    "receipt_digest": "a4f621e08dc7b77bcdafe3103b2a4e9230eb8887a167456124a5bcc52e4aa5d4"
}
```

## Dependency Closure

No new support component is needed. The slice reuses native WAL parsing, checkpoint payloads, hot-journal deletion state, and existing VFS writer operation receipts.

## Expected Dashboard Movement

`phpPass` should increase by `+50` focused PASS lines when accepted. Mapped upstream coverage is unchanged because this slice does not claim a new manifest-backed upstream inventory row.
