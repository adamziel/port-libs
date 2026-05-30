# WAL Hot Journal Savepoint Checkpoint Current Source Next174

Status: focused PHP behavior growth for crash-resume file-state admission after hot-journal recovery, WAL savepoint rollback, and checkpoint publication.

This slice adds `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan`. It builds on the existing next169 partial-publish resume plan and verifies the actual database, hot-journal, and WAL sidecar bytes before admitting hot-journal deletion, reader release, or WAL reset. Matching file bytes can be verified idempotently; missing or mismatched database/WAL/journal bytes produce a precise replay action.

Application path: `application-wal-hot-journal-savepoint-checkpoint-current-source-next174.php` models a copied `wp_options` plugin import that crashes after current checkpoint payloads are durable but before the hot journal is deleted. The smoke proves the current database/WAL bytes are verified, the hot journal can be deleted, and reader release remains blocked until the journal is actually gone.

Verification:

- `php -l lanes/libsqlite/src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext174Test.php`
- `php -l lanes/libsqlite/examples/application-wal-hot-journal-savepoint-checkpoint-current-source-next174.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext174Test.php`
- `php lanes/libsqlite/examples/application-wal-hot-journal-savepoint-checkpoint-current-source-next174.php`
- `git diff --check -- lanes/libsqlite`

Focused result: `1 test files, 59 assertions, 0 failures`.

Dashboard delta: update `phpPass` by `+59` after integration (`79690 -> 79749` from this worktree's lane-status baseline). Mapped upstream coverage is unchanged; this is focused current-source PHP behavior over an already mapped WAL/pager durability family.

Non-overlap: this extends next169 crash-resume with concrete file-state replay admission. It avoids accepted WAL byte truncation, VFS writer/sync/lock, rollback-journal commit/apply, checkpoint transaction, hot-journal reader restart, and reader-token cache surfaces.

Dependency closure: no new support component is needed. The slice reuses native PHP WAL parsing, hot-journal recovery, savepoint rollback, checkpoint payloads, and VFS file-state primitives.

Next task: continue with broader pager/VFS transaction application or another non-overlapping WAL durability edge; avoid another savepoint wrapper unless it applies a distinct persistent state transition.
