# WAL hot-journal savepoint checkpoint current-source next210

## Behavior

Adds `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan`, a
post-checkpoint WAL append fence layered after the next209 writer-generation
admission. It accepts append batches only when the writer is current, the
first frame follows the checkpoint frame, the commit frame matches the new
append boundary, database/WAL/consumer digests still match, no hot-journal
identity remains, the savepoint scope is closed, and the exclusive lock receipt
is present.

## Application Smoke

`examples/application-wal-hot-journal-savepoint-checkpoint-current-source-next210.php`
models a copied Application `wp_options` import after hot-journal recovery and
checkpoint publication. It admits the current autoload update frame batch and
blocks a stale plugin writer before any new WAL append.

## Verification

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext210Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 80 assertions, 0 failures

php lanes/libsqlite/examples/application-wal-hot-journal-savepoint-checkpoint-current-source-next210.php
status: wal-hot-journal-savepoint-checkpoint-current-source-next210
acceptedAppendBatches: autoload-frame-batch
blockedAppendBatches: stale-plugin-frame-batch
```

## Non-Overlap

This slice gates new WAL append batches after next209 writer reuse. It does
not repeat next208 reader-slot reuse, next209 writer-handle admission, WAL byte
truncation, checkpoint transaction planning, VFS savepoint rollback apply,
rollback-journal commit/apply, WAL file writing, or hot-journal recovery
application.

## Dependency Closure

No new support component is needed. The slice reuses next209 writer-generation
fences, WAL/database digests, and append-frame metadata.
