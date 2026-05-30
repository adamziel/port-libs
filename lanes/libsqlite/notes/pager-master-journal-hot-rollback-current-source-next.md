Status: focused PHP behavior growth for pager master-journal hot rollback current-source recovery.

This slice adds `SQLitePagerMasterJournalHotRollbackCurrentSourceNextPlan` and `SQLiteVfsFileWriter::applyMasterJournalHotRollbackFromCurrentSource()`. The planner models the opener boundary where a surviving master/super-journal and attached rollback journals must be read from the current VFS source, while stale pre-open database or journal candidate bytes are ignored. Named current-source rollback journals restore attached database images, truncate dirty tails, delete the current journals, and delete the master journal only after every named journal is cleared.

The WordPress smoke models copied `wp_options` and multisite option databases after an interrupted plugin/network import. It proves current file-handle bytes, not stale pager snapshot bytes, decide the recovered `active_plugins`/`upload_path` rows.

Verification:

```text
php -l lanes/libsqlite/src/SQLitePagerMasterJournalHotRollbackCurrentSourceNextPlan.php
No syntax errors detected in lanes/libsqlite/src/SQLitePagerMasterJournalHotRollbackCurrentSourceNextPlan.php

php -l lanes/libsqlite/src/SQLiteVfsFileWriter.php
No syntax errors detected in lanes/libsqlite/src/SQLiteVfsFileWriter.php

php -l lanes/libsqlite/tests/SQLitePagerMasterJournalHotRollbackCurrentSourceNext89Test.php
No syntax errors detected in lanes/libsqlite/tests/SQLitePagerMasterJournalHotRollbackCurrentSourceNext89Test.php

php -l lanes/libsqlite/examples/wordpress-pager-master-journal-hot-rollback-current-source-current-source.php
No syntax errors detected in lanes/libsqlite/examples/wordpress-pager-master-journal-hot-rollback-current-source-current-source.php

php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalHotRollbackCurrentSourceNext89Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 69 assertions, 0 failures

php lanes/libsqlite/examples/wordpress-pager-master-journal-hot-rollback-current-source-current-source.php --self-test
{
    "status": "applied",
    "recoveredDatabases": 2,
    "staleCandidatesIgnored": 1,
    "masterDeleted": true,
    "mainJournalDeleted": true,
    "siteJournalDeleted": true,
    "mainActivePluginsRecovered": true,
    "staleSnapshotIgnored": true,
    "mainTruncatedBytes": 1024,
    "operationsApplied": 10,
    "durableSyncs": 2
}
```

PASS delta: `+69` focused PASS lines. `lane-status.json` `phpPass` moves from `34719` to `34788`. Mapped upstream coverage is unchanged because this reuses already mapped pager hot-journal, master-journal, rollback-journal, and VFS file-handle primitives.

Non-overlap: avoids accepted super-journal commit, rollback-journal commit/apply, hot rollback-journal recovery application, pager hot-journal super current-next70, master/super WAL recovery73/74, pager statement current-source84, hot-journal savepoint cache83, WAL byte truncation/savepoint/checkpoint/read-pin clusters, VFS writer/sync/lock clusters, B-tree pointer-map/overflow/freeblock clusters, JSON table source/cursor/constraint work, SELECT SQL text/subquery/group/order clusters, and Unicode GLOB behavior. The new surface is current VFS-source hydration for master-journal hot rollback, including stale candidate rejection before recovery.

Dependency closure: no new support component is needed. The slice reuses lane-local rollback-journal parsing/recovery and VFS file-writer operation application, adding only a bounded current-source hydration planner.

Next: continue with broader pager/VFS transaction application or another non-overlapping release/all-suite blocker; avoid another hot-journal wrapper unless it applies a distinct current-source or upstream-runner blocker.
