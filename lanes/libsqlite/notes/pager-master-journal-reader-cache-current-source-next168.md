# Pager master-journal reader cache current-source next168

Status: focused PHP behavior growth for `pager-master-journal-reader-cache-current-source-next168`.

This slice adds `SQLitePagerMasterJournalReaderCacheCurrentSourceNext168Plan`. It extends the accepted next164 master-journal reader-cache header fence with a current-source digest and source-generation fence: a reader-cache entry can no longer be admitted just because page bytes, master-journal membership, change counter, schema cookie, and version-valid-for match. If the cache entry came from an older recovered source generation or carries a stale per-page source digest, the next reader reopens against the recovered source before read/write reuse.

Application smoke: `application-pager-master-journal-reader-cache-current-source-next168.php` models copied `wp_options` recovery where the root page is cleanly refreshed, but `active_plugins` and plugin-setting cache pages are rejected because they predate the current master-journal source digest/generation before the next write.

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext168Test.php`
  - `1 test files, 76 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next168.php`
  - `application-pager-master-journal-reader-cache-current-source-next168 self-test passed`
- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNext168Plan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext168Test.php`
- `php -l lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next168.php`
- `git diff --check -- lanes/libsqlite`

Expected dashboard delta: `phpPass` moves from `75459` to `75535` from 76 newly passing focused PASS lines. Mapped upstream coverage remains `611 / 1589`; this is focused pager/master-journal behavior over existing current-source inventory rather than a fresh upstream denominator row.

Non-overlap: this avoids accepted next164 page-1 header change-counter/schema-cookie/version-valid-for reader-cache fencing, next158/159/160/161 master-journal reader-cache membership/digest behavior, hot-cache current-source rebasing, statement-journal savepoint recovery, WAL hot-journal/checkpoint/savepoint slices, VFS rollback/commit/sync/lock writer clusters, B-tree freeblock/freelist/page-move clusters, JSON table source/cursor/constraint clusters, SELECT SQL text/group/order/subquery clusters, and encoding Unicode GLOB work. The new behavior is specifically the current-source digest/generation ABA fence for reader-cache admission after master-journal recovery.

Dependency closure: no new support component is needed. The patch reuses lane-local pager/master-journal reader-cache primitives and bounded PHP page-image/source-digest metadata.
