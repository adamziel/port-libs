# WAL reader checkpoint savepoint truncate current-source next123

## Behavior

Adds `SQLiteWalReaderCheckpointSavepointTruncateCurrentSourceNextPlan` for a current-source WAL reader boundary:

- a stale reader can still describe the pre-rollback WAL source through its pinned end frame;
- rollback to a savepoint truncates the current WAL source to the retained prefix;
- truncate checkpoint remains busy while the retained prefix is pinned by that reader;
- after reader release, truncate checkpoint yields an empty WAL sidecar and next readers use the checkpointed database image.

This is intentionally narrower than accepted WAL byte-truncation, VFS savepoint rollback apply, WAL checkpoint transactions, and checkpoint reader recovery slices. It does not apply bytes to local file handles; it reports current/reader/retained source identity and visibility for the checkpoint admission boundary.

## Evidence

Focused command:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalReaderCheckpointSavepointTruncateCurrentSourceNext123Test.php
```

Result:

```text
Focused test run: 1 selected test files (root lock skipped)
1 test files, 75 assertions, 0 failures
```

Expected dashboard movement: `phpPass +75` once accepted (`47656 -> 47731` from this worktree's current lane status).

## WordPress Smoke

`lanes/libsqlite/examples/wordpress-wal-reader-checkpoint-savepoint-truncate-current-source-next123.php --self-test` covers copied `wp_options` import behavior where plugin savepoint rollback discards stale WAL frames and reader release allows truncate checkpoint to make next readers database-only.

## Dependency Closure

No new support component is needed. The slice reuses existing native PHP WAL parsing/checksum validation, savepoint WAL frame bookkeeping, and durable checkpoint planning.

## Non-overlap

Avoided accepted checkpoint transactions, VFS file writer/savepoint rollback application, WAL savepoint byte truncation, WAL checkpoint reader recovery, reader-pin restart/truncate, and batch118/119 WAL checkpoint reader savepoint recovery surfaces. This next123 slice is source-accounting and reader-boundary behavior only.
