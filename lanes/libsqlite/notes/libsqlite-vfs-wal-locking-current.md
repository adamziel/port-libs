# libsqlite-vfs-wal-locking-current

## Behavior

`SQLiteShmIndex::checkpointPlanWithVfsLocks()` re-evaluates WAL checkpoint
read-mark state from the live VFS SHM read-lock table. SQLite stores read-mark
frames in the `-shm` wal-index image, while the matching read locks are VFS
byte locks; copied SHM bytes alone can make abandoned reader marks look active.

This slice keeps the existing parsed-fixture behavior intact and adds a
current-lock path for Application copy/import flows that need to decide whether a
WAL checkpoint reset can finish.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteShmIndexVfsLockCurrentTest.php`
  - `1 test files, 44 assertions, 0 failures`
  - `44` PASS lines

## Non-overlap

Avoids the accepted VFS byte-range/process-lock/locked-writer clusters,
rollback-journal/WAL byte truncation/checkpoint-transaction clusters, and SHM
read-mark parsing-only coverage. This is the narrower current VFS lock table
override for WAL read-mark checkpoint decisions.

## Dependency Closure

No new support component is required. The slice reuses the existing
`SQLiteShmIndex` wal-index parser and the existing VFS SHM lock map shape from
the current-source lock-byte work.
