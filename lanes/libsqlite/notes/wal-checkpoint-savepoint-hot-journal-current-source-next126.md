# WAL Checkpoint Savepoint Hot-Journal Current Source Next126

## Scope

Adds `SQLiteWalCheckpointSavepointHotJournalCurrentSourceNextPlan`, a bounded current-source composition for the upstream ordering where a hot rollback journal is recovered before a WAL savepoint rollback truncates the in-memory WAL prefix and the retained prefix is checkpointed.

This intentionally avoids the accepted next122 hot-journal reader checkpoint surface, next123/124 reader restart/truncate surfaces, accepted WAL byte truncation helpers, VFS writer/apply wrappers, and rollback-journal commit/super-journal paths. The new behavior is the combined ordering and source accounting across hot journal recovery, savepoint WAL prefix selection, pinned checkpoint preservation, and released restart/truncate handling.

## Evidence

- Focused test: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalCheckpointSavepointHotJournalCurrentSourceNext126Test.php`
- Result: `1 test files, 76 assertions, 0 failures`
- Application smoke: `php lanes/libsqlite/examples/application-wal-checkpoint-savepoint-hot-journal-current-source-next126.php`
- Smoke result: status `wal-checkpoint-savepoint-hot-journal-current-source-next126`, retained WAL frame count `2`, discarded frame count `2`, pinned checkpoint preserves WAL, released checkpoint restarts WAL.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP rollback-journal parser/recovery, `SQLiteSavepointStack` WAL prefix truncation, `SQLiteWal` snapshot/checkpoint primitives, and bounded Application example harness.

## Next

Continue WAL/pager work toward real transaction application and durable file-handle/fsync behavior after checkpoint/savepoint decisions, avoiding another wrapper around the same hot-journal or byte-truncation surfaces.
