# Pager hot-journal savepoint cache current-source next157

Status: focused PHP behavior growth for `pager-hot-journal-savepoint-cache-current-source-next157`.

This slice adds `SQLitePagerHotJournalSavepointCacheCurrentSourceNextPlan`. It models the pager edge where hot rollback-journal recovery has already advanced the current source token, but a cached page can still carry the recovered token while holding stale bytes. Before an active savepoint captures before-images, the plan fences cache entries by recovered current-source image digest, invalidates stale same-token pages, captures savepoint before-images from recovered pages, rolls back the failed savepoint write, and captures retry before-images from the restored recovered source.

Application smoke: `application-pager-hot-journal-savepoint-cache-current-source-next157.php` covers a copied `wp_options` import where stale same-token cache images for `active_plugins` and plugin settings are rejected before retrying the savepoint.

Verification:

```text
php -l lanes/libsqlite/src/SQLitePagerHotJournalSavepointCacheCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLitePagerHotJournalSavepointCacheCurrentSourceNext157Test.php
php -l lanes/libsqlite/examples/application-pager-hot-journal-savepoint-cache-current-source-next157.php
php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerHotJournalSavepointCacheCurrentSourceNext157Test.php
php lanes/libsqlite/examples/application-pager-hot-journal-savepoint-cache-current-source-next157.php
git diff --check -- lanes/libsqlite
```

Focused result after implementation: `1 test files, 86 assertions, 0 failures`.

Dashboard delta: `phpPass` moves from `69549` to `69635` from 86 newly passing focused assertions in this isolated worktree. Mapped upstream coverage remains `607 / 1589`; this is focused pager behavior over existing rollback-journal/savepoint/cache inventory rather than a new manifest-backed upstream row.

Non-overlap: avoids accepted pager hot-journal savepoint cache next83/next100/next149 release/read and next-statement cache refresh surfaces, next128/next131 statement-subjournal variants, master-journal cache/savepoint/cache-spill slices, rollback-journal apply/commit/super-journal clusters, WAL checkpoint/savepoint/restart/truncate visibility, VFS writer/sync/lock clusters, B-tree, JSON, SELECT, and encoding surfaces. The new behavior is specifically the recovered current-source image-digest fence for same-token pager-cache entries before savepoint and retry before-image capture.

Dependency closure: no new support component is needed. The slice reuses lane-local pager cache, hot-journal recovery, savepoint before-image, and retry write primitives.
