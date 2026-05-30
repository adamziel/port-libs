# Hot Journal WAL Checkpoint Recovery Current Next27

Status: focused PHP behavior growth for pager startup recovery ordering when a database has both a hot rollback journal and a WAL sidecar.

## Delta

- Added `SQLitePagerHotJournalWalRecoveryPlan`, a bounded native PHP recovery planner that restores hot rollback-journal page images first, then runs WAL transaction recovery/checkpointing against the recovered database image.
- Added `SQLiteVfsFileWriter::applyHotJournalWalRecovery()` to atomically apply the combined database, journal, WAL, and directory operations through existing VFS file-handle primitives.
- Added `SQLiteHotJournalWalCheckpointRecoveryCurrentNext27Test.php` with 59 independent PASS cases.
- Added `application-hot-journal-wal-checkpoint-recovery.php` to smoke copied `wp_options` recovery without ext/sqlite.

## Verification

Local focused verification:

```bash
php -l lanes/libsqlite/src/SQLitePagerHotJournalWalRecoveryPlan.php
# No syntax errors detected in lanes/libsqlite/src/SQLitePagerHotJournalWalRecoveryPlan.php

php -l lanes/libsqlite/src/SQLiteVfsFileWriter.php
# No syntax errors detected in lanes/libsqlite/src/SQLiteVfsFileWriter.php

php -l lanes/libsqlite/tests/SQLiteHotJournalWalCheckpointRecoveryCurrentNext27Test.php
# No syntax errors detected in lanes/libsqlite/tests/SQLiteHotJournalWalCheckpointRecoveryCurrentNext27Test.php

php -l lanes/libsqlite/examples/application-hot-journal-wal-checkpoint-recovery.php
# No syntax errors detected in lanes/libsqlite/examples/application-hot-journal-wal-checkpoint-recovery.php

php tools/run-tests.php lanes/libsqlite/tests/SQLiteHotJournalWalCheckpointRecoveryCurrentNext27Test.php
# 1 test files, 59 assertions, 0 failures

php lanes/libsqlite/examples/application-hot-journal-wal-checkpoint-recovery.php --self-test
# status: applied; hotRecovered: true; committedFrameCount: 2; discardedValidTailFrames: 1; discardedCorruptTailFrames: 1; walBytes: 1104

git diff --check -- lanes/libsqlite
# passed with no output
```

## Non-Overlap

This avoids accepted hot rollback-journal recovery/application, rollback-journal commit, WAL checkpoint transactions, WAL checksum recovery apply, WAL multi-transaction visibility, WAL append, savepoint byte truncation, VFS file writer/locked writer/sync/lock clusters, B-tree page/freelist clusters, JSON table source/cursor/constraint work, SELECT SQL text/subquery/group/order clusters, and Unicode GLOB. The new surface is combined startup ordering across hot rollback-journal recovery followed by WAL transaction checkpoint recovery against the recovered image.

## Dependency Closure

No new support component is needed. The slice reuses lane-local rollback-journal parsing/recovery, WAL transaction recovery boundaries, and native VFS file-handle write/truncate/delete/sync operations.
