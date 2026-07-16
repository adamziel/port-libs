# pager-master-journal-cache-spill-savepoint-current-source-next114

Status: focused PHP behavior growth for pager master-journal recovery followed by cache-spill savepoint current-source admission.

## Behavior

- Added `SQLitePagerMasterJournalCacheSpillSavepointCurrentSourceNextPlan`.
- The plan composes accepted master-journal savepoint recovery with cache-spill journal-mode routing, then verifies that eligible spill pages are sourced from the recovered current database image or rollback preview.
- Stale dirty cache images are rejected from spill admission, while pinned stale pages remain dirty and do not seed retry statement state.
- WAL-mode spill routing is also covered to prove frame routing still uses current-source page images after recovery.

## Evidence

```bash
php -l lanes/libsqlite/src/SQLitePagerMasterJournalCacheSpillSavepointCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLitePagerMasterJournalCacheSpillSavepointCurrentSourceNext114Test.php
php -l lanes/libsqlite/examples/application-pager-master-journal-cache-spill-savepoint-current-source-next114.php
```

All three changed PHP files reported no syntax errors.

```bash
php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalCacheSpillSavepointCurrentSourceNext114Test.php
```

Result: `1 test files, 51 assertions, 0 failures`.

```bash
php lanes/libsqlite/examples/application-pager-master-journal-cache-spill-savepoint-current-source-next114.php --self-test
```

Result: `application-pager-master-journal-cache-spill-savepoint-current-source-next114 self-test passed`.

## Non-Overlap

This avoids accepted batch107/108 pager cache-spill journal modes and master-journal savepoints by adding the narrower continuation guard where cache-spill page images after master-journal savepoint recovery must be current-source verified before spill admission. It also avoids WAL byte truncation, VFS savepoint rollback/write/sync/lock clusters, rollback-journal commit/apply/super-journal paths, hot-journal statement cache paths, and JSON/SQL/B-tree surfaces.

## Dependency Closure

No new support component is needed. The slice reuses lane-local rollback-journal parsing, master-journal recovery, savepoint image rollback, and cache-spill journal-mode routing.

## Next

Continue with broader pager/VFS transaction application or another distinct WAL/pager durability edge; avoid another standalone cache-spill or master-journal wrapper unless it applies a new current-source or durable-write rule.
