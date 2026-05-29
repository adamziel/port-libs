# Pager statement-journal savepoint master current-source next123

Status: focused PHP behavior growth for `pager-statement-journal-savepoint-master-current-source-next123`.

This slice adds `SQLitePagerStatementJournalSavepointMasterCurrentSourceNextPlan`. It models the pager boundary where master-journal recovery first establishes the current database source, a failed statement inside an active savepoint writes dirty pages, statement-journal rollback restores only that statement's before-images, and the next statement journal captures retry before-images from the recovered statement-rollback source.

WordPress smoke: `wordpress-pager-statement-savepoint-master-current-source-next123.php` covers a copied `wp_options` plugin import retry after master-journal recovery restores the root/autoload pages and the failed `active_plugins` statement rolls back inside the still-open savepoint.

Verification:

```text
php -l lanes/libsqlite/src/SQLitePagerStatementJournalSavepointMasterCurrentSourceNextPlan.php
No syntax errors detected in lanes/libsqlite/src/SQLitePagerStatementJournalSavepointMasterCurrentSourceNextPlan.php

php -l lanes/libsqlite/tests/SQLitePagerStatementJournalSavepointMasterCurrentSourceNext123Test.php
No syntax errors detected in lanes/libsqlite/tests/SQLitePagerStatementJournalSavepointMasterCurrentSourceNext123Test.php

php -l lanes/libsqlite/examples/wordpress-pager-statement-savepoint-master-current-source-next123.php
No syntax errors detected in lanes/libsqlite/examples/wordpress-pager-statement-savepoint-master-current-source-next123.php

php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerStatementJournalSavepointMasterCurrentSourceNext123Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 91 assertions, 0 failures

php lanes/libsqlite/examples/wordpress-pager-statement-savepoint-master-current-source-next123.php --self-test
wordpress-pager-statement-savepoint-master-current-source-next123 self-test passed
```

Dashboard delta: `phpPass` moves from `47656` to `47747` from 91 newly passing focused PASS lines. Mapped upstream coverage remains `605 / 1589`; this is fresh focused PHP pager behavior over already mapped journal/savepoint primitives rather than a new upstream inventory unit.

Non-overlap: this avoids accepted pager hot-journal savepoint cache, master-journal savepoint current-source, master-journal statement recovery, cache-spill savepoint, WAL byte truncation, VFS savepoint rollback, rollback-journal apply/commit, super-journal commit, WAL checkpoint transaction, B-tree overflow/freelist/page-move clusters, JSON table source/cursor/constraint clusters, SELECT SQL text/group/order/subquery clusters, and Unicode GLOB behavior. The new surface is the composition of master-journal current-source recovery with statement-subjournal rollback and next statement capture inside an active savepoint.

Dependency closure: no new support component is needed. The slice reuses lane-local pager page-image modeling and bounded journal/savepoint current-source primitives.

Next task: continue with broader pager/VFS transaction application or a distinct WAL/pager durability edge; avoid another statement-savepoint wrapper unless it applies a different pager state transition.
