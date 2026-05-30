# WAL Savepoint Reader Restart Current Next50

Status: focused PHP behavior growth for WAL savepoint rollback followed by a drained-reader RESTART/TRUNCATE checkpoint boundary.

## Change

- Adds `SQLiteWalSavepointCheckpointPlan::readerRestartCurrentNextAfterRollbackTo()`.
- The planner truncates the WAL to the retained savepoint prefix, checkpoints that retained prefix with no reader pin, and reports the current retained-WAL reader view versus the next reader view after the WAL is restarted or truncated.
- Adds a Application smoke showing a copied `wp_options` import savepoint that discards plugin frames while preserving retained `active_plugins` and autoload index pages through the checkpoint database image.

## Verification

```sh
php -l lanes/libsqlite/src/SQLiteWalSavepointCheckpointPlan.php
php -l lanes/libsqlite/tests/SQLiteWalSavepointReaderRestartCurrentNext50Test.php
php -l lanes/libsqlite/examples/application-wal-savepoint-reader-restart-current-next50.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalSavepointReaderRestartCurrentNext50Test.php
php lanes/libsqlite/examples/application-wal-savepoint-reader-restart-current-next50.php
git diff --check -- lanes/libsqlite
```

Focused result: `1 test files, 60 assertions, 0 failures`, adding 60 independent PASS lines.

## Non-overlap

This avoids accepted WAL byte truncation-only diagnostics, savepoint page-image rollback, VFS savepoint rollback application, WAL reader pin/read-mark behavior, WAL checkpoint transactions, rollback-journal commit/apply, super-journal commit, WAL append transaction persistence, WAL SHM checkpoint restart, VFS writer/sync/lock clusters, JSON table source/cursor/constraint work, SELECT SQL text/subquery/group/order clusters, B-tree page/freelist/overflow clusters, and Unicode GLOB behavior. The new surface is the drained-reader current/next boundary after a savepoint rollback allows RESTART/TRUNCATE to reset the WAL for the next reader.

## Dependency Closure

No new support component is needed. The slice reuses lane-local savepoint WAL truncation, durable WAL checkpoint, restarted WAL header, and reader snapshot primitives.

## Next

Continue with broader pager/VFS transaction application or another non-overlapping WAL durability edge; avoid another savepoint reader wrapper unless it applies bytes through a distinct pager path.
