# pager-rollback-cache-spill-master-current-source-next121

Status: focused PHP behavior growth for pager rollback recovery followed by cache-spill cache-generation admission.

## Implementation

- Added `SQLitePagerRollbackCacheSpillMasterCurrentSourceNextPlan`.
- The planner composes accepted master-journal/savepoint/cache-spill recovery, then advances the pager cache source token after rollback recovery.
- Dirty pages are admitted for cache spill only when their image matches the recovered current source or rollback preview and their cache source id has advanced to the recovered generation.
- Stale dirty cache pages are expired before retry reads so a crashed writer cannot seed the next write transaction with pre-rollback page images.
- WAL mode is covered as routing through recovered-generation WAL spill frames while preserving the same stale-cache rejection rule.

## Focused Verification

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerRollbackCacheSpillMasterCurrentSourceNext121Test.php
```

Result: `1 test files, 71 assertions, 0 failures`.

```sh
php lanes/libsqlite/examples/application-pager-rollback-cache-spill-master-current-source-next121.php --self-test
```

Result: `application-pager-rollback-cache-spill-master-current-source-next121 self-test passed`.

## Non-Overlap

This avoids accepted next107 cache-spill journal-mode routing and next114 master-journal cache-spill savepoint source verification by adding the narrower post-rollback cache-generation admission rule. It also avoids VFS savepoint rollback/write/sync/lock clusters, rollback-journal commit/apply/super-journal paths, WAL byte truncation/checkpoint transaction work, hot-journal statement-cache paths, B-tree overflow/freelist/page-move work, JSON table/planner work, and SQL executor/planner surfaces.

## Dependency Closure

No new support component is needed. The slice reuses native PHP rollback-journal parsing, savepoint image rollback, master-journal recovery, and dirty-page cache-spill planners already present under `lanes/libsqlite/src`.

## Next

Continue with broader pager/VFS transaction application or another distinct WAL/pager durability edge; avoid another standalone cache-spill or master-journal wrapper unless it applies a new durable-write or source-generation rule.
