# WAL Hot Journal Checkpoint Savepoint Current Source Next141

## Scope

This slice adds `SQLiteWalHotJournalCheckpointSavepointCurrentSourceNextPlan`
for the current-source WAL path where:

- a copied Application database image first recovers a hot rollback journal;
- WAL transaction recovery keeps only the committed prefix;
- `ROLLBACK TO` an inner savepoint truncates that prefix to the current reader
  source;
- a restart checkpoint remains busy while that reader is pinned; and
- the next writer opens a separate WAL source with a new checkpoint sequence
  and salt.

This intentionally avoids the accepted next114 hot-journal savepoint checkpoint,
next135 reader next-generation source, next138 truncate path, WAL byte
truncation, VFS savepoint rollback apply, rollback-journal commit/apply, and
checkpoint transaction clusters. The new behavior is the composed current-reader
source boundary after hot-journal recovery plus savepoint rollback while the next
WAL generation is admitted separately.

## Evidence

Focused verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalCheckpointSavepointCurrentSourceNext141Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 85 assertions, 0 failures
```

Application smoke:

```text
php lanes/libsqlite/examples/application-wal-hot-journal-checkpoint-savepoint-current-source-next141.php
```

The smoke returns JSON showing the current reader remains pinned to the
savepoint-retained WAL source, the restart checkpoint preserves that source while
busy, and the next writer uses a separated WAL generation for copied
`wp_options` import pages.

## Dependency Closure

No new support component is needed. This reuses native rollback-journal hot
recovery, WAL transaction recovery, savepoint WAL prefix truncation,
checkpoint-mode durability planning, and WAL reader snapshot logic already in
`lanes/libsqlite/src`.
