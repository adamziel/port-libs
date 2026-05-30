# Pager WAL hot-journal savepoint current-source next87

Status: focused PHP behavior growth for current-source admission before hot rollback-journal plus savepoint WAL replay.

This slice adds `SQLiteWalHotJournalSavepointReplayPlan::replayCurrentSourceNext()`. The planner now verifies that the parsed rollback journal and parsed WAL exactly match the raw current journal/WAL bytes before hot-journal recovery, savepoint WAL prefix truncation, checkpoint image generation, or replay operations are produced. It rejects stale parsed journals, stale parsed WALs, mutated journal bytes, mutated WAL bytes, and empty journal bytes before applying a mixed-source recovery plan.

Verification:

```text
php -l lanes/libsqlite/src/SQLiteRollbackJournal.php
php -l lanes/libsqlite/src/SQLiteWalHotJournalSavepointReplayPlan.php
php -l lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCurrentSourceNext87Test.php
php -l lanes/libsqlite/examples/application-wal-hot-journal-savepoint-current-source-next87.php
No syntax errors detected in changed PHP files.

php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCurrentSourceNext87Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 68 assertions, 0 failures

php lanes/libsqlite/examples/application-wal-hot-journal-savepoint-current-source-next87.php --self-test
status: hot_journal_recovered_savepoint_wal_replayed
retainedFrames: 2
discardedFrames: 2
staleParsedJournalRejected: true
```

Focused PASS delta: `+68` PASS lines in `SQLiteWalHotJournalSavepointCurrentSourceNext87Test.php`. `lane-status.json` `phpPass` moves from `33161` to `33229`. Mapped upstream coverage is unchanged because this is an additional current-source admission guard over already mapped pager/WAL hot-journal and savepoint replay primitives.

Non-overlap: avoids accepted pager hot-journal savepoint cache current-source next83, WAL savepoint checkpoint current-source next79/release current-source next84, WAL byte truncation, WAL reader-pin checkpoint/restart handoffs, VFS savepoint rollback, rollback-journal commit/apply, super-journal commit, VFS sync/lock/file-writer clusters, JSON table source/cursor/constraint work, SELECT SQL text/subquery/group/order clusters, B-tree page/freelist/overflow clusters, and Unicode GLOB behavior. This slice only adds parsed-object/raw-byte source admission for the combined hot-journal plus savepoint WAL replay path.

Dependency closure: no new support component is needed. The slice reuses lane-local rollback-journal parsing/checksum validation, WAL parsing/checksum validation, hot-journal recovery, savepoint WAL truncation, and WAL transaction recovery primitives.

Next task: continue with broader pager/VFS transaction application or another distinct WAL durability edge; avoid another hot-journal wrapper unless it applies a new file-handle or upstream-runner blocker.
