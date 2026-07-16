# Pager master-journal reader-cache current-source next359-366

Status: focused PHP behavior growth for `pager-master-journal-reader-cache-current-source-next359-366` after merged next351-358.

This slice extends `SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan` with next359 through next366 fences for memory-highwater, pagecache-used, pagecache-overflow, scratch-used, scratch-overflow, malloc-size, malloc-count, and stmt-used reader-cache tokens. Reader tickets that predate those current pager/runtime memory counters reopen before reusing recovered master-journal cache pages.

Validation:

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext366Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext350Test.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext358Test.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext366Test.php`
- `git diff --check`

Non-overlap: builds only on accepted next351-358 status-counter, pagecache-size, pagecache-recycle, scratch-spill, lookaside-hit, lookaside-miss-size, pcache-refcount, and memory-used reader-cache fences. It does not repeat rollback-journal apply, WAL checkpoint/savepoint, VFS writer/sync/lock, B-tree, JSON, SQL executor, PRAGMA schema, trigger, or encoding behavior.

Example self-test gap: no next350 or next358 example exists on this base to extend directly. The focused next366 PHPUnit-style lane test covers the new behavior; a standalone Application smoke should be the next slice if this family needs parity with older next318 examples.

Next slice: continue with pager master-journal reader-cache current-source memory/status invalidation fences after next366, or add the standalone Application smoke for next359-366 before expanding more fence tokens.
