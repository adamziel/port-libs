# WAL Savepoint Checkpoint Visibility Current Next32

Status: focused PHP behavior growth for atomically applying a savepoint rollback
WAL prefix and its checkpoint visibility boundary through native VFS file
handles.

## Behavior

- Added `SQLiteVfsFileWriter::applySavepointCheckpointVisibility()` to compose
  `SQLiteWalSavepointCheckpointPlan::readerBoundaryAfterRollbackTo()` with the
  durable checkpoint result and apply the database/WAL sidecar bytes atomically.
- Covers TRUNCATE, RESTART, FULL, and busy reader-pinned RESTART boundaries.
- Verifies that current readers keep the retained WAL snapshot while next
  readers see the checkpoint database image after reset/truncate.
- Confirms rolled-back savepoint frames and stale WAL tail bytes are removed
  from the applied files.
- Added a copied `wp_options` plugin-settings smoke for failed import savepoint
  rollback followed by checkpoint visibility application.

## Verification

```sh
php -l lanes/libsqlite/src/SQLiteVfsFileWriter.php
php -l lanes/libsqlite/tests/SQLiteWalSavepointCheckpointVisibilityCurrentNext32Test.php
php -l lanes/libsqlite/examples/application-wal-savepoint-checkpoint-visibility-current-next32.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalSavepointCheckpointVisibilityCurrentNext32Test.php
php lanes/libsqlite/examples/application-wal-savepoint-checkpoint-visibility-current-next32.php --self-test
git diff --check -- lanes/libsqlite
```

Focused output:

```text
Focused test run: 1 selected test files (root lock skipped)
1 test files, 53 assertions, 0 failures
```

Expected dashboard movement: `phpPass` +53, from 10687 to 10740, from the 53
independent PASS lines in
`SQLiteWalSavepointCheckpointVisibilityCurrentNext32Test.php`.

## Non-Overlap

This avoids accepted WAL byte-truncation-only diagnostics, VFS savepoint
rollback application, WAL checkpoint transaction planning, raw WAL corrupt
recovery, rollback-journal commit/apply, VFS writer/sync/lock clusters, JSON
table cursor/source/constraint work, SELECT SQL text/subquery/group/order
clusters, B-tree page move/root-collapse/overflow release work, Unicode GLOB,
and batch23/batch26 metadata/planner/VDBE surfaces. The new behavior is the
atomic file-handle application of the post-savepoint current WAL checkpoint
state plus current-reader versus next-reader visibility evidence.

## Dependency Closure

No new support component is needed. The slice reuses lane-local savepoint
metadata, WAL checkpoint planning, reader snapshot visibility, and VFS
file-handle write/truncate/sync primitives.
