# WAL Hot-Journal Savepoint Checkpoint Current Source Next261

## Behavior

`SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan` seals the current-source publication after the accepted next258 post-restart writer admission. The planner requires matching receipts for:

- database image publication
- WAL frame publication
- SHM-index publication
- restarted read-mark fences
- savepoint release
- database/WAL/SHM sync completion

The slice blocks publication when a receipt has stale paths, stale digests, reused restart salt, missing writer/readmark/reader coverage, open savepoint scope, missing exclusive lock, visible hot journal, unsynced sidecars, or an IO error.

## Evidence

- `php -l lanes/libsqlite/src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext261Test.php`
- `php -l lanes/libsqlite/examples/application-wal-hot-journal-savepoint-checkpoint-current-source-next261.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext261Test.php`
  - `1 test files, 109 assertions, 0 failures`

## Application Smoke

`lanes/libsqlite/examples/application-wal-hot-journal-savepoint-checkpoint-current-source-next261.php --self-test` verifies a copied Application import only advances reopened readers after the post-restart WAL writer source has database/WAL/SHM/read-mark/savepoint/sync receipts.

## Non-Overlap

This next261 slice does not repeat next258 writer admission, next255 reader restart admission, WAL byte truncation, VFS savepoint rollback, rollback-journal apply/commit, checkpoint transaction planning, JSON, SELECT, B-tree, or encoding surfaces.

## Dependency Closure

No new support component is needed. The slice reuses lane-local WAL/source metadata and native PHP receipt validation.
