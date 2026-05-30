# WAL Hot-Journal Savepoint Checkpoint Current-Source Next179

## Behavior

Adds `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next179Plan()`, a reopen-read admission check after the next178 post-apply receipt. The slice requires the receipt to be publishable, the database and WAL digests to still match the receipt, the hot journal to remain removed, and each reopened database/WAL read to carry the next178 receipt digest.

This is intentionally a reopen-source pinning slice. It does not repeat next176 source-token admission, next177 apply planning, or next178 file receipt matching.

## Evidence

Focused test:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext179Test.php
```

Application smoke:

```text
php lanes/libsqlite/examples/application-wal-hot-journal-savepoint-checkpoint-current-source-next179.php
```

## Dependency Closure

No new support component is needed. The slice reuses next178 receipt digests plus reopened database and WAL page images.
