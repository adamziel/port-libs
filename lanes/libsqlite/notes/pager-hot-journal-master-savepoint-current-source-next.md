# Pager hot-journal master savepoint current source next134

- Slice: `pager-hot-journal-master-savepoint-current-source-next134`.
- Behavior: adds `SQLitePagerHotJournalMasterSavepointCurrentSourceNextPlan`, a bounded pager planner for the non-WAL edge where a hot rollback journal is protected by a master journal, a savepoint is still open, and the pager must reject stale cached master-journal membership before capturing savepoint before-images from the current hot-recovered source.
- WordPress path: `wordpress-pager-hot-journal-master-savepoint-current-source-next134.php --self-test` models a copied `wp_options` import where stale cached master-journal bytes would have skipped recovery, but the current master journal names the database journal and restores `active_plugins` before the savepoint retry writes and rolls back.
- Non-overlap: avoids accepted WAL savepoint master-journal next126, pager hot-journal savepoint cache next100, WAL hot-journal checkpoint/restart/reader slices, rollback-journal apply/commit, VFS savepoint rollback, super-journal commit, and pager cache-spill journal-mode work. This slice is specifically the non-WAL master-journal current-source recheck before open-savepoint before-image capture.
- Dependency closure: no new support component needed; the slice reuses native PHP rollback-journal parsing/recovery and lane-local page-image savepoint modeling.

Verification:

```sh
php -l lanes/libsqlite/src/SQLitePagerHotJournalMasterSavepointCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLitePagerHotJournalMasterSavepointCurrentSourceNext134Test.php
php -l lanes/libsqlite/examples/wordpress-pager-hot-journal-master-savepoint-current-source-next134.php
php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerHotJournalMasterSavepointCurrentSourceNext134Test.php
php lanes/libsqlite/examples/wordpress-pager-hot-journal-master-savepoint-current-source-next134.php --self-test
git diff --check -- lanes/libsqlite
```

Expected dashboard movement: `phpPass` +76 from the 76 independent PASS lines in the focused test. Mapped upstream coverage remains conservative; this is focused pager behavior coverage over existing rollback/master-journal inventory.
