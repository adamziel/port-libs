# Pager Savepoint Master-Journal Hot Current Source Next140

This slice adds `SQLitePagerSavepointMasterJournalHotCurrentSourceNextPlan`, a bounded pager planner for the non-WAL edge where a hot rollback journal is admitted through the current master journal while a savepoint remains open.

Behavior covered:

- Re-read the current master journal before hot recovery and require it to name the database rollback journal.
- Recover hot pages into the current source before savepoint before-images are captured.
- Roll back failed savepoint writes back to the hot-recovered current source, then capture retry statement before-images from that restored source.
- Preserve the rollback journal and master journal until the outer attached transaction commits; only the explicit commit mode deletes them.

WordPress smoke: `wordpress-pager-savepoint-master-journal-hot-current-source-next140.php --self-test` models a copied `wp_options` import where `active_plugins` is restored from hot current-source bytes, a failed savepoint write rolls back, retry option/transient writes stay dirty, and the master journal is preserved until outer commit.

Verification:

- `php -l lanes/libsqlite/src/SQLitePagerSavepointMasterJournalHotCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerSavepointMasterJournalHotCurrentSourceNext140Test.php`
- `php -l lanes/libsqlite/examples/wordpress-pager-savepoint-master-journal-hot-current-source-next140.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerSavepointMasterJournalHotCurrentSourceNext140Test.php`
- `php lanes/libsqlite/examples/wordpress-pager-savepoint-master-journal-hot-current-source-next140.php --self-test`
- `git diff --check -- lanes/libsqlite`

Non-overlap: avoids accepted WAL byte truncation, savepoint page-image rollback, VFS savepoint rollback apply, rollback-journal commit apply, super-journal commit, hot rollback application, master-journal savepoint cache next138, and pager statement journal/savepoint master current-source next123. This is specifically the current-master hot rollback source feeding savepoint rollback/retry while deferring master-journal deletion until the outer commit.

Dependency closure: no new support component is needed; this reuses bounded native PHP page-image planning already present under `lanes/libsqlite/src`.
