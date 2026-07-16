# Pager master-journal reader-cache current-source next259

Status: focused PHP behavior growth for `pager-master-journal-reader-cache-current-source-next259`.

This slice adds `SQLitePagerMasterJournalReaderCacheCurrentSourceNext259Plan`. It layers SQLite page-1 `version-valid-for` admission on top of the accepted next256 database-header schema-cookie fence. A reader-cache page can cross a recovered master-journal boundary only when current-source provenance, database header change-counter, schema-cookie, and version-valid-for tickets all match the current source.

Application smoke: `application-pager-master-journal-reader-cache-current-source-next259.php` models copied `wp_options` recovery where stale page-1 schema and `active_plugins` readers reopen before plugin import resumes.

Verification:

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNext259Plan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext259Test.php`
- `php -l lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next259.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext259Test.php`
  - `1 test files, 57 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next259.php`
  - `application-pager-master-journal-reader-cache-current-source-next259 self-test passed`

Expected dashboard delta: `phpPass` moves from `137964` to `138021` from 57 newly passing focused PASS lines. Mapped upstream coverage remains `683 / 1589`; this is focused pager reader-cache current-source behavior over existing master-journal inventory rather than a fresh manifest row.

Non-overlap: avoids accepted next256 schema-cookie, next253 change-counter, current-source provenance, statement-root, schema-reparse, read-transaction, master-journal byte/token/member fences, rollback-journal apply/commit, WAL checkpoint/savepoint, VFS writer/sync/lock, B-tree, JSON, SQL executor, and encoding behavior. The new surface is specifically SQLite header `version-valid-for` ticket admission before master-journal reader-cache reuse.

Dependency closure: no new support component is needed. The slice reuses lane-local pager master-journal reader-cache current-source primitives.
