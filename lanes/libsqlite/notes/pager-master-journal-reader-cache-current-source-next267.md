# Pager Master-Journal Reader Cache Current Source Next267

Status: focused PHP behavior growth for `pager-master-journal-reader-cache-current-source-next267`.

This slice carries the validated pager reader-cache dependency forward from next263-266 and adds a distinct next267 rollback-source fence fixture. It reuses the accepted pager spill-drain and master-journal recovery receipt admission path, then verifies that cached Application reader pages reopen when their rollback-journal reader-source ticket predates the current master-journal source.

Verification:

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext267Test.php`
- `php -l lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next267.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext267Test.php`
- `php lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next267.php --self-test`

Non-overlap: next267 is scoped to pager reader-cache rollback-source reuse after master-journal recovery. It does not change WAL, VFS, B-tree, JSON, SQL executor, PRAGMA, trigger, encoding, dashboard, or private status behavior.
