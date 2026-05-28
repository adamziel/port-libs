# Pager Master-Journal Reader-Cache Current Source Next238

Status: focused PHP behavior growth for `pager-master-journal-reader-cache-current-source-next238`.

This slice adds `SQLitePagerMasterJournalReaderCacheCurrentSourceNext238Plan`. It extends the accepted next235 database change-counter fence without repeating it: after master-journal recovery, reader-cache rows and next-read tickets must also match the recovered `sqlite_schema` root digest. This prevents a page image from being reused after DDL changed schema-root content under the master-journal boundary while path/source/lease and database change-counter tickets otherwise look current.

WordPress smoke: `wordpress-pager-master-journal-reader-cache-current-source-next238.php` models copied `wp_options` recovery where schema/options pages remain reusable when the schema-root digest matches the recovered DDL state, while an image-identical `active_plugins` cache row from before plugin-table DDL reopens before plugin import continues.

Focused evidence:

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNext238Plan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNext238Plan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext238Test.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext238Test.php`
- `php -l lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-current-source-next238.php`
  - `No syntax errors detected in lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-current-source-next238.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext238Test.php`
  - `1 test files, 57 assertions, 0 failures`
- `php lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-current-source-next238.php`
  - `wordpress-pager-master-journal-reader-cache-current-source-next238 self-test passed`

Expected dashboard delta: `phpPass` moves from `118322` to `118379` from 57 newly passing focused PASS lines. Mapped upstream coverage remains `641 / 1589`; this is focused pager reader-cache current-source behavior over existing master-journal inventory rather than a fresh manifest-backed row.

Non-overlap: avoids accepted next235 database change-counter admission, next232 database path namespace admission, next229 pager-cache-source admission, next224 reader-lease admission, next218 cleanup-token admission, schema-cookie/page-count/database-header digest fences, raw master-journal bytes, member-token/header/order fences, VFS writer/sync/lock, rollback-journal apply/commit, WAL checkpoint/byte truncation, super-journal commit, B-tree, JSON, SELECT, PRAGMA, trigger, and encoding behavior. The new surface is only the `sqlite_schema` root digest fence after database change-counter and path/source tickets have already been observed as current.

Dependency closure: no new support component is needed. The behavior reuses existing lane-local pager master-journal reader-cache state, current-source tickets, cleanup-token evidence, reader-lease evidence, pager-cache-source evidence, database path namespace evidence, SQLite header change-counter evidence, and schema-root digest admission evidence.

Next task: wire this schema-root digest fence into the eventual native pager cache owner when broader pager transaction execution owns reader-cache generations directly.
