# Pager master-journal reader cache current-source next162

Status: focused PHP behavior growth for `pager-master-journal-reader-cache-current-source-next162`.

This slice adds `SQLitePagerMasterJournalReaderCacheCurrentSourceNext162Plan`, a bounded pager planner for the edge where master-journal recovery changes the current source while the next reader may still present stale reader tickets or speculative next-source cache pages. The plan admits only clean cache pages whose source id, epoch, end-frame ticket, and page digest match the recovered current source. It invalidates dirty, pinned, stale-source, stale-epoch, stale-image, and speculative next-source cache entries before the next read.

Application smoke: `application-pager-master-journal-reader-cache-current-source-next162.php` models a copied `wp_options` database after master-journal recovery. It reuses a schema reader cache hit, reloads a stale options-root page from the recovered current source, and forces a speculative plugin reader to reopen on the current source.

Verification:

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNext162Plan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext162Test.php`
- `php -l lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next162.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext162Test.php`
  - `1 test files, 78 assertions, 0 failures`
  - `78` focused PASS lines
- `php lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next162.php`
- `git diff --check -- lanes/libsqlite`

Expected dashboard delta: `phpPass` moves from `72664` to `72742` from 78 newly passing focused PASS lines. Mapped upstream coverage remains `609 / 1589`; this is focused pager current-source behavior over existing master-journal reader-cache inventory rather than a fresh manifest row.

Non-overlap: this avoids accepted pager master-journal reader-cache next158/next160 retained/refreshed digest checks, master-journal statement/savepoint/cache-spill slices, rollback-journal apply/commit, super-journal commit, WAL checkpoint/restart/truncate visibility, VFS writer/sync/lock clusters, and B-tree/JSON/SELECT/encoding surfaces. The new behavior is specifically next-reader ticket admission after master-journal recovery.

Dependency closure: no new support component is needed. The slice reuses lane-local pager master-journal membership parsing and reader-cache source tracking primitives.

Next: connect the same next-reader ticket rule to broader pager/VFS transaction application if a later slice needs durable file-handle reads after recovery; avoid another standalone cache wrapper unless it applies new write, checkpoint, or recovery state.
