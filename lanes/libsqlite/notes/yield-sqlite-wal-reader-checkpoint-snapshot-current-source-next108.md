# WAL Reader Checkpoint Snapshot Current Source Next108

## Behavior

Adds current-source WAL reader/checkpoint snapshot coverage for a Application-style `wp_options` import where an old reader remains pinned at an earlier WAL commit while a checkpoint runs. The new helper validates that supplied WAL bytes match the parsed current source, proves a passive checkpoint is limited by the old reader, proves a full checkpoint reports the reader blocker, and proves a released full checkpoint has the same committed page images available from the database image for new readers.

This avoids the accepted restart/truncate/savepoint-reader clusters by staying on passive/full checkpoint snapshot visibility without reset/truncate WAL generation and without savepoint rollback.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalReaderCheckpointSnapshotCurrentSourceNext108Test.php`
- Result: `1 test files, 76 assertions, 0 failures`
- New focused PASS lines: `76`

## Application Smoke

- `php lanes/libsqlite/examples/application-wal-reader-checkpoint-snapshot-current-source-next108.php --self-test`
- The smoke reports copied `wp_options` active plugin/autoload/transient page visibility before and after a reader-limited checkpoint.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP WAL parser, checkpoint planner, reader snapshot page lookup, and durable sidecar checkpoint result primitives.
