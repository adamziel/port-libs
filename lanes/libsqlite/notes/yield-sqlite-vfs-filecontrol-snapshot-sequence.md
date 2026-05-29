# SQLite VFS File-Control Snapshot Sequence

## Status delta

- Added bounded native PHP VFS file-control state for `size_limit`, `reserve_bytes`, `lock_timeout`, `data_version`, `has_moved`, and deterministic `tempfilename`.
- Added `SQLiteVfsFileControlState::fileControlSnapshotSequence()` to expose current/next file-control transitions for pager/open code without touching accepted size-hint file preallocation behavior.
- Added `SQLiteVfsFileControlSnapshotSequenceTest.php` with focused snapshot-sequence coverage.
- Updated the WordPress smoke `wordpress-vfs-filecontrol-snapshot-sequence.php` for copied `wp_options` import handles that need lock timeout, reserve bytes, file-size cap, data-version, and temp-journal filename diagnostics without ext/sqlite.

## Verification

```text
php -l lanes/libsqlite/src/SQLiteVfsFileControlState.php
No syntax errors detected in lanes/libsqlite/src/SQLiteVfsFileControlState.php

php -l lanes/libsqlite/tests/SQLiteVfsFileControlSnapshotSequenceTest.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteVfsFileControlSnapshotSequenceTest.php

php -l lanes/libsqlite/examples/wordpress-vfs-filecontrol-snapshot-sequence.php
No syntax errors detected in lanes/libsqlite/examples/wordpress-vfs-filecontrol-snapshot-sequence.php

php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsFileControlSnapshotSequenceTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 203 assertions, 0 failures

php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 9748 assertions, 0 failures

php lanes/libsqlite/examples/wordpress-vfs-filecontrol-snapshot-sequence.php
status ok, operations 6, size_limit 8388608, reserve_bytes 32, lock_timeout 2500, data_version 12

git diff --check -- lanes/libsqlite
no output
```

## Non-overlap

This slice avoids accepted VFS size-hint/open file-control preallocation, VFS capability planning, VFS lock state, process locks, locked writer, sync apply, rollback-journal apply/commit, and savepoint rollback clusters. It only adds remaining xFileControl-style state transitions needed by pager/current-next consumers.

## Dependency closure

No new support component is required. The slice reuses the existing bounded `SQLiteOpenPlan`, `SQLiteVfsCapabilityPlan`, and `SQLiteVfsFileControlState` components and adds lane-local state behavior only.
