# Pager master journal savepoint current source next108

Status: focused PHP behavior growth for pager master-journal recovery followed by active savepoint retry writes.

This slice adds `SQLitePagerMasterJournalSavepointCurrentSourceNextPlan`. It composes the accepted master-journal current-source hot rollback and savepoint retry path, then records active savepoint before-images from the recovered database image. That proves a Application import retry can still `ROLLBACK TO` the savepoint without restoring stale crashed current bytes.

Focused verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalSavepointCurrentSourceNext108Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 69 assertions, 0 failures
```

Application smoke:

```text
php lanes/libsqlite/examples/application-pager-master-journal-savepoint-current-source-next108.php --self-test
application-pager-master-journal-savepoint-current-source-next108 self-test passed
```

Non-overlap: avoids accepted pager hot-journal statement cache recovery, pager statement-journal savepoint handling, pager super-journal hot rollback, rollback-journal commit/apply, VFS savepoint rollback apply, WAL byte truncation/restart/truncate reader slices, WAL checkpoint transactions, and accepted B-tree/JSON/SQL/encoding/VFS clusters. The new surface is savepoint before-image seeding from the master-journal recovered current source before retry writes.

Dependency closure: no new support component is needed. The slice reuses native PHP rollback-journal parsing, master-journal current-source recovery, and savepoint page-image bookkeeping.

Next task: continue pager/VFS transaction application or another non-overlapping durability edge; avoid another master-journal/status-only variant unless it applies a distinct current-source pager transition.
