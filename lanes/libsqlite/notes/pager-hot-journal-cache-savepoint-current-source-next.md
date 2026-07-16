# Pager hot-journal cache savepoint current-source next131

Status: focused PHP behavior growth for `pager-hot-journal-cache-savepoint-current-source-next131`.

This slice adds `SQLitePagerHotJournalCacheSavepointCurrentSourceNextPlan`. It models the pager boundary after hot rollback-journal recovery where clean page-cache entries are retagged to the recovered current-source token, hot-journal pages replace stale cache images, dirty or stale savepoint cache entries are invalidated, `ROLLBACK TO` restores inner savepoint before-images, active savepoint cursors re-read against the recovered source, and the next statement subjournal captures before-images from that refreshed source.

Application smoke: `application-pager-hot-journal-cache-savepoint-current-source-next131.php` covers a copied `wp_options` plugin settings retry after hot-journal recovery. It proves stale active-plugin cache pages and dirty failed plugin writes cannot shadow recovered current-source images before retrying autoload updates.

Verification:

```text
php -l lanes/libsqlite/src/SQLitePagerHotJournalCacheSavepointCurrentSourceNextPlan.php
No syntax errors detected in lanes/libsqlite/src/SQLitePagerHotJournalCacheSavepointCurrentSourceNextPlan.php

php -l lanes/libsqlite/tests/SQLitePagerHotJournalCacheSavepointCurrentSourceNext131Test.php
No syntax errors detected in lanes/libsqlite/tests/SQLitePagerHotJournalCacheSavepointCurrentSourceNext131Test.php

php -l lanes/libsqlite/examples/application-pager-hot-journal-cache-savepoint-current-source-next131.php
No syntax errors detected in lanes/libsqlite/examples/application-pager-hot-journal-cache-savepoint-current-source-next131.php

php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerHotJournalCacheSavepointCurrentSourceNext131Test.php
1 test files, 85 assertions, 0 failures

php lanes/libsqlite/examples/application-pager-hot-journal-cache-savepoint-current-source-next131.php --self-test
application-pager-hot-journal-cache-savepoint-current-source-next131 self-test passed
```

Dashboard delta: `phpPass` increases by 85 focused PASS lines from this isolated worktree's lane status. Mapped upstream coverage remains unchanged; this is fresh focused pager behavior over already mapped rollback-journal/savepoint primitives.

Non-overlap: avoids accepted pager hot-journal savepoint cache next100, pager statement/hot-journal current-source next97/next128, master-journal cache recovery, WAL hot-journal checkpoint reader next120/next122/next129, WAL savepoint byte truncation, rollback-journal apply/commit, super-journal commit, VFS writer/sync/lock clusters, B-tree overflow/freelist/page-move clusters, JSON table source/cursor/constraint clusters, SELECT SQL text/group/order/subquery clusters, and Unicode GLOB behavior. The new surface is multi-savepoint cache source retagging and active cursor revalidation before the next statement subjournal captures recovered-source before-images.

Dependency closure: no new support component is needed; this composes lane-local pager cache source tokens with savepoint before-image and hot-journal recovery models.
