# Pager master-journal reader cache current-source next158

Status: focused PHP behavior growth for `pager-master-journal-reader-cache-current-source-next158`.

This slice adds `SQLitePagerMasterJournalReaderCacheCurrentSourceNext158Plan`, a bounded pager planner for the edge where master-journal recovery changes the current database source while a reader cache still contains pages from an older source token, epoch, or reader end-frame. Matching clean reader pages are retained with the new source token, clean stale pages may be refreshed from recovered current-source bytes, and pinned or source-stale reader pages are invalidated before the next read.

WordPress smoke: `wordpress-pager-master-journal-reader-cache-current-source-next158.php` models copied `wp_options` reads after master-journal recovery. It keeps a valid schema reader page, refreshes a stale options page, and invalidates a pinned stale plugin-setting reader page so the next read uses recovered database bytes.

Verification:

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNext158Plan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext158Test.php`
- `php -l lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-current-source-next158.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext158Test.php`
- `php lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-current-source-next158.php`
- `git diff --check -- lanes/libsqlite`

Expected dashboard delta: `phpPass` moves from `69549` to `69633` from 84 newly passing focused PASS lines. Mapped upstream coverage remains `607 / 1589`; this is focused pager behavior over existing master-journal/cache inventory rather than a fresh upstream denominator row.

Non-overlap: this avoids accepted master-journal cache recovery next122, statement/savepoint master-journal next123, savepoint/hot-cache current-source next128, hot-cache source-token rebasing next136, rollback-journal apply/commit, super-journal commit, WAL checkpoint/restart/truncate visibility, VFS writer/sync/lock clusters, and B-tree/JSON/SELECT/encoding surfaces. The new behavior is specifically read-only reader-cache current-source validation after master-journal recovery before the next read.

Dependency closure: no new support component is needed. The slice reuses lane-local pager master-journal recovery and reader cache source tracking primitives.

Next: connect the same source-token rule to broader pager/VFS transaction application if a later slice needs durable file-handle writes; avoid another standalone cache wrapper unless it applies new write, checkpoint, or recovery state.
