# Pager master-journal reader cache current-source next161

Status: focused PHP behavior growth for `pager-master-journal-reader-cache-current-source-next161`.

This slice adds `SQLitePagerMasterJournalReaderCacheCurrentSourceNext161Plan`. It extends the current-source reader-cache admission boundary with a master-journal member digest fence: a reader-cache page can have the same page image, source id, and epoch as the recovered database image, but it is still invalidated when it was keyed from stale master-journal membership.

WordPress smoke: `wordpress-pager-master-journal-reader-cache-current-source-next161.php` covers a copied `wp_options` recovery where a schema reader-cache page still matches the recovered bytes but carries the old master-journal member digest, so the next read misses cache and the next `active_plugins` write journals from the recovered current source.

Focused evidence:

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNext161Plan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNext161Plan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext161Test.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext161Test.php`
- `php -l lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-current-source-next161.php`
  - `No syntax errors detected in lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-current-source-next161.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext161Test.php`
  - `1 test files, 92 assertions, 0 failures`
- `php lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-current-source-next161.php`
  - `wordpress-pager-master-journal-reader-cache-current-source-next161 self-test passed`

Dashboard delta: `phpPass` moves from `72234` to `72326` from 92 newly passing focused assertions. Mapped upstream coverage remains `609 / 1589`; this is focused pager behavior over already mapped master-journal and reader-cache current-source primitives rather than a fresh upstream inventory row.

Non-overlap: this avoids accepted pager master-journal reader-cache next159, master-journal cache recovery next122, master-journal hot cache next136, savepoint/master-journal reader/cache slices, rollback-journal commit/apply, super-journal commits, WAL byte truncation/checkpoint/savepoint clusters, VFS writer/sync/lock clusters, B-tree overflow/freelist/page-move clusters, JSON table source/cursor/constraint clusters, SELECT SQL text/group/order/subquery clusters, and Unicode GLOB behavior. The new surface is specifically stale master-journal member-digest invalidation for reader-cache pages before the next pager source is used.

Dependency closure: no new support component is needed. The slice reuses lane-local pager master-journal current-source and bounded page-image cache primitives.

Next task: continue with broader pager/VFS transaction application or a release-runner blocker; avoid another master-journal cache wrapper unless it adds a distinct file-handle, digest, or upstream-runner blocker.
