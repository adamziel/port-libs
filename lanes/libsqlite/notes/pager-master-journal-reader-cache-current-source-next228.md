# Pager Master-Journal Reader Cache Current Source Next228

Status: focused PHP behavior growth for `pager-master-journal-reader-cache-current-source-next228`.

This slice adds `SQLitePagerMasterJournalReaderCacheCurrentSourceNext228Plan`. It extends the accepted next224 reader-lease current-source fence without repeating it: after master-journal cleanup, database file-token, member-journal, reader-lease, and recovered page-set checks pass, the plan also fences reader-cache reuse on the per-page current-source payload digest. A cache row with the same page number and source ticket now reopens when its payload digest predates the recovered master-journal source.

Application smoke: `application-pager-master-journal-reader-cache-current-source-next228.php` models a copied `wp_options` recovery where schema and options-root reader-cache pages remain usable, while an `active_plugins` cache row with a stale page payload digest misses cache before plugin import resumes.

Focused evidence:

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNext228Plan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNext228Plan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext228Test.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext228Test.php`
- `php -l lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next228.php`
  - `No syntax errors detected in lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next228.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext228Test.php`
  - `1 test files, 61 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next228.php`
  - `application-pager-master-journal-reader-cache-current-source-next228 self-test passed`
- `git diff --check -- lanes/libsqlite`
  - no output

Expected dashboard delta: `phpPass` moves from `110487` to `110548` from 61 newly passing focused PASS lines. Mapped upstream coverage remains `628 / 1589`; this is focused pager reader-cache behavior over existing master-journal current-source inventory rather than a fresh manifest-backed row.

Non-overlap: avoids accepted next224 reader lease, next221/next218 master cleanup and source-token fences, next170/next174 rollback-journal aggregate source and canonical member-set fences, accepted VFS/WAL rollback apply, savepoint byte truncation, checkpoint transaction, process locking, B-tree, JSON, SELECT, PRAGMA, and encoding clusters. The new surface is only per-page payload digest admission before current-source reader-cache reuse.

Dependency closure: no new support component is needed. The slice reuses lane-local pager master-journal reader-cache, current-source digest, and reader-lease primitives.

Next task: wire per-page payload-digest misses into a native pager cache owner when broader pager transaction execution takes direct ownership of reader-cache rows.
