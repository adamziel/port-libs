# Pager master-journal reader-cache current-source next185

Status: focused PHP behavior growth for `pager-master-journal-reader-cache-current-source-next185`.

This slice adds `SQLitePagerMasterJournalReaderCacheCurrentSourceNext185Plan`. It models a master-journal recovery boundary where the rollback journal has a finite original database page count. Before next reader-cache admission, the current source restores in-range journal pages, ignores journal records beyond the original database size, truncates tail pages from the database image, invalidates cache rows that now point past EOF, blocks next reads/writes of truncated pages, and lets retained/refreshed cache rows serve only pages still in the recovered current source.

Application smoke: `application-pager-master-journal-reader-cache-current-source-next185.php` models copied `wp_options` recovery where the schema cache page survives finite master-journal recovery, `active_plugins` is rewritten from the recovered source, and transient option tail pages removed by rollback are not served from stale reader cache.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext185Test.php`
  - `1 test files, 95 assertions, 0 failures`
- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNext185Plan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext185Test.php`
- `php -l lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next185.php`
- `php lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next185.php`
- `git diff --check -- lanes/libsqlite`

Expected dashboard delta: `phpPass` moves from `87410` to `87505` from 95 newly passing focused PASS lines in this isolated worktree. Mapped upstream coverage remains `615 / 1589`; this is focused pager current-source behavior over existing rollback-journal/master-journal inventory rather than a fresh manifest row.

Non-overlap: avoids accepted pager master-journal reader-cache next180/next182 behavior by adding only finite rollback-journal original database-size truncation before reader-cache reuse. It does not repeat unknown-page-count EOF checksum scanning, page-one format-ticket fences, rollback-journal commit/apply, VFS writer/sync/lock, B-tree overflow/freelist/page-move, JSON table, SQL SELECT text, or encoding/GLOB surfaces.

Dependency closure: no new support component is needed. The slice reuses lane-local rollback-journal checksum validation, finite initial database page-count parsing, and pager reader-cache current-source primitives.
