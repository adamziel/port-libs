# WAL hot-journal savepoint checkpoint current-source next163

## Behavior

Adds a pinned-reader current-source WAL/VFS apply path for a Application-style import crash:

- recover the hot rollback journal first;
- roll back the active WAL savepoint to its retained prefix;
- attempt a restart checkpoint while a current reader is pinned;
- preserve the retained WAL sidecar instead of truncating/deleting it;
- keep the operation atomic through the existing native PHP VFS writer.

This is narrower than the accepted next160 truncate/no-reader apply path: next163 asserts the busy restart checkpoint path where the retained WAL prefix must remain durable for the pinned reader.

## Evidence

Focused command:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext163Test.php
```

Result:

```text
Focused test run: 1 selected test files (root lock skipped)
1 test files, 56 assertions, 0 failures
```

Application smoke:

```sh
php lanes/libsqlite/examples/application-wal-hot-journal-savepoint-checkpoint-current-source-next163.php
```

Expected output includes `status: applied-pinned-reader`, `walAction: preserve_wal`, `walFrames: 2`, journal removal, retained Application option page visibility, and discarded savepoint draft exclusion.

## Dependency Closure

No new support component is needed. This reuses hot rollback-journal recovery, WAL savepoint current-prefix truncation, restart checkpoint planning, and native VFS file writer atomic apply.

## Non-Overlap

Avoids accepted next160 WAL hot-journal savepoint checkpoint truncate/no-reader behavior, WAL byte truncation, VFS savepoint rollback, rollback-journal commit, WAL checkpoint transactions, and VFS sync/file-writer clusters. The new assertion surface is the pinned-reader restart checkpoint preservation of the retained WAL prefix.
