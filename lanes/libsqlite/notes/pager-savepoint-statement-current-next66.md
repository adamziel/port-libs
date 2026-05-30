# Pager savepoint statement current next66

Status: focused PHP behavior growth for pager savepoint `ROLLBACK TO` followed by the next statement subjournal under the still-open savepoint.

This slice adds `SQLiteSavepointStack::rollbackToCurrentAndBeginNextStatementJournal66()`. It models the SQLite pager edge where `ROLLBACK TO savepoint` clears statement journals owned by the target/discarded frames, preserves any outer statement journal, keeps the target savepoint active, and starts the next statement journal from the retained WAL frame prefix.

Verification:

```sh
php -l lanes/libsqlite/src/SQLiteSavepointStack.php
php -l lanes/libsqlite/tests/SQLitePagerSavepointStatementJournalCurrentNext66Test.php
php -l lanes/libsqlite/examples/application-pager-savepoint-statement-current-next66.php
php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerSavepointStatementJournalCurrentNext66Test.php
php lanes/libsqlite/examples/application-pager-savepoint-statement-current-next66.php --self-test
git diff --check -- lanes/libsqlite
```

Focused output:

```text
1 test files, 60 assertions, 0 failures
application-pager-savepoint-statement-current-next66 self-test passed
```

Expected dashboard movement: `phpPass` +60, from 24610 to 24670. `benchmarkDenominator.mapped` is unchanged because this is focused native pager behavior coverage, not a newly mapped upstream inventory unit.

Non-overlap: this avoids accepted WAL byte truncation, page-image rollback, VFS savepoint rollback apply, WAL reader/release/checkpoint current-next slices, rollback-journal commit/apply, super-journal, VFS sync/lock/write clusters, B-tree freeblock/freelist/page-move/root-collapse/overflow clusters, JSON table source/cursor/constraint clusters, SELECT SQL text/subquery/group/order clusters, and Unicode GLOB behavior. The new surface is statement-subjournal cleanup and next-statement journaling after a current savepoint rollback.

Dependency closure: no new support component is needed. The slice reuses lane-local savepoint stack, statement journal, and WAL frame bookkeeping primitives.

Next task: continue with broader pager/VFS transaction application or another non-overlapping WAL durability edge; avoid another savepoint wrapper unless it applies a distinct pager state transition.
