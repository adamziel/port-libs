# WAL checkpoint savepoint reader current-source next87

Status: focused PHP behavior growth for a WAL savepoint rollback checkpoint
reader boundary that first proves the raw WAL bytes are the current source.

This slice adds
`SQLiteWalSavepointCheckpointPlan::readerBoundaryCurrentSourceNext()`. The
plan rejects stale WAL bytes before a savepoint rollback/checkpoint reader
boundary, then reports the original current source, retained savepoint WAL
prefix, and next durable source after RESTART/TRUNCATE/PASSIVE checkpoint
handling.

Verification:

- `php -l lanes/libsqlite/src/SQLiteWalSavepointCheckpointPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteWalCheckpointSavepointReaderCurrentSourceNext87Test.php`
- `php -l lanes/libsqlite/examples/application-wal-checkpoint-savepoint-reader-current-source-next87.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalCheckpointSavepointReaderCurrentSourceNext87Test.php`
- `php lanes/libsqlite/examples/application-wal-checkpoint-savepoint-reader-current-source-next87.php --self-test`
- `git diff --check -- lanes/libsqlite`

Non-overlap: this avoids accepted WAL byte truncation, VFS savepoint rollback
application, WAL checkpoint transactions, WAL reader-pin restart/truncate
handoff, release current-source next84, WAL checksum/salt recovery, rollback
journal/super-journal paths, VFS file-writer/sync/lock clusters, JSON table
source/cursor/constraint work, SELECT SQL text/group/order/subquery clusters,
B-tree page/freelist/overflow clusters, and Unicode GLOB behavior. The new
surface is current-source admission plus source metadata for the reader
current/next savepoint checkpoint boundary.

Dependency closure: no new support component is needed. The slice reuses
existing native PHP WAL parsing/checksum validation, savepoint WAL frame
accounting, durable checkpoint planning, and reader snapshot primitives.

Next task: continue with broader pager/VFS transaction application or another
non-overlapping WAL durability edge; avoid another reader wrapper unless it
applies a distinct persisted state transition.
