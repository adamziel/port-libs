# WAL Hot-Journal Savepoint Checkpoint Current Source Next162

This slice adds `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan`, a bounded planner for the pager/WAL edge where a hot rollback journal must become the current database source before a savepoint WAL-prefix rollback is checkpointed.

The non-overlap point is the stale-source admission check: it compares checkpoint output computed from the hot-recovered current source against the dirty pre-recovery database bytes and reports the pages that would publish stale failed-import content. This avoids repeating accepted WAL byte truncation, hot-journal reader checkpoint, VFS savepoint rollback application, WAL transaction recovery apply, and next158 VFS savepoint/checkpoint apply paths.

Verification:

- `php -l lanes/libsqlite/src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext162Test.php`
- `php -l lanes/libsqlite/examples/application-wal-hot-journal-savepoint-checkpoint-current-source-next162.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext162Test.php`
- `php lanes/libsqlite/examples/application-wal-hot-journal-savepoint-checkpoint-current-source-next162.php`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component is needed. The slice reuses native PHP rollback-journal parsing/recovery, WAL savepoint prefix truncation, durable checkpoint planning, and reader snapshot helpers.
