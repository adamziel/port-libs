# Pager Master-Journal Reader-Cache Current Source Next232

Status: focused PHP behavior growth for `pager-master-journal-reader-cache-current-source-next232`.

This slice adds `SQLitePagerMasterJournalReaderCacheCurrentSourceNext232Plan`. It extends the accepted next229 pager-cache-source fence without repeating it: after master-journal recovery, reader-cache rows and next-read tickets must also match the current database path/attachment namespace. This prevents a cache row for an attached database from being reused for the main database when page number, image, file-token, reader lease, and pager-cache source otherwise look current.

Application smoke: `application-pager-master-journal-reader-cache-current-source-next232.php` models copied `wp_options` recovery where schema/options pages from the main database remain reusable while an image-identical `active_plugins` cache row from an attached users database reopens before plugin import continues.

Focused evidence:

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNext232Plan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNext232Plan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext232Test.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext232Test.php`
- `php -l lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next232.php`
  - `No syntax errors detected in lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next232.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext232Test.php`
  - `1 test files, 54 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next232.php`
  - `application-pager-master-journal-reader-cache-current-source-next232 self-test passed`
- `git diff --check -- lanes/libsqlite`
  - no output

Expected dashboard delta: `phpPass` moves from `113830` to `113884` from 54 newly passing focused PASS lines. Mapped upstream coverage remains `634 / 1589`; this is focused pager reader-cache current-source behavior over existing master-journal inventory rather than a fresh manifest-backed row.

Non-overlap: avoids accepted next229 pager-cache-source admission, next224 reader-lease admission, next218 cleanup-token admission, next212 database file-token admission, raw master-journal bytes, member-token/header/order fences, VFS writer/sync/lock, rollback-journal apply/commit, WAL checkpoint/byte truncation, super-journal commit, B-tree, JSON, SELECT, PRAGMA, trigger, and encoding behavior. The new surface is only the database path/attachment namespace fence after the reader-cache source has already been observed as current.

Dependency closure: no new support component is needed. The behavior reuses existing lane-local pager master-journal reader-cache state, current-source tickets, cleanup-token evidence, reader-lease evidence, and pager-cache-source evidence.

Next task: wire this namespace fence into the eventual native pager cache owner when broader pager transaction execution owns multi-database reader-cache rows directly.
