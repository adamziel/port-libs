# Pager Savepoint Hot-Journal Master Current Source Next142

Status: focused PHP behavior growth for a pager savepoint retry after the current master journal names the hot rollback journal.

This slice adds `SQLitePagerSavepointHotJournalMasterCurrentSourceNextPlan`. It models the current-source ordering where the master journal first validates the hot rollback journal, hot-journal pages restore the database image, and a retry savepoint captures before-images from that recovered image rather than from dirty crashed database bytes. `ROLLBACK TO` then restores the hot-journal current source for the next pager reads.

WordPress smoke: `wordpress-pager-savepoint-hot-journal-master-current-source-next142.php` covers a copied `wp_options` plugin activation retry. The smoke proves `active_plugins` is restored from the hot journal before the retry savepoint captures its before-image.

Verification:

```sh
php -l lanes/libsqlite/src/SQLitePagerSavepointHotJournalMasterCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLitePagerSavepointHotJournalMasterCurrentSourceNext142Test.php
php -l lanes/libsqlite/examples/wordpress-pager-savepoint-hot-journal-master-current-source-next142.php
php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerSavepointHotJournalMasterCurrentSourceNext142Test.php
php lanes/libsqlite/examples/wordpress-pager-savepoint-hot-journal-master-current-source-next142.php
git diff --check -- lanes/libsqlite
```

Expected focused delta: 78 new PASS lines in the lane-scoped test file. `lane-status.json` raises `phpPass` from 62524 to 62602 and leaves mapped coverage unchanged because this is behavior-backed focused PHP coverage rather than a newly mapped upstream manifest row.

Non-overlap: this avoids accepted pager savepoint master-journal current-source next92, master-journal cache recovery next122, master-journal hot cache next136, cache-spill next132, hot-journal savepoint cache next100, statement-journal savepoint/master slices, WAL byte truncation/checkpoint/savepoint paths, VFS savepoint rollback/write/sync/lock clusters, rollback-journal commit/apply/super-journal clusters, B-tree/JSON/SELECT/encoding surfaces, and all batch107-113/139 accepted surfaces. The new surface is the pager ordering where master-journal membership validates hot rollback recovery before a retry savepoint captures current-source before-images.

Dependency closure: no new support component is needed. The slice reuses lane-local pager rollback-journal, savepoint before-image, and master-journal current-source conventions.
