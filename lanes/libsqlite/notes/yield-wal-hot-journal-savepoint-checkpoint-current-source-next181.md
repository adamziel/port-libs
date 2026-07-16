# WAL Hot-Journal Savepoint Checkpoint Current-Source Next181

## Behavior

Adds `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan`, a reopen-time admission check after the next178 post-apply receipt. It verifies the durable database bytes, confirms the hot journal remains absent, reparses the reopened WAL with checksums enabled, and records commit-frame metadata before allowing a Application import/checkpoint publication to reopen readers.

This intentionally follows the next178 receipt. It does not repeat next175 VFS writes, next173 source-hash admission, or next178 post-apply file matching.

## Evidence

Focused test:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext181Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 41 assertions, 0 failures
```

Application smoke:

```text
php lanes/libsqlite/examples/application-wal-hot-journal-savepoint-checkpoint-current-source-next181.php
{
    "status": "wal-hot-journal-savepoint-checkpoint-current-source-next181",
    "can_reopen_publish": true,
    "matched_source_names": [
        "database",
        "journal",
        "wal"
    ],
    "wal_frame_count": 1,
    "wal_last_commit_page_count": 2,
    "blocked_reasons": [],
    "reopen_digest": "80f36bd89621bb1eb95bd3f7ca0f976fbb970069c0101692835e097baa09ccfd"
}
```

## Dependency Closure

No new support component is needed. The slice reuses native WAL checksum parsing, next178 post-apply receipt payloads, and existing VFS writer receipts.

## Expected Dashboard Movement

`phpPass` should increase by `+41` focused PASS lines from `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext181Test.php`. Mapped upstream coverage is unchanged because this slice does not claim a new manifest-backed upstream inventory row.
