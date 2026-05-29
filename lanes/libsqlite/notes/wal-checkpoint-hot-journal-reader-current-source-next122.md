# WAL checkpoint hot-journal reader current-source next122

Status: focused PHP behavior growth for WAL checkpoint reader visibility after hot rollback-journal recovery.

This slice adds `SQLiteWalCheckpointHotJournalReaderCurrentSourceNextPlan`, a bounded native PHP planner for the SQLite edge where a database has a hot rollback journal, recovery restores the current database image, and a WAL reader pins that recovered current source while a RESTART/TRUNCATE checkpoint is attempted. The plan verifies that checkpoint visibility is computed from the hot-recovered database bytes rather than dirty pre-recovery pages, records reader-pinned versus released checkpoint actions, and reports blocked cases for reserved locks or missing super-journals.

Focused verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalCheckpointHotJournalReaderCurrentSourceNext122Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 70 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/libsqlite/examples/wordpress-wal-checkpoint-hot-journal-reader-current-source-next122.php --self-test
wordpress-wal-checkpoint-hot-journal-reader-current-source-next122 self-test passed
```

Expected dashboard movement: `phpPass` +70, from 47656 to 47726, from the 70 independent PASS lines in `SQLiteWalCheckpointHotJournalReaderCurrentSourceNext122Test.php`. Mapped upstream coverage remains `604 / 1589`; this is focused PHP behavior coverage over existing WAL/pager inventory rather than a newly mapped upstream manifest row.

Non-overlap: avoids accepted WAL checkpoint transactions, WAL byte truncation, WAL savepoint reader checkpoint recovery next118, WAL reader-pin restart/truncate next119, rollback-journal commit/apply, VFS savepoint rollback, hot-journal savepoint retry, pager cache-spill journal modes, and accepted B-tree/JSON/SQL/encoding clusters. The new surface is the ordering of hot rollback-journal recovery before WAL reader checkpoint source selection.

Dependency closure: no new support component is needed. The patch reuses native PHP rollback-journal parsing/recovery, WAL reader snapshots, and durable checkpoint result primitives.

Next task: continue with pager/VFS transaction application or another non-overlapping WAL durability edge; avoid another checkpoint wrapper unless it applies a distinct source-ordering or persistence transition.
