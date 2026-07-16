# Pager master-journal reader-cache current-source next166

Status: focused PHP behavior growth for `pager-master-journal-reader-cache-current-source-next166`.

This slice adds `SQLitePagerMasterJournalReaderCacheCurrentSourceNext166Plan`. It models the pager reader-cache fence after master-journal recovery when the next source advances its reader generation, schema cookie, and page count. Unchanged current-source pages can be reused by next readers, but dirty pages, pinned changed pages, stale generation/schema/page-count cache rows, master-digest mismatches, changed pages, and pages truncated out of the next source are forced to reopen before serving the next read.

Application smoke: `application-pager-master-journal-reader-cache-current-source-next166.php` models copied `wp_options` import behavior where unchanged schema/autoload pages survive a master-journal recovery boundary, while `active_plugins` and truncated overflow cache rows are reopened against the next source generation before plugin import continues.

Verification:

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNext166Plan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext166Test.php`
- `php -l lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next166.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext166Test.php`
- `php lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next166.php`
- `git diff --check -- lanes/libsqlite`

Expected dashboard delta: `phpPass` moves from `74754` to `74857` from 103 newly passing focused PASS lines. Mapped upstream coverage remains `610 / 1589`; this is focused pager reader-cache current-source behavior over existing master-journal inventory rather than a fresh manifest row.

Non-overlap: avoids accepted pager master-journal reader-cache next161/next162/next163, master-journal hot-cache/cache-spill/savepoint slices, hot-journal savepoint cache next157, rollback-journal apply/commit/super-journal, WAL checkpoint/savepoint/restart/truncate visibility, VFS writer/sync/lock clusters, B-tree, JSON, SELECT, and encoding surfaces. The new behavior is specifically the next-source reader-cache generation/schema/page-count fence after current master-journal recovery.

Dependency closure: no new support component is needed. The slice reuses lane-local pager master-journal membership and reader-cache current-source primitives.
