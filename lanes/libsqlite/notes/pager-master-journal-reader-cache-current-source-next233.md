# Pager Master-Journal Reader Cache Current Source Next233

Status: focused PHP behavior growth for `pager-master-journal-reader-cache-current-source-next233`.

This slice adds `SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan`. It extends the accepted next229 pager-cache source fence without repeating it: after master-journal cleanup, database file-token, member-journal, reader-lease, and pager-cache source checks pass, the plan also fences reader-cache reuse on the read-transaction token that opened the reader snapshot. A cache row whose bytes and cache source still look current now reopens when its read transaction predates the recovered master-journal source.

Application smoke: `application-pager-master-journal-reader-cache-current-source-next233.php` models a copied `wp_options` recovery where schema and options-root reader-cache pages remain usable, while an `active_plugins` read opened before master-journal recovery misses cache before plugin import resumes.

Focused evidence:

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext233Test.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext233Test.php`
- `php -l lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next233.php`
  - `No syntax errors detected in lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next233.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext233Test.php`
  - `1 test files, 64 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next233.php`
  - `application-pager-master-journal-reader-cache-current-source-next233 self-test passed`

Expected dashboard delta: `phpPass` moves from `113830` to `113894` from 64 newly passing focused PASS lines. Mapped upstream coverage remains `634 / 1589`; this is focused pager reader-cache behavior over existing master-journal current-source inventory rather than a fresh manifest-backed row.

Non-overlap: avoids accepted next229 pager-cache source, next228 payload digest, next224 reader lease, next221/next218 cleanup and source-token fences, next170/next174 rollback-journal source fences, accepted VFS/WAL rollback apply, savepoint byte truncation, checkpoint transaction, process locking, B-tree, JSON, SELECT, PRAGMA, and encoding clusters. The new surface is only read-transaction snapshot admission before current-source reader-cache reuse.

Dependency closure: no new support component is needed. The slice reuses lane-local pager master-journal reader-cache, reader-lease, and pager-cache source primitives.

Next task: wire read-transaction cache misses into a native pager cache owner when broader pager transaction execution takes direct ownership of reader-cache rows.
