# Pager Hot-Journal Super/Master Recovery Current

Status: focused PHP behavior growth for applying attached-database hot rollback-journal recovery through a current master/super-journal.

This slice adds `SQLiteVfsFileWriter::applyMasterSuperJournalHotRecovery()`. It takes the accepted next73 master/super-journal recovery plan and applies the named attached database recoveries atomically through bounded native PHP file handles. The recovered databases are restored from hot rollback journals, committed WAL prefixes are checkpointed, uncommitted WAL tails are discarded, named rollback journals are deleted, and the master/super-journal is deleted only when every named member has cleared. If the master/super-journal names an absent attached journal, the recovered databases are still applied but the master/super-journal is preserved for the next opener.

Verification:

```text
php -l lanes/libsqlite/src/SQLiteVfsFileWriter.php
No syntax errors detected in lanes/libsqlite/src/SQLiteVfsFileWriter.php

php -l lanes/libsqlite/tests/SQLitePagerHotJournalSuperMasterRecoveryTest.php
No syntax errors detected in lanes/libsqlite/tests/SQLitePagerHotJournalSuperMasterRecoveryTest.php

php -l lanes/libsqlite/examples/application-pager-hot-journal-super-master-recovery.php
No syntax errors detected in lanes/libsqlite/examples/application-pager-hot-journal-super-master-recovery.php

php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerHotJournalSuperMasterRecoveryTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 52 assertions, 0 failures

php lanes/libsqlite/examples/application-pager-hot-journal-super-master-recovery.php --self-test
{
    "status": "applied",
    "recoveredDatabases": 2,
    "superJournalDeleted": true,
    "mainJournalDeleted": true,
    "siteJournalDeleted": true,
    "mainRecoveredActivePlugins": true,
    "siteRecoveredActivePlugins": true,
    "operationsApplied": 24,
    "durableSyncs": 6
}
```

PASS-line delta: `+52` focused assertions in `SQLitePagerHotJournalSuperMasterRecoveryTest.php`.

Dependency closure: no new support component is needed. This reuses the existing bounded native PHP rollback-journal, WAL transaction recovery, master/super-journal planner, and VFS file-handle writer components.

Non-overlap: this avoids accepted super-journal commit, next73 master/super-journal recovery planning, rollback-journal apply/commit, VFS savepoint rollback, WAL byte truncation/checkpoint/read-pin/restart, VFS writer/sync/lock/file-control, B-tree pointer-map/freelist/page-move/overflow, JSON table planner/source/cursor/constraint, SELECT SQL text/subquery/group/order, and Unicode GLOB clusters. The new surface is atomic VFS application of the current master/super-journal hot recovery plan, including preservation when a named member remains unresolved.
