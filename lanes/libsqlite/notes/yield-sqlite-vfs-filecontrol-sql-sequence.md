# SQLite VFS SQL File-Control Sequence

## Status delta

- Added `SQLiteVfsFileControlState::sqlFileControlSequence()` for SQL/pragma-shaped VFS file-control sequences over current/next snapshots.
- Added parsing for bounded `PRAGMA mmap_size`, `chunk_size`, `max_page_count`, `journal_size_limit`, `reserve_bytes`, `busy_timeout`/`lock_timeout`, `data_version`, and `file_control(...)` inputs.
- Added size-limit enforcement for `size_hint`, so WordPress import preallocation is ignored when it would exceed the active file cap.
- Added `SQLiteVfsSqlFileControlSequenceTest.php` with 55 focused PASS cases and 184 assertions.
- Added the WordPress smoke `wordpress-vfs-filecontrol-sql-sequence.php` for copied `wp_options` import handles applying busy timeout, reserved bytes, mmap, size hint, and data-version controls without ext/sqlite.

## Verification

```text
php -l lanes/libsqlite/src/SQLiteVfsFileControlState.php
No syntax errors detected in lanes/libsqlite/src/SQLiteVfsFileControlState.php

php -l lanes/libsqlite/tests/SQLiteVfsSqlFileControlSequenceTest.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteVfsSqlFileControlSequenceTest.php

php -l lanes/libsqlite/examples/wordpress-vfs-filecontrol-sql-sequence.php
No syntax errors detected in lanes/libsqlite/examples/wordpress-vfs-filecontrol-sql-sequence.php

php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsSqlFileControlSequenceTest.php
Focused test run: 1 selected test files (root lock skipped)
55 PASS lines
1 test files, 184 assertions, 0 failures

php lanes/libsqlite/examples/wordpress-vfs-filecontrol-sql-sequence.php
status ok, operations 6, applied 6, ignored 0, changed 4, lock_timeout 2500, reserve_bytes 32, mmap_size 262144, data_version 19, size_hint_status ok

git diff --check -- lanes/libsqlite
no output
```

## Non-overlap

This slice avoids accepted raw file-control snapshot coverage, VFS capability planning, lock-state/process-lock/locked-writer behavior, sync apply, rollback-journal apply/commit, savepoint rollback, and the accepted VFS size-hint open-file preallocation cluster. It only adds SQL/pragma-shaped file-control ingestion and a size-limit guard for later pager/open consumers.

## Dependency closure

No new support component is required. The slice reuses existing bounded VFS capability and file-control state components and adds lane-local parser/state behavior only.
