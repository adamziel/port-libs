# Pager hot-journal savepoint cache current-source next100

Status: focused PHP behavior growth for `pager-hot-journal-savepoint-cache-current-source-next100`.

This slice adds `SQLitePagerHotJournalSavepointCacheCurrentSourceNextPlan`. It models the pager cache boundary after hot rollback-journal recovery changes the current source token, a savepoint writes recovered pages, `ROLLBACK TO` restores the hot-journal before-images, and `RELEASE` allows the next reads to reuse only pages whose cache epoch/source token match the recovered source.

Focused behavior:

- invalidates cached pages restored by the hot journal;
- invalidates dirty cache pages from the aborted savepoint;
- invalidates clean pages whose epoch or source token belongs to the pre-recovery current source;
- advances preserved clean pages to the recovered next-source token;
- proves release-time reads hit only clean recovered/preserved pages and miss stale/absent pages.

Verification:

```bash
php -l lanes/libsqlite/src/SQLitePagerHotJournalSavepointCacheCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLitePagerHotJournalSavepointCacheCurrentSourceNext100Test.php
php -l lanes/libsqlite/examples/wordpress-hot-journal-savepoint-cache-current-source-next100.php
php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerHotJournalSavepointCacheCurrentSourceNext100Test.php
php lanes/libsqlite/examples/wordpress-hot-journal-savepoint-cache-current-source-next100.php --self-test
```

Focused result: `1 test files, 70 assertions, 0 failures`, with 70 PASS lines.

WordPress smoke: `wordpress-hot-journal-savepoint-cache-current-source-next100 self-test passed`.

Dashboard delta: `phpPass` moves from `38278` to `38348`. Mapped upstream coverage remains `568 / 1589`; this is fresh focused PHP behavior over already mapped pager hot-journal/savepoint cache primitives rather than a new upstream inventory unit.

Non-overlap: this avoids accepted next83 hot-journal savepoint cache retry-write coverage, next93 hot-journal statement recovery, WAL savepoint byte truncation, VFS savepoint rollback application, rollback-journal apply/commit/super-journal clusters, WAL reader/checkpoint current-source handoffs, B-tree freeblock/freelist/page-move/root-collapse/overflow clusters, JSON table source/cursor/constraint clusters, SELECT SQL text/subquery/group/order clusters, and Unicode GLOB behavior. The new surface is source-token cache validation for release-time pager reads after hot-journal recovery plus savepoint rollback.

Dependency closure: no new support component is needed. The slice reuses lane-local hot-journal recovery, savepoint page-image rollback, and pager cache current-source concepts.

Next task: apply the same source-token discipline only where it wires into broader pager/VFS transaction application; avoid another cache wrapper unless it covers a distinct source transition.
