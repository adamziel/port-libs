# Pager MVCC reader current next56

Status: focused PHP behavior growth for pager MVCC reader snapshots across a concurrent WAL append.

This slice adds `SQLiteWalAppendPlan::mvccReaderCurrentNext()`. It accepts an explicit current reader end frame, appends committed and uncommitted writer frames, and reports current-reader versus next-reader page visibility. The current reader remains pinned to its original WAL read mark while the next reader advances to the latest committed append. Uncommitted writer tail frames remain invisible to both readers.

Application relevance: copied `wp_options` imports can append `active_plugins` and transient-cache pages while an existing reader remains pinned to the pre-import option view; the next reader sees the committed import without exposing draft plugin frames.

Verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMvccReaderCurrentNext56Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 65 assertions, 0 failures
```

```text
php -l lanes/libsqlite/src/SQLiteWalAppendPlan.php
php -l lanes/libsqlite/tests/SQLitePagerMvccReaderCurrentNext56Test.php
php -l lanes/libsqlite/examples/application-pager-mvcc-reader-current-next56.php
php lanes/libsqlite/examples/application-pager-mvcc-reader-current-next56.php --self-test
```

Dashboard delta: `phpPass` should increase by 65, from 20008 to 20073, for the new focused PASS lines in `SQLitePagerMvccReaderCurrentNext56Test.php`.

Non-overlap: this avoids accepted WAL checkpoint transactions, WAL savepoint byte truncation, WAL savepoint reader restart/truncate checkpoint visibility, WAL append transaction persistence, VFS writer/sync/lock/rollback clusters, rollback-journal commit/apply, JSON table source/cursor/constraint work, SELECT SQL text/JOIN/GROUP/subquery/ORDER/LIMIT clusters, B-tree page/root/overflow clusters, Unicode GLOB, and batch51-55 queued surfaces. The new behavior is the explicit older reader read-mark boundary during a later WAL append.

Dependency closure: no new support component is needed. The slice reuses existing native PHP WAL checksum, append transaction, and reader snapshot primitives.
