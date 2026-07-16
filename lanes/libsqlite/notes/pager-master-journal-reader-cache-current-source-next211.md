# Pager Master Journal Reader Cache Current Source Next211

Status: focused PHP behavior growth for `pager-master-journal-reader-cache-current-source-next211`.

This slice adds `SQLitePagerMasterJournalReaderCacheCurrentSourceNext211Plan`. It extends the accepted next209 raw master-journal byte fence without repeating it: when master-journal bytes, file token, member order, member tokens, and member headers still match, reader-cache reuse is still rejected if any attached member rollback journal reports a different recovered-page-set digest for the current source.

Application smoke: `application-pager-master-journal-reader-cache-current-source-next211.php` covers a copied `wp_options` import with an attached usermeta rollback journal. Schema/root cache pages remain reusable, but the `active_plugins` cache page is reopened when the attached member journal replayed a different page set under otherwise identical master-journal evidence.

Focused verification:

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNext211Plan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNext211Plan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext211Test.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext211Test.php`
- `php -l lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next211.php`
  - `No syntax errors detected in lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next211.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext211Test.php`
  - `1 test files, 52 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next211.php --self-test`
  - `application-pager-master-journal-reader-cache-current-source-next211 self-test passed`

Expected dashboard delta: `phpPass` moves from `101605` to `101657` from 52 newly passing focused PASS lines. Mapped upstream coverage remains `622 / 1589`; this is focused pager reader-cache current-source behavior over existing master-journal inventory rather than a fresh manifest row.

Non-overlap: avoids accepted next209 raw master-journal bytes, next206 file token, next203 member order, next196 member header, next192 member token, next191 delete-sync, accepted VFS writer/sync/lock/apply paths, WAL checkpoint/savepoint/restart/truncate visibility, rollback/super-journal commit/apply, B-tree, JSON, SELECT, and encoding surfaces. The new behavior is specifically attached member recovered-page-set fencing after all lower master-journal identity checks still admit the cache entry.

Dependency closure: no new support component is needed. The slice reuses lane-local pager master-journal reader-cache current-source primitives and digest maps.

Next task: wire the recovered-page-set fence into a future pager transaction executor so reader-cache entries are owned by the native pager rather than by bounded planning fixtures.
