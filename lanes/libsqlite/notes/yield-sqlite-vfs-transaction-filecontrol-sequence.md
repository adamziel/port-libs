# SQLite VFS Transaction File-Control Sequence

## Status delta

- Added bounded native PHP VFS file-control current/next state for transaction-oriented SQLite xFileControl hooks: `begin_atomic_write`, `commit_atomic_write`, `rollback_atomic_write`, `sync`, `commit_phasetwo`, `write_hint`, and `overwrite`.
- Added `SQLiteVfsFileControlState::transactionFileControlSequence()` so pager/open code can inspect current and next file-control state for atomic-write generation, sync flags/counts, phase-two commits, write hints, and overwritten pages without applying accepted writer/sync/rollback helpers.
- Added `SQLiteVfsTransactionFileControlSequenceTest.php` with focused transaction file-control coverage.
- Added the Application smoke `application-vfs-transaction-filecontrol-sequence.php` for copied `wp_options` imports that need file-control transaction hook diagnostics before native VFS write application.

## Verification

```text
php -l lanes/libsqlite/src/SQLiteVfsFileControlState.php
No syntax errors detected in lanes/libsqlite/src/SQLiteVfsFileControlState.php

php -l lanes/libsqlite/tests/SQLiteVfsTransactionFileControlSequenceTest.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteVfsTransactionFileControlSequenceTest.php

php -l lanes/libsqlite/examples/application-vfs-transaction-filecontrol-sequence.php
No syntax errors detected in lanes/libsqlite/examples/application-vfs-transaction-filecontrol-sequence.php

php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsTransactionFileControlSequenceTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 175 assertions, 0 failures
```

## Non-overlap

This slice avoids accepted VFS size-hint/open file-control preallocation, file-control snapshot-sequence coverage, VFS file writer, locked writer, sync plan/apply, rollback-journal apply/commit, savepoint rollback, VFS lock-state/process-lock, WAL checkpoint transaction, and super-journal clusters. It only adds transaction-hook state needed by pager/VFS consumers.

## Dependency closure

No new support component is required. The slice reuses the existing bounded `SQLiteVfsCapabilityPlan` and `SQLiteVfsFileControlState` components and adds lane-local xFileControl transaction hook state.
