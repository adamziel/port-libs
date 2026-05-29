# WAL Reader Savepoint Restart Current Source Next121

Status: focused PHP behavior growth for WAL readers after `ROLLBACK TO` savepoint and retry-writer restart from the retained WAL prefix.

## Change

- Adds `SQLiteWalReaderSavepointRestartCurrentSourceNextPlan`.
- The planner verifies that retry writer WAL bytes begin with the retained savepoint prefix, that new writer frames restart at the first frame after that prefix, and that stale reader tail frames discarded by `ROLLBACK TO` do not become the current source.
- Adds a WordPress smoke showing a failed `wp_options` import savepoint where stale plugin/transient frames are ignored and a retry writer appends current-source frames for `active_plugins`, autoload, and plugin-settings pages.

## Verification

```sh
php -l lanes/libsqlite/src/SQLiteWalReaderSavepointRestartCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteWalReaderSavepointRestartCurrentSourceNext121Test.php
php -l lanes/libsqlite/examples/wordpress-wal-reader-savepoint-restart-current-source-next121.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalReaderSavepointRestartCurrentSourceNext121Test.php
php lanes/libsqlite/examples/wordpress-wal-reader-savepoint-restart-current-source-next121.php --self-test
git diff --check -- lanes/libsqlite
```

Focused result: `1 test files, 59 assertions, 0 failures`, adding 59 independent PASS lines.

## Non-overlap

This avoids accepted WAL savepoint byte truncation, WAL savepoint reader checkpoint next117, VFS savepoint rollback application, WAL checkpoint transaction, rollback-journal commit/apply, super-journal commit, VFS writer/sync/lock clusters, JSON table source/cursor/constraint work, SELECT SQL text/subquery/group/order clusters, B-tree page/freelist/overflow clusters, and Unicode GLOB behavior. The new surface is the writer-side current-source restart after savepoint rollback while a stale reader still holds discarded tail frames.

## Dependency Closure

No new support component is needed. The slice reuses lane-local savepoint WAL byte truncation, WAL parsing/checksum validation, and reader snapshot primitives.

## Next

Continue with broader WAL/pager transaction application or durable file-handle behavior beyond stale-reader restart diagnostics.
