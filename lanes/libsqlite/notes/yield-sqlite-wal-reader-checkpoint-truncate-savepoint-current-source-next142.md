# WAL Reader Checkpoint Truncate Savepoint Current-Source Next142

## Behavior

Adds `SQLiteWalSavepointCheckpointPlan::readerCheckpointTruncateSavepointCurrentSourceNext()` for the WAL path where:

- a current-source reader is pinned in SHM on frames that a savepoint rollback will discard;
- the writer rolls back to the retained WAL prefix and attempts a TRUNCATE checkpoint;
- the active reader keeps the original WAL source and blocks the truncate reset;
- once the reader releases, TRUNCATE removes the old WAL sidecar and the next WordPress retry write starts at frame 1 of a fresh WAL generation.

This targets `wal-reader-checkpoint-truncate-savepoint-current-source-next142`.

## Evidence

- `php -l lanes/libsqlite/src/SQLiteWalSavepointCheckpointPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteWalReaderCheckpointTruncateSavepointCurrentSourceNext142Test.php`
- `php -l lanes/libsqlite/examples/wordpress-wal-reader-checkpoint-truncate-savepoint-current-source-next142.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalReaderCheckpointTruncateSavepointCurrentSourceNext142Test.php`
  - `1 test files, 77 assertions, 0 failures`
  - `77` focused PASS lines
- `php lanes/libsqlite/examples/wordpress-wal-reader-checkpoint-truncate-savepoint-current-source-next142.php`
  - `wordpress-wal-reader-checkpoint-truncate-savepoint-current-source-next142 self-test passed`

## Non-Overlap

Avoids accepted next134 truncate-reader fresh-generation behavior by adding savepoint rollback and active SHM reader pinning. Avoids next139 savepoint-reader checkpoint behavior by requiring released-reader TRUNCATE removal plus a new writer append on the fresh truncated generation. It also avoids accepted WAL byte truncation, checkpoint transaction, VFS savepoint rollback apply, and WAL checkpoint reader restart next140 surfaces.

## Dependency Closure

No new support component is needed. This composes existing native PHP WAL parsing/checkpoint logic, SHM read-mark planning, savepoint WAL prefix rollback, and WAL append planning.
