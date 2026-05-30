# WAL Recovery Checkpoint Savepoint Current Source Next100

This slice adds a bounded planner for a WAL recovery edge used by Application import retries:

- recover the current WAL source to the last committed prefix when a valid uncommitted frame is followed by a corrupt tail frame;
- run savepoint rollback against that recovered committed prefix, not the stale original WAL bytes;
- checkpoint the retained prefix in restart/truncate modes and preserve reader-visible page transitions;
- expose operation ordering for the WAL prefix rewrite, savepoint truncation, database checkpoint write, database sync, and WAL restart/truncate action.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalRecoveryCheckpointSavepointCurrentSourceNext100Test.php`
- `php lanes/libsqlite/examples/application-wal-recovery-checkpoint-savepoint-current-source-next100.php`
- `php -l` on changed PHP files
- `git diff --check -- lanes/libsqlite`

Non-overlap: this does not repeat accepted WAL savepoint byte truncation, WAL checkpoint transactions, VFS savepoint rollback apply, WAL reader-pin restart/truncate handoff, WAL checksum/salt recovery, hot rollback-journal application, or VFS file writer clusters. The new behavior is the composition point where transaction recovery first establishes the current committed WAL source before savepoint rollback and checkpoint restart/truncate decisions.

Dependency closure: no new support component is needed. The slice reuses existing native PHP WAL parsing/recovery, savepoint stack, and checkpoint durability primitives.
