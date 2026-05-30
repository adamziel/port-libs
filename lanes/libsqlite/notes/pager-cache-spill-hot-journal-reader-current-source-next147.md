# Pager cache-spill hot-journal reader current-source next147

Status: focused PHP behavior growth for `pager-cache-spill-hot-journal-reader-current-source-next147`.

This slice adds `SQLitePagerCacheSpillHotJournalReaderCurrentSourceNextPlan`. It composes hot rollback-journal recovery, current WAL reader snapshots, restarted next-generation WAL parsing, and WAL-mode dirty cache-spill routing. Cache pages are admitted only when their current image matches the pinned hot-journal reader source; reader-pinned pages, stale hot-source pages, and next-generation WAL cache images are deferred before the spill appends new WAL frames.

Application smoke: `application-pager-cache-spill-hot-journal-reader-current-source-next147.php` models a copied `wp_options` import retry where a hot rollback journal restores database pages, a current reader remains pinned to the recovered WAL source, and retry cache pages spill only after stale or reader-pinned pages are excluded.

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerCacheSpillHotJournalReaderCurrentSourceNext147Test.php`
- Result: `1 test files, 89 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-pager-cache-spill-hot-journal-reader-current-source-next147.php --self-test`
- Result: `application-pager-cache-spill-hot-journal-reader-current-source-next147 self-test passed`

Expected dashboard movement: `phpPass` +89, from `64992` to `65081`, from the independent PASS lines in the focused test. Mapped upstream coverage remains `606 / 1589`; this is focused pager/WAL behavior over already mapped hot-journal, reader, and cache-spill inventory rather than a fresh upstream denominator row.

Non-overlap: avoids accepted pager cache-spill WAL savepoint next143, WAL hot-journal reader restart next143, checkpoint hot-journal reader/truncate slices, rollback-journal apply/commit, VFS writer/sync/lock clusters, B-tree overflow/freelist/page-move clusters, JSON table source/cursor/constraint clusters, SELECT SQL text/group/order/subquery clusters, and Unicode GLOB behavior. The new surface is the cache-spill admission boundary when a hot-journal-recovered current reader is still pinned and later writes must append to a separate next WAL generation.

Dependency closure: no new support component is needed. The slice reuses native hot rollback-journal recovery, WAL parsing, reader snapshot current-source helpers, and pager cache-spill journal-mode routing.

Next task: continue with broader pager/VFS transaction application or another non-overlapping WAL/pager durability edge; avoid another cache-spill wrapper unless it applies a distinct current-source transition.
