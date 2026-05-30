# WAL hot-journal savepoint checkpoint current-source next194

## Slice

Adds `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan`, a
reopened-reader generation seal for the WAL hot-journal/savepoint/checkpoint
current-source chain. It builds on the accepted next190 retry-checkpoint
publication guard and admits reopened reader tickets only when they:

- point at the next190 publication token,
- match the published database and WAL digests,
- advance past the prior reader epoch,
- stay within the published reader page/source sets,
- carry directory-sync and checkpoint-lock receipts,
- close the savepoint and retain no hot-journal digest.

## Application path

`examples/application-wal-hot-journal-savepoint-checkpoint-current-source-next194.php`
models copied `wp_options` readers being exposed after retry checkpoint
publication. The smoke emits the reader-exposure status, ticket count, sealed
epoch, reader sources, seal digest, and dependency-closure note.

## Evidence

Focused command:

```bash
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext194Test.php
```

Verified focused delta: 65 PASS lines in a lane-scoped test file.

Example smoke:

```bash
php lanes/libsqlite/examples/application-wal-hot-journal-savepoint-checkpoint-current-source-next194.php
```

## Non-overlap

This slice avoids accepted WAL byte truncation, rollback-journal apply,
checkpoint transaction planning, VFS writer/sync application, reader-cache
token fencing, and next190 file-map publication. It seals reopened reader
tickets after those behaviors have already succeeded.

## Dependency Closure

No new support component is needed. The plan reuses next190 retry checkpoint
publication evidence plus existing directory-sync and checkpoint-lock receipts.
