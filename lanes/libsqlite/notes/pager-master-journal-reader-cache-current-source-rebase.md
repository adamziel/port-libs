# Pager master-journal reader cache current-source rebase

Status: focused PHP behavior preserved after consolidating the production helper to `SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::planCurrentSourceReaderCacheRebase()`.

This slice uses the canonical `SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan` pager planner for the edge where master-journal recovery changes the current database source while a reader cache still contains pages from an older source token, epoch, or reader end-frame. Matching clean reader pages are retained with the new source token, clean stale pages may be refreshed from recovered current-source bytes, and pinned or source-stale reader pages are invalidated before the next read.

Application smoke: `application-pager-master-journal-reader-cache-current-source-rebase.php` models copied `wp_options` reads after master-journal recovery. It keeps a valid schema reader page, refreshes a stale options page, and invalidates a pinned stale plugin-setting reader page so the next read uses recovered database bytes.

Verification:

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceRebaseTest.php`
- `php -l lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-rebase.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceRebaseTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCache*Test.php`
- `php lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-rebase.php --self-test`
- `git diff --check -- lanes/libsqlite`

Expected dashboard delta: no `phpPass` or mapped-coverage movement; this is production suffix cleanup that preserves the existing 84 focused PASS lines while removing a numbered production method name.

Non-overlap: this avoids accepted master-journal cache recovery next122, statement/savepoint master-journal next123, savepoint/hot-cache current-source next128, hot-cache source-token rebasing next136, rollback-journal apply/commit, super-journal commit, WAL checkpoint/restart/truncate visibility, VFS writer/sync/lock clusters, and B-tree/JSON/SELECT/encoding surfaces. The new behavior is specifically read-only reader-cache current-source validation after master-journal recovery before the next read.

Dependency closure: no new support component is needed. The slice reuses lane-local pager master-journal recovery and reader cache source tracking primitives.

Next: connect the same source-token rule to broader pager/VFS transaction application if a later slice needs durable file-handle writes; avoid another standalone cache wrapper unless it applies new write, checkpoint, or recovery state.
