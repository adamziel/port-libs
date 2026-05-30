# WAL checkpoint busy reader current next29

Status: focused PHP corpus growth for WAL checkpoint busy-reader current/next visibility.

This slice adds `SQLiteWal::checkpointBusyReaderCurrentNext()` for copied Application database imports. It composes a reader-blocked FULL/RESTART checkpoint with two explicit visibility views: the current reader remains pinned to its original WAL end frame, while the next reader opens against the partially checkpointed database image and the preserved WAL tail. This covers the upstream WAL rule that busy checkpoints may copy safe frames but must not reset or truncate WAL content needed by active readers.

Files changed:

- `lanes/libsqlite/src/SQLiteWal.php`
- `lanes/libsqlite/tests/SQLiteWalCheckpointBusyReaderCurrentNext29Test.php`
- `lanes/libsqlite/examples/application-wal-checkpoint-busy-reader-current-next29.php`
- `lanes/libsqlite/lane-status.json`
- `lanes/libsqlite/notes/wal-checkpoint-busy-reader-current-next29.md`

Verification:

- `php -l lanes/libsqlite/src/SQLiteWal.php` -> no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteWalCheckpointBusyReaderCurrentNext29Test.php` -> no syntax errors.
- `php -l lanes/libsqlite/examples/application-wal-checkpoint-busy-reader-current-next29.php` -> no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalCheckpointBusyReaderCurrentNext29Test.php` -> `1 test files, 54 assertions, 0 failures` with 54 PASS lines.
- `php lanes/libsqlite/examples/application-wal-checkpoint-busy-reader-current-next29.php --self-test` -> self-test passed and printed busy checkpoint current/next reader evidence.
- `git diff --check -- lanes/libsqlite` -> clean.

Dashboard delta:

- `phpPass` increases by exactly 54, from 10028 to 10082.
- `benchmarkDenominator.mapped` is unchanged because this is focused lane-local behavior evidence without a fresh upstream inventory unit.

Non-overlap:

This avoids accepted WAL checkpoint append/restart, WAL checkpoint transaction admission, WAL byte truncation, WAL savepoint rollback, WAL durable file-write/checkpoint application, rollback-journal commit/apply, VFS writer/sync/lock clusters, JSON table source/cursor/constraint clusters, SELECT SQL text/subquery/group/order clusters, B-tree page move/root-collapse/overflow release work, Unicode GLOB, and batch23/batch25 metadata/planner/VDBE work. The new behavior is specifically the current-reader versus next-reader boundary when a busy checkpoint copies safe frames but preserves the WAL tail.

Dependency closure:

No new support component is needed. The slice reuses existing native PHP WAL parsing, checkpoint result, durable WAL sidecar, and reader snapshot primitives.
