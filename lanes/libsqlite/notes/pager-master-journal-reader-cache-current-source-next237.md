# Pager Master-Journal Reader Cache Current Source Next237

Status: focused PHP behavior growth for `pager-master-journal-reader-cache-current-source-next237`.

`SQLitePagerMasterJournalReaderCacheCurrentSourceNext237Plan` extends the accepted pager master-journal reader-cache current-source fences by checking the SQLite page-1 schema-format number after next234 `user_version` / `application_id` admission. Readers keep cache pages only when the recovered current source still uses the same btree payload interpretation format; stale Application import tickets reopen before using cached `active_plugins` or rewrite-rule pages.

Application smoke: `application-pager-master-journal-reader-cache-current-source-next237.php --self-test` models a copied `wp_options` recovery where schema format 3 tickets are rejected after recovery publishes a schema format 4 current source.

Verification:

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNext237Plan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext237Test.php`
- `php -l lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next237.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext237Test.php` -> `1 test files, 51 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next237.php --self-test`
- `git diff --check -- lanes/libsqlite`

Non-overlap: avoids accepted next230 SQLite version-number fencing, next231 freelist header fencing, next234 `user_version` / `application_id` fencing, earlier schema-cookie fences, and accepted rollback-journal/WAL/VFS/super-journal application clusters. This slice only adds schema-format current-source admission.

Dependency closure: no new support component is needed. The patch reuses lane-local pager master-journal reader-cache current-source primitives.

Next task: wire this schema-format fence into the eventual native pager reader-cache entry type once the lane owns direct cache rows instead of bounded plans.
