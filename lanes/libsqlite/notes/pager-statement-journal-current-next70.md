# Pager statement journal current next70

Status: focused PHP behavior growth for a failed statement subjournal followed by the next statement journal under the same open savepoint.

This slice adds `SQLiteSavepointStack::rollbackStatementAndBeginNextStatementJournal70()`. It models the pager edge where a statement error rolls back only the current statement journal, truncates pending WAL state to the statement start frame, keeps the enclosing savepoint active, and starts the next statement journal from that retained prefix.

Verification:

```sh
php -l lanes/libsqlite/src/SQLiteSavepointStack.php
php -l lanes/libsqlite/tests/SQLitePagerStatementJournalCurrentNext70Test.php
php -l lanes/libsqlite/examples/application-pager-statement-journal-current-next70.php
php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerStatementJournalCurrentNext70Test.php
php lanes/libsqlite/examples/application-pager-statement-journal-current-next70.php --self-test
git diff --check -- lanes/libsqlite
```

Expected focused movement: `phpPass` +54 from `26014` to `26068`, from the 54 independent PASS lines in `SQLitePagerStatementJournalCurrentNext70Test.php`. Mapped upstream coverage remains `464 / 1589`; this is a focused current/next pager behavior assertion cluster, not a fresh upstream denominator admission.

Non-overlap: this avoids accepted pager savepoint statement-journal rollback-to-current/current-next66, savepoint release-next68, WAL byte truncation, VFS savepoint rollback apply, rollback-journal commit/apply, super-journal, WAL reader/checkpoint handoff, VFS sync/lock/write/file-control clusters, B-tree freeblock/freelist/page-move/root-collapse/overflow clusters, JSON table source/cursor/constraint clusters, SELECT SQL text/subquery/group/order clusters, and Unicode GLOB behavior. The new surface is statement-subjournal failure recovery followed by the next statement journal within an already retained savepoint.

Dependency closure: no new support component is needed. The slice reuses lane-local savepoint stack, statement journal, and WAL frame bookkeeping primitives.
