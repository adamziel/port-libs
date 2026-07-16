# WAL SHM Checkpoint Restart Current Next20

Status: focused PHP corpus growth for WAL restart checkpoint behavior with SHM read-mark state.

## Delta

- Added `SQLiteWalShmCheckpointRestartCorpusTest.php` with 40 independent PASS cases covering restart/truncate checkpoint decisions, current-reader reset blocking, durable restart WAL header regeneration, reader visibility across restart checkpoints, WAL read-mark planning, and SHM wal-index read-lock checkpoint state.
- Added `application-wal-shm-checkpoint-restart-current-next20.php` to smoke copied `wp_options` WAL restart checkpoint diagnostics using a pinned SHM reader and a later reader-drained restart.
- `phpPass` increases by the verified focused PASS-line delta only. `benchmarkDenominator.mapped` is unchanged because this is lane-scoped focused PHP corpus growth, not a newly mapped upstream inventory unit.

## Non-Overlap

This slice avoids accepted WAL byte truncation, VFS savepoint rollback application, rollback-journal commit/apply, super-journal commit, WAL checkpoint transaction application, VFS writer/sync/lock clusters, JSON table cursor/source/constraint work, B-tree page move/root-collapse/overflow clusters, SELECT SQL text/subquery/grouping/expression-order clusters, and Unicode GLOB work.

## Dependency Closure

No new support component is needed. The corpus reuses existing lane-local `SQLiteWal` checkpoint/restart/read-mark behavior and `SQLiteShmIndex` wal-index parsing.

## Verification

- `php -l lanes/libsqlite/tests/SQLiteWalShmCheckpointRestartCorpusTest.php` -> no syntax errors.
- `php -l lanes/libsqlite/examples/application-wal-shm-checkpoint-restart-current-next20.php` -> no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalShmCheckpointRestartCorpusTest.php` -> 1 test file, 40 assertions, 0 failures, 40 PASS lines.
- `php lanes/libsqlite/examples/application-wal-shm-checkpoint-restart-current-next20.php` -> printed pinned reader frame `2`, reader-blocked restart preserving WAL, reader-drained restart writing a 32-byte restarted WAL header with checkpoint sequence `8`, stable current-reader visibility, and SHM/WAL checkpoint dependencies.
- `git diff --check -- lanes/libsqlite` -> clean.
