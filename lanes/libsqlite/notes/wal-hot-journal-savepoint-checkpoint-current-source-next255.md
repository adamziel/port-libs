# WAL Hot-Journal Savepoint Checkpoint Current Source Next255

This slice adds `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan`. It composes the accepted next251 WAL sidecar reset admission with reopened-reader receipts, so WordPress readers only move to the restarted empty WAL generation when their paths, source token, generation, database/page-cache digests, fresh WAL salt, zero visible frames, clean cache state, and read-mark slots all match the current source.

Focused evidence:

- `php -l lanes/libsqlite/src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php`: no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext255Test.php`: no syntax errors.
- `php -l lanes/libsqlite/examples/wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next255.php`: no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext255Test.php`: `1 test files, 87 assertions, 0 failures`.
- `php lanes/libsqlite/examples/wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next255.php --self-test`: smoke passed.
- `git diff --check -- lanes/libsqlite`: passed.

Non-overlap: this avoids accepted durable page writes, WAL reset/truncate receipt validation, checkpoint transaction planning, WAL byte truncation, VFS savepoint rollback, rollback-journal apply/commit, VFS sync/write/lock, SELECT, JSON, B-tree, and encoding surfaces. The new behavior is specifically reader admission after the restarted WAL header is already published by next251.

Dependency closure: no new support component is needed; this reuses next251 WAL reset metadata with native PHP reader reopen receipt checks, fresh salt/read-mark validation, empty-WAL fences, and clean page-cache digests.

Expected dashboard delta: `phpPass` increases by `+87`, from `133054` to `133141`. Mapped upstream coverage is unchanged because this is additional focused PHP behavior over existing WAL hot-journal/checkpoint current-source inventory.
