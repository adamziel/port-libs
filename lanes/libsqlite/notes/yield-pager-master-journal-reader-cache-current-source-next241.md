# Pager Master-Journal Reader Cache Current Source Next241

Status: focused PHP behavior growth for `pager-master-journal-reader-cache-current-source-next241`.

`SQLitePagerMasterJournalReaderCacheCurrentSourceNext241Plan` extends the accepted pager master-journal reader-cache current-source fences by checking the page-1 schema cookie after next238 schema-root digest admission. Readers keep recovered cache pages only when the schema cookie observed by the cache row and read ticket matches the master-journal-recovered current source; stale WordPress plugin DDL cache tickets reopen before the next read.

WordPress smoke: `wordpress-pager-master-journal-reader-cache-current-source-next241.php` models a copied `wp_options` recovery where `active_plugins` was cached before plugin-table DDL advanced the schema cookie.

Verification:

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNext241Plan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext241Test.php`
- `php -l lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-current-source-next241.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext241Test.php`
- `php lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-current-source-next241.php`
- `git diff --check -- lanes/libsqlite`

Non-overlap: avoids accepted next238 schema-root digest, next235 database change-counter, next232 database path, next229 pager-cache-source, next224 reader-lease, and accepted WAL/savepoint, rollback-journal apply, VFS writer/sync/lock, B-tree, JSON, and SELECT clusters. This slice only adds schema-cookie admission after those prior fences pass.

Dependency closure: no new support component is needed. The patch reuses lane-local pager master-journal reader-cache current-source primitives.

Next task: wire the schema-cookie fence into the eventual native pager reader-cache entry type once bounded plans are consolidated into direct cache rows.
