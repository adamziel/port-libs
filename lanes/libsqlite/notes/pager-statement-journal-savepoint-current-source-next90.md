# Pager statement journal savepoint current source next90

Status: focused PHP behavior growth for pager statement-journal savepoint RELEASE followed by the next statement journal.

This slice adds `SQLiteSavepointStack::releaseCurrentSourceAndBeginNextStatementJournal90()`. It models the pager edge where a successful inner savepoint is released only after current database page images match the expected statement-journal source pages, statement journals owned by the released frame are cleared, the released dirty pages and WAL frame ownership merge into the parent frame, and the next statement journal starts from that merged current source.

Verification:

```text
php -l lanes/libsqlite/src/SQLiteSavepointStack.php
php -l lanes/libsqlite/tests/SQLitePagerStatementJournalSavepointCurrentSourceNext90Test.php
php -l lanes/libsqlite/examples/application-pager-statement-journal-savepoint-current-source-next90.php
php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerStatementJournalSavepointCurrentSourceNext90Test.php
php lanes/libsqlite/examples/application-pager-statement-journal-savepoint-current-source-next90.php --self-test
git diff --check -- lanes/libsqlite
```

Focused assertion delta: +61 new PASS lines in `SQLitePagerStatementJournalSavepointCurrentSourceNext90Test.php`.

Non-overlap: avoids accepted statement-journal rollback current-source next86, rollback-to-current next66/69, WAL byte truncation, VFS savepoint rollback/apply, rollback-journal commit/apply, super-journal, hot-journal recovery, WAL reader/checkpoint surfaces, B-tree freeblock/freelist/page-move/root-collapse/overflow clusters, JSON table/source/constraint clusters, SELECT SQL text/subquery/group/order clusters, and Unicode GLOB behavior. The new surface is successful savepoint RELEASE with statement-journal current-source admission and next-statement journaling.

Dependency closure: no new support component is needed. The slice reuses lane-local savepoint stack, statement journal, current-source page-image verification, and WAL frame bookkeeping primitives.

Next task: continue with broader pager/VFS transaction application or another non-overlapping pager durability edge; avoid another statement-journal wrapper unless it applies a distinct pager state transition.
