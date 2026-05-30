# Pager master-journal reader-cache current-source next246

Status: focused PHP behavior growth for `pager-master-journal-reader-cache-current-source-next246`.

This slice adds `SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan`. It layers a current-source version-vector fence after the accepted next243 provenance fence so a reader-cache page can only be reused after master-journal recovery when its multi-database source vector still matches the recovered current source. Cached schema/options pages with matching vectors are retained or refreshed, while stale attached-database vectors, stale reader tickets, and inherited stale provenance/schema/read-transaction fences reopen the next reader.

WordPress smoke: `wordpress-pager-master-journal-reader-cache-current-source-version-vector-fence.php` models copied `wp_options` import behavior where schema and options-root cache rows survive recovery only when the main and attached users database version vector is current; stale `active_plugins` reads reopen before plugin import continues.

Verification:

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceVersionVectorFenceTest.php`
- `php -l lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-current-source-version-vector-fence.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceVersionVectorFenceTest.php`
- `php lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-current-source-version-vector-fence.php`
- `git diff --check -- lanes/libsqlite`

Focused result: `1 test files, 69 assertions, 0 failures`.

Expected dashboard delta: `phpPass` moves from `125265` to `125334` from 69 newly passing focused PASS lines. Mapped upstream coverage remains `650 / 1589`; this is focused pager reader-cache current-source behavior over existing master-journal inventory rather than a fresh manifest row.

Non-overlap: avoids accepted next243 provenance, next242 statement snapshot, next240 statement schema-root, schema-reparse/read-transaction/pager-cache token fences, master-journal byte/token/member fences, rollback-journal apply/commit/super-journal, WAL checkpoint/savepoint/restart/truncate visibility, VFS writer/sync/lock clusters, B-tree, JSON, SELECT, and encoding surfaces. The new behavior is specifically the current-source version-vector fence for multi-database reader-cache reuse after master-journal recovery.

Dependency closure: no new support component is needed. The slice reuses lane-local pager master-journal reader-cache and current-source provenance primitives.
