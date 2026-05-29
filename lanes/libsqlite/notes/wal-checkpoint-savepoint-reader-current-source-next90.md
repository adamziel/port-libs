# WAL Checkpoint Savepoint Reader Current Source Next90

## Behavior

Adds `SQLiteWalSavepointCheckpointPlan::checkpointReaderSavepointPinnedCurrentSourceNext()`, an additive current-source wrapper for the existing savepoint rollback/checkpoint reader path. The new behavior verifies that the supplied current WAL bytes are the exact source for the parsed `SQLiteWal` object at frame granularity, not only by header salt/checkpoint sequence and frame count.

The planner now reports exact frame-source rows with frame index, page number, commit-frame marker, database page count after commit, source byte offset/length, and page-image SHA-256. A stale parsed WAL object or stale WAL byte source with matching header and frame count but different frame image is rejected before checkpoint planning.

## Non-Overlap

This does not repeat accepted WAL byte truncation, savepoint rollback application, checkpoint transaction planning, reader-pin restart/truncate handoff, or batch88 WAL reader checkpoint/truncate visibility. It only tightens the current-source proof for a pinned-reader savepoint rollback/checkpoint path and adds frame-level source evidence.

## WordPress Smoke

`examples/wordpress-wal-checkpoint-savepoint-reader-current-source-next90.php` models a copied `wp_options` import where a failed `plugin-settings` savepoint rolls back while a reader remains pinned. The smoke proves the checkpoint remains busy, preserves the retained WAL prefix, and exposes exact frame-source rows for repair diagnostics.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalCheckpointSavepointReaderCurrentSourceNext90Test.php`
  - `Focused test run: 1 selected test files (root lock skipped)`
  - `1 test files, 65 assertions, 0 failures`
- Additional final verification commands are recorded in the final lane handoff.

## Dependency Closure

No new support component is needed. This reuses existing native PHP WAL parsing, savepoint stack, checksum validation, and checkpoint durability helpers.
