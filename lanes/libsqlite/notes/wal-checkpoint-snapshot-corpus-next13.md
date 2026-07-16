# WAL Checkpoint Snapshot Corpus Next13

Status: focused PHP corpus growth for WAL reader/writer checkpoint snapshot behavior.

- Added `SQLiteWalReaderWriterCheckpointSnapshotCorpusTest.php` with 53 independent PASS cases covering reader snapshot frame boundaries, checkpoint reader visibility, read-mark pinning, uncommitted WAL tail preservation, and corrupt WAL tail recovery through checkpointable committed prefixes.
- Added `application-wal-checkpoint-snapshot-corpus.php` to smoke copied `wp_options` WAL snapshot inspection while a reader pins an older frame, with checkpoint visibility and corrupt-tail recovery evidence.
- `phpPass` increases by the verified PASS-line delta: `3796 -> 3849`.
- `benchmarkDenominator.mapped` is unchanged; this is lane-scoped PHP corpus growth over existing mapped WAL/pager behavior, not a newly mapped upstream inventory unit.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalReaderWriterCheckpointSnapshotCorpusTest.php` -> `1 test files, 53 assertions, 0 failures`.
- `php -l lanes/libsqlite/tests/SQLiteWalReaderWriterCheckpointSnapshotCorpusTest.php` -> no syntax errors.
- `php -l lanes/libsqlite/examples/application-wal-checkpoint-snapshot-corpus.php` -> no syntax errors.
- `php lanes/libsqlite/examples/application-wal-checkpoint-snapshot-corpus.php` -> printed pinned-reader page 2 source `wal`, PASSIVE checkpoint `stable: true`, WAL preservation with uncommitted tail, read-mark pinned frame `2`, and corrupt-tail recovery `recovered_prefix`.
- `git diff --check -- lanes/libsqlite` -> passed.

Non-overlap:

- Avoids accepted WAL savepoint byte truncation, VFS savepoint rollback application, WAL checkpoint transaction planning/application, rollback-journal commit/apply, VFS writer/sync/lock clusters, JSON table cursor/source/constraint clusters, SELECT SQL text/subquery/group/order clusters, Unicode GLOB, and B-tree page move/root-collapse/overflow release work.
- This slice is bounded to corpus coverage for reader snapshot stability and checkpoint/recovery visibility across WAL reader/writer boundaries.

Dependency closure:

- No new support component is needed. This reuses the lane-local `SQLiteWal` reader snapshot, checkpoint mode/result, read-mark, and corrupt recovery-boundary implementations.

Next:

- Continue with non-overlapping pager/VFS transaction application, WAL durability, SQL executor/planner, or full-suite countability blockers on current accepted HEAD.
