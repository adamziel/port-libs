# WAL savepoint recovery current-next30

Status: focused PHP corpus growth for WAL recovery after `ROLLBACK TO` savepoint.

## Behavior

- Added `SQLiteWalSavepointRecoveryPlan::currentNextAfterRollbackTo()` to compose existing savepoint WAL prefix truncation with existing WAL transaction recovery/current-next reader visibility.
- The plan reports the current WAL prefix after savepoint rollback, the next-open recovery boundary, retained/discarded frame counts, checkpoint database availability, reader page sources, and dependency tags.
- Added `SQLiteWalSavepointRecoveryCurrentNext30Test.php` with focused PASS cases for outer and nested savepoint rollback prefixes, current/next reader visibility, omitted discarded plugin frames, checkpoint page counts, and invalid input guards.
- Added `application-wal-savepoint-recovery-current-next30.php` to smoke a failed copied `wp_options` plugin settings import where the current reader and next open recovery see only the retained committed WAL prefix.

## Verification

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalSavepointRecoveryCurrentNext30Test.php
php -l lanes/libsqlite/src/SQLiteWalSavepointRecoveryPlan.php
php -l lanes/libsqlite/tests/SQLiteWalSavepointRecoveryCurrentNext30Test.php
php -l lanes/libsqlite/examples/application-wal-savepoint-recovery-current-next30.php
php lanes/libsqlite/examples/application-wal-savepoint-recovery-current-next30.php --self-test
git diff --check -- lanes/libsqlite
```

## Non-overlap

This slice avoids accepted WAL byte truncation-only diagnostics, VFS savepoint rollback application, rollback-journal commit/apply, WAL checkpoint transactions, WAL append transactions, WAL corrupt recovery boundaries, VFS writer/sync/lock clusters, B-tree page/freeblock/overflow clusters, JSON table source/cursor/constraint work, SELECT SQL text/subquery/grouping/expression-order clusters, and Unicode GLOB behavior. The new surface is the current/next recovery visibility after savepoint rollback has selected the WAL prefix.

## Dependency closure

No new support component is needed. The slice reuses existing native PHP `SQLiteSavepointStack`, `SQLiteWal`, and WAL reader recovery primitives.
