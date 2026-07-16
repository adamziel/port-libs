# Pager Hot-Journal Savepoint Cache Current-Source Next83

Slice: `pager-hot-journal-savepoint-cache-current-source-next83`

## Behavior

Adds `SQLitePagerHotJournalSavepointCacheCurrentSourceNextPlan`, a bounded pager recovery helper for the current-source boundary after hot rollback-journal recovery. When hot-journal recovery changes page images, stale pager-cache entries from the previous database/WAL source are invalidated before a savepoint captures before-images. Retry writes after `ROLLBACK TO` then capture from the recovered current source, or zero-fill for pages whose stale cache entry was discarded.

The Application smoke models copied `wp_options` plugin-import pages where `active_plugins` and plugin settings are restored by a hot journal, stale WAL/database cache pages are discarded, and the retry import writes against the recovered page source.

## Verification

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerHotJournalSavepointCacheCurrentSourceNext83Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 65 assertions, 0 failures
```

```text
php -l lanes/libsqlite/src/SQLitePagerHotJournalSavepointCacheCurrentSourceNextPlan.php
No syntax errors detected in lanes/libsqlite/src/SQLitePagerHotJournalSavepointCacheCurrentSourceNextPlan.php

php -l lanes/libsqlite/tests/SQLitePagerHotJournalSavepointCacheCurrentSourceNext83Test.php
No syntax errors detected in lanes/libsqlite/tests/SQLitePagerHotJournalSavepointCacheCurrentSourceNext83Test.php

php -l lanes/libsqlite/examples/application-hot-journal-savepoint-cache-current-source-next83.php
No syntax errors detected in lanes/libsqlite/examples/application-hot-journal-savepoint-cache-current-source-next83.php
```

```text
php lanes/libsqlite/examples/application-hot-journal-savepoint-cache-current-source-next83.php --self-test
application-hot-journal-savepoint-cache-current-source-next83 self-test passed
```

PASS delta: `+65` focused PASS lines. `lane-status.json` `phpPass` moves from `31014` to `31079`. Mapped upstream coverage is unchanged because this reuses already mapped pager hot-journal, pager cache current-source, and savepoint before-image primitives.

## Non-Overlap

This avoids accepted pager hot-journal super/master recovery, pager master-journal statement recovery, pager cache savepoint file-handle current-next76, WAL savepoint checkpoint current-source next79, WAL reader-pin restart/truncate handoff, VFS savepoint rollback, rollback-journal commit/apply, super-journal commit, VFS sync/lock/file-writer clusters, B-tree pointer-map/overflow/freeblock clusters, JSON table source/cursor/constraint work, SELECT SQL text/subquery/group/order clusters, and UTF collation/GLOB work. The new surface is cache-source invalidation and before-image capture after hot-journal recovery but before savepoint retry writes.

## Dependency Closure

No new support component is needed. The slice reuses lane-local pager-cache, hot-journal recovery, and savepoint page-image concepts and adds only a bounded current-source transition planner.

## Next

Continue with broader pager/VFS transaction application or another non-overlapping WAL/pager durability edge; avoid another hot-journal wrapper unless it applies a distinct file-handle or upstream-runner blocker.
