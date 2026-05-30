# WAL Hot-Journal Savepoint Checkpoint Current Source Next160

## Behavior

Adds a bounded VFS apply path for the current-source ordering where a hot
rollback journal must be recovered before a WAL savepoint rollback/checkpoint
is made durable. The writer now:

- reads the database, `-journal`, and `-wal` sidecars from the VFS root;
- applies hot rollback-journal recovery as the source image;
- truncates the WAL to the savepoint-retained prefix;
- checkpoints the retained prefix in truncate mode;
- deletes the hot journal and persists the final database/WAL state atomically.

This is intentionally narrower than accepted hot-journal recovery,
savepoint-byte truncation, checkpoint transaction planning, rollback-journal
commit apply, and next157 WAL transaction recovery. The slice combines those
already accepted primitives only at the missing VFS application boundary for a
hot-journal plus savepoint checkpoint source transition.

## Evidence

Focused test:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext160Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 57 assertions, 0 failures
```

The test covers applied operations, durable sync counts, journal deletion,
savepoint-retained/discarded WAL frames, current/next reader source
transitions, on-disk database/WAL bytes, reserved-lock skip behavior, missing
savepoint rejection, read-only writer rejection, and checksum rejection.

## Application Smoke

`examples/application-wal-hot-journal-savepoint-checkpoint-current-source-next160.php`
models a Application import crash where `wp_options` has a hot journal and a
plugin-batch savepoint with WAL frames that must be discarded before truncate
checkpoint publication.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
rollback journal parser/recovery, WAL parser/checkpoint logic,
`SQLiteSavepointStack`, and `SQLiteVfsFileWriter` atomic write/snapshot support.
