# Attach temp WAL schema-cache current-source next145-148

Status: focused PHP behavior growth for `attach-temp-wal-schema-cache-current-source-next145-148`.

This slice adds `SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan::currentSourceNext145148()`. It extends the attached/temp/WAL schema-cache planner across the current-source boundary where a new attached schema satisfies a previously unresolved unqualified statement, a temp table drop lets an active statement finish its current snapshot before `SQLITE_SCHEMA` on reset, an attached `INDEXED BY` rename invalidates a read plan, and DETACH blocks a stale writer before retry.

Application smoke: `application-attach-temp-wal-schema-cache-current-source-next145-148.php` models a site import that attaches a reporting database, drops a temp import queue, renames an archive term index, and detaches the archive database while prepared statements still carry current-source schema-cache decisions.

Validation:

- `php -l lanes/libsqlite/src/SQLiteAttachWalTempSchemaCacheCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext145148Test.php`
- `php -l lanes/libsqlite/examples/application-attach-temp-wal-schema-cache-current-source-next145-148.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheCurrentSourceNext145148Test.php`
- `php lanes/libsqlite/examples/application-attach-temp-wal-schema-cache-current-source-next145-148.php --self-test`
- `git diff --check`

Expected dashboard delta: focused attach behavior only. No benchmark denominator change is expected because this reuses the lane-local attached schema-cache planner rather than admitting a fresh upstream inventory row.

Non-overlap: avoids prior attach next137-140 temp drop, main table rename, main index drop, and archive create-index cases; uses 141-144 as dependency labels only, without importing that commit into this worktree. It also avoids WAL checkpoint/savepoint durability, pager/master-journal, VFS lock/write/open, B-tree, JSON table, planner/stat4, PRAGMA index/FK, trigger/returning, and suite-runner countability surfaces.

Dependency closure: no new support component is needed. The slice reuses attach/detach, schema-write/WAL commit filtering, duplicate event consolidation, search-order resolution, and indexed-by transition logic from the existing current-source planner.

Next task: continue with a distinct attach schema-cache edge after next148, preferably one that exercises qualified schema aliasing or cache invalidation through a broader executor lifecycle rather than repeating attach/detach/index rename wrappers.
