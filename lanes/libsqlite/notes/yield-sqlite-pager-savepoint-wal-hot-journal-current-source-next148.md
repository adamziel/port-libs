# pager-savepoint-wal-hot-journal-current-source-next148

## Behavior

Adds `SQLitePagerSavepointWalHotJournalCurrentSourceNextPlan` for a bounded pager/WAL durability edge:

- recover a hot rollback journal before retrying a savepoint;
- preserve the current WAL reader snapshot against the recovered current source;
- roll back the current savepoint page images before retry;
- require the next savepoint retry to use a distinct WAL generation, with advanced checkpoint sequence, changed salt pair, and different WAL bytes.

This avoids the accepted next88 hot-journal savepoint retry, next143 WAL reader restart, next142 master-journal savepoint, batch143 WAL checkpoint hot-journal reader, WAL byte truncation, VFS savepoint rollback, rollback-journal apply, and checkpoint transaction surfaces. The new behavior composes those primitives to prove the current reader pin remains stable across hot-journal recovery and savepoint retry while later writes are separated into the next WAL source.

## Evidence

Focused command:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerSavepointWalHotJournalCurrentSourceNext148Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 73 assertions, 0 failures
```

Application smoke:

```text
php lanes/libsqlite/examples/application-pager-savepoint-wal-hot-journal-current-source-next148.php
application-pager-savepoint-wal-hot-journal-current-source-next148 self-test passed
```

## Dashboard Delta

- `phpPass`: `65533 -> 65606` from 73 new focused passing assertions.
- `phpFail`: remains `0`.
- Mapped upstream denominator remains unchanged; this is focused PHP behavior coverage, not a fresh upstream Tcl inventory row.

## Dependency Closure

No new support component is needed. This reuses the native PHP rollback-journal hot-recovery, savepoint page-image rollback, WAL parsing, and reader snapshot helpers already present under `lanes/libsqlite/src`.

## Next Gate

Continue with broader pager/VFS transaction application or WAL checkpoint/reset durability that applies this separated current/next WAL source model to file-handle writes without repeating the accepted rollback-journal, savepoint rollback, checkpoint transaction, or next148 reader-pin assertions.
