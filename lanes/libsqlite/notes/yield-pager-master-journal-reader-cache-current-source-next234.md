# Pager Master-Journal Reader Cache Current Source Next234

Status: focused PHP behavior growth for `pager-master-journal-reader-cache-current-source-next234`.

`SQLitePagerMasterJournalReaderCacheCurrentSourceNext234Plan` extends the accepted pager master-journal reader-cache current-source fences by checking SQLite page-1 `user_version` and `application_id` metadata after next226 header-counter admission. Readers keep cache pages only when the recovered current source metadata matches the cache row and read ticket; stale Application import metadata reopens before the next read.

Application smoke: `application-pager-master-journal-reader-cache-current-source-next234.php --self-test` models a copied `wp_options` recovery where stale `active_plugins` and `rewrite_rules` cache tickets were keyed before the current `user_version` / `application_id` publication.

Verification:

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNext234Plan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext234Test.php`
- `php -l lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next234.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext234Test.php`
- `php lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next234.php --self-test`
- `git diff --check -- lanes/libsqlite`

Non-overlap: avoids accepted next230 SQLite version-number fencing, next231 freelist header fencing, next226 page-1 change-counter/version-valid-for fencing, next219 page-count/header digest behavior, and accepted rollback-journal/WAL/VFS/super-journal application clusters. This slice only adds `user_version` / `application_id` current-source admission.

Dependency closure: no new support component is needed. The patch reuses lane-local pager master-journal reader-cache current-source primitives.

Next task: wire this metadata fence into the eventual native pager reader-cache entry type once the lane owns direct cache rows instead of bounded plans.
