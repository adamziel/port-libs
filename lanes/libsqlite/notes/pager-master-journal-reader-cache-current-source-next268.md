# Pager Master-Journal Reader Cache Current Source Next268

Status: focused PHP behavior growth for `pager-master-journal-reader-cache-current-source-next268`.

This slice adds a distinct next268 pager reader-cache rollback-source fixture on top of the validated current-source dependency chain. The test and WordPress smoke example confirm that retained pages keep cache hits, stale rollback-source tickets force reader reopen, and inherited recovery receipt, snapshot, generation, and provenance fences continue to compose.

Verification:

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext268Test.php`
- `php -l lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-current-source-next268.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext268Test.php`
- `php lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-current-source-next268.php --self-test`

Non-overlap: next268 remains limited to pager master-journal reader-cache source admission. It avoids broad upstream-suite countability, WAL checkpoint/savepoint, rollback apply/commit, VFS sync/lock, B-tree, JSON, SQL executor, and dashboard changes.
