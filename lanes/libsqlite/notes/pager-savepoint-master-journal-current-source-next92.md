# pager-savepoint-master-journal-current-source-next92

Status: focused PHP behavior growth for pager savepoint retry after current-source master-journal hot rollback.

This slice adds `SQLitePagerSavepointMasterJournalCurrentSourceNextPlan` and a VFS writer entrypoint that compose the accepted current-source master-journal hot rollback path with the next savepoint retry. The retry savepoint captures before-images from the recovered database image, not from stale dirty current bytes, and can append a new page after attached-database master-journal recovery.

Application path: `application-pager-savepoint-master-journal-current-source-next92.php` models a copied `wp_options` plugin import that crashes with a master journal across main and attached site databases, then retries the plugin savepoint after recovery.

Verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerSavepointMasterJournalCurrentSourceNext92Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 61 assertions, 0 failures
```

PASS delta: `+61` focused PASS lines. `lane-status.json` `phpPass` moves from `35916` to `35977`. Mapped upstream coverage is unchanged because this composes already mapped pager/master-journal/savepoint current-source primitives.

Non-overlap: avoids accepted pager master-journal hot rollback current-source next89, WAL byte truncation, VFS savepoint rollback, rollback-journal commit/apply, super-journal commits, statement-journal recovery, WAL reader/checkpoint clusters, B-tree freeblock/page-move/overflow clusters, JSON table source/cursor/constraint work, SELECT SQL text/subquery/group/order clusters, Unicode GLOB, and VFS writer/sync/lock clusters. The new surface is the savepoint retry before-image source after master-journal current-source recovery.

Dependency closure: no new support component is needed. The slice reuses lane-local rollback-journal parsing, master-journal current-source recovery, savepoint page-image semantics, and VFS atomic write application.

Next task: continue with broader pager/VFS transaction application or a distinct WAL/checkpoint durability edge; avoid another master-journal wrapper unless it proves a new storage state transition.
