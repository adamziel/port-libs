# Pager savepoint hot-journal cache current-source next128

Status: focused PHP behavior growth for `pager-savepoint-hot-journal-cache-current-source-next128`.

This slice adds `SQLitePagerSavepointHotJournalCacheCurrentSourceNextPlan`. It models the pager boundary where hot rollback-journal recovery changes the current page-cache source, `ROLLBACK TO` restores savepoint before-images while keeping the savepoint active, and the next statement subjournal must capture retry before-images from the hot-journal recovered current source instead of stale cached or dirty aborted pages.

Application smoke: `application-pager-savepoint-hot-journal-cache-current-source-next128.php` covers a copied `wp_options` plugin import retry after a hot journal restores `active_plugins` and autoload index pages, discards stale/dirty page-cache entries, rolls back the failed savepoint write, and retries from the recovered current source.

Verification:

```text
php -l lanes/libsqlite/src/SQLitePagerSavepointHotJournalCacheCurrentSourceNextPlan.php
No syntax errors detected in lanes/libsqlite/src/SQLitePagerSavepointHotJournalCacheCurrentSourceNextPlan.php

php -l lanes/libsqlite/tests/SQLitePagerSavepointHotJournalCacheCurrentSourceNext128Test.php
No syntax errors detected in lanes/libsqlite/tests/SQLitePagerSavepointHotJournalCacheCurrentSourceNext128Test.php

php -l lanes/libsqlite/examples/application-pager-savepoint-hot-journal-cache-current-source-next128.php
No syntax errors detected in lanes/libsqlite/examples/application-pager-savepoint-hot-journal-cache-current-source-next128.php

php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerSavepointHotJournalCacheCurrentSourceNext128Test.php
1 test files, 80 assertions, 0 failures

php lanes/libsqlite/examples/application-pager-savepoint-hot-journal-cache-current-source-next128.php --self-test
application-pager-savepoint-hot-journal-cache-current-source-next128 self-test passed
```

Dashboard delta: `phpPass` moves from `52453` to `52533` from 80 newly passing focused PASS lines. Mapped upstream coverage remains `606 / 1589`; this is fresh focused PHP pager behavior over already mapped journal/savepoint primitives rather than a new upstream inventory row.

Non-overlap: this avoids accepted pager hot-journal savepoint cache next100 as a standalone release-read cache helper, master-journal cache recovery next122, statement-journal master current-source next123, WAL checkpoint savepoint hot-journal next126, WAL byte truncation, VFS savepoint rollback, rollback-journal apply/commit, super-journal commit, VFS writer/sync/lock clusters, B-tree overflow/freelist/page-move clusters, JSON table source/cursor/constraint clusters, SELECT SQL text/group/order/subquery clusters, and Unicode GLOB behavior. The new surface is the next statement subjournal capture after hot-journal recovery plus `ROLLBACK TO` invalidates stale cache entries and preserves the recovered current source token.

Dependency closure: no new support component is needed. The slice reuses lane-local pager current-source/page-cache modeling and bounded journal/savepoint primitives.
