# WAL Checkpoint Hot Journal Savepoint Current Source Next114

Status: focused PHP behavior growth for `wal-checkpoint-hot-journal-savepoint-current-source-next114`.

This slice adds `SQLiteWalCheckpointHotJournalSavepointCurrentSourceNextPlan`, a bounded native PHP composition for the pager/WAL edge where hot rollback-journal recovery must establish the current database source before a recovered committed WAL prefix can be used for savepoint rollback and restart/truncate checkpoint decisions.

Behavior covered:

- rejects stale parsed rollback journals or WAL bytes before trusting current-source inputs;
- restores the hot rollback journal before WAL recovery and savepoint checkpointing;
- discards valid uncommitted WAL tail frames when deriving the committed current WAL prefix;
- rolls back the named savepoint against that recovered prefix, not the stale original WAL bytes;
- reports dirty, hot-recovered, current-savepoint, and next-reader visibility for restart, truncate, reader-pinned busy, and not-hot super-journal paths.

Verification:

```sh
php -l lanes/libsqlite/src/SQLiteWalCheckpointHotJournalSavepointCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteWalCheckpointHotJournalSavepointCurrentSourceNext114Test.php
php -l lanes/libsqlite/examples/wordpress-wal-checkpoint-hot-journal-savepoint-current-source-next114.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalCheckpointHotJournalSavepointCurrentSourceNext114Test.php
php lanes/libsqlite/examples/wordpress-wal-checkpoint-hot-journal-savepoint-current-source-next114.php --self-test
git diff --check -- lanes/libsqlite
```

Focused result: `1 test files, 83 assertions, 0 failures` with 83 PASS lines.

WordPress smoke: `wordpress-wal-checkpoint-hot-journal-savepoint-current-source-next114 self-test passed`.

Non-overlap: avoids accepted WAL MVCC hot-journal checkpoint next107, WAL recovery checkpoint savepoint next100, WAL savepoint release/checkpoint next84/85/105, WAL byte truncation, WAL checkpoint transactions, VFS savepoint rollback apply, rollback-journal apply/commit/super-journal paths, WAL checksum/salt recovery, and hot-journal statement/cache current-source slices. The new surface is specifically the ordering and current-source handoff across hot rollback-journal recovery, committed WAL prefix recovery, savepoint rollback, and restart/truncate checkpoint visibility.

Dependency closure: no new support component is needed. This reuses lane-local rollback-journal parsing/recovery, WAL transaction recovery, savepoint WAL prefix rollback, reader visibility, and durable checkpoint primitives.

Next task: continue with broader pager/VFS transaction application or a distinct WAL durability edge; avoid another savepoint wrapper unless it applies a new source-ordering, fsync, or file-handle rule.
