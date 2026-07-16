# Pager master-journal reader cache current-source next159

Status: focused PHP behavior growth for `pager-master-journal-reader-cache-current-source-next159`.

This slice adds `SQLitePagerMasterJournalReaderCacheCurrentSourceNext159Plan`. It models the pager boundary where a connection has reader-cache pages and a cached master-journal member list, but recovery must re-read the current master-journal bytes before admitting those cached reader pages for the next read/write source.

The plan rejects stale cached master-journal membership, restores current master-journal recovered pages, retains cache pages that already match the recovered current source, refreshes clean unpinned stale cache pages, invalidates pinned/dirty/wrong-source/wrong-epoch reader cache pages, and captures next-write before-images from the recovered current source.

Application smoke: `application-pager-master-journal-reader-cache-current-source-next159.php` covers copied `wp_options` recovery where stale pinned `active_plugins` and dirty plugin settings cache pages are discarded after reading the current master journal, and the next `active_plugins` write journals from the recovered current-source page.

Verification:

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNext159Plan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNext159Plan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext159Test.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext159Test.php`
- `php -l lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next159.php`
  - `No syntax errors detected in lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next159.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext159Test.php`
  - `1 test files, 84 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next159.php`
  - `application-pager-master-journal-reader-cache-current-source-next159 self-test passed`

Dashboard delta: `phpPass` moves from `70146` to `70230` from 84 newly passing focused PASS lines. Mapped upstream coverage remains `608 / 1589`; this is focused pager behavior over already mapped master-journal and reader-cache current-source primitives rather than a fresh upstream inventory row.

Non-overlap: this avoids accepted pager master-journal cache recovery next122, master-journal hot cache next136, master-journal savepoint cache next138, savepoint master-journal reader next146, statement-journal savepoint master next123, rollback-journal commit/apply, super-journal commits, WAL byte truncation/checkpoint/savepoint clusters, VFS writer/sync/lock clusters, B-tree overflow/freelist/page-move clusters, JSON table source/cursor/constraint clusters, SELECT SQL text/group/order/subquery clusters, and Unicode GLOB behavior. The new surface is reader-cache admission against freshly read current master-journal membership before the next pager source is used.

Dependency closure: no new support component is needed. The slice reuses lane-local pager master-journal current-source and bounded page-image cache primitives.

Next task: continue with broader pager/VFS transaction application or release-runner blockers; avoid another master-journal cache wrapper unless it applies a distinct file-handle or upstream-runner blocker.
