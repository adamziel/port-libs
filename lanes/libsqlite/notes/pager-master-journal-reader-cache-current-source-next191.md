# Pager Master-Journal Reader Cache Current-Source Next191

Status: focused PHP behavior growth for `pager-master-journal-reader-cache-current-source-next191`.

Behavior: adds `SQLitePagerMasterJournalReaderCacheCurrentSourceNext191Plan`, which fences pager reader-cache admission after master-journal recovery on proof that the master-journal sidecar was deleted and the containing directory sync generation is current. Clean cache pages with matching delete proof can be retained or refreshed from current source bytes; dirty pages, stale delete tokens, stale directory-sync generations, stale source/epoch entries, and pinned stale images are forced to reopen.

Application smoke: `application-pager-master-journal-reader-cache-current-source-next191.php --self-test` models copied `wp_options` import recovery where the schema page survives the master-journal delete boundary, the `alloptions` page refreshes from the recovered source, and `active_plugins` / `rewrite_rules` readers reopen because their delete proof or directory sync generation predates the current source.

Verification:

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNext191Plan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext191Test.php`
- `php -l lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next191.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext191Test.php`
- `php lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next191.php --self-test`
- `git diff --check -- lanes/libsqlite`

Expected dashboard delta: `phpPass` moves from `91519` to `91606` from 87 newly passing focused PASS lines. Mapped upstream coverage remains `617 / 1589`; this is focused pager master-journal reader-cache behavior over existing pager inventory, not a newly mapped manifest unit.

Non-overlap: avoids accepted next188 NUL-separated master-journal member parsing, next187 complete-read membership, next185 finite rollback truncation, next184 file read-token/stat fencing, rollback-journal apply/commit, super-journal commit, VFS sync-plan/apply, WAL checkpoint/savepoint/restart/truncate visibility, B-tree, JSON, SELECT, PRAGMA, trigger, planner, and encoding surfaces. This slice is specifically the master-journal delete proof plus directory-sync generation fence before next-source reader-cache reuse.

Dependency closure: no new support component is needed. The slice reuses lane-local pager reader-cache current-source primitives and records only bounded native PHP delete/sync-generation admission metadata.
