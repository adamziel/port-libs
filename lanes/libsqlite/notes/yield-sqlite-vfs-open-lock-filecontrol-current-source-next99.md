# VFS Open Lock File-Control Current-Source Next99

Slice: `vfs-open-lock-filecontrol-current-source-next99`

## Behavior

- Adds `SQLiteVfsOpenLockFileControlCurrentSource::currentSourceNext99()` for same-source data-version freshness across URI-normalized VFS opens.
- Tracks a per-source generation for persistent database handles. A locked writer that changes write-sensitive file controls bumps the source generation and refreshes its own handle snapshot.
- `file_control(data_version)` now reports the current source generation and whether the calling handle was opened against a stale source snapshot.
- Reopening the same decoded `file:` URI rehydrates the current source generation; memory and read-only handles do not create persistent generation movement.

## Focused Evidence

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsOpenLockFileControlCurrentSourceNext99Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 48 assertions, 0 failures
48 PASS lines
```

## Application Smoke

```text
php lanes/libsqlite/examples/application-vfs-open-lock-filecontrol-current-source-next99.php --self-test
application-vfs-open-lock-filecontrol-current-source-next99 self-test passed
```

The smoke models copied `wp_options` database handles where one writer changes persistent VFS file-control state and a sibling reader detects its stale current-source snapshot through `data_version` before reopening.

## Non-Overlap

This avoids accepted next82 open/reopen persistence, next86 URI immutable handling, next90 lock-gated persistent controls, next94 `persist_wal` write-lock discipline, VFS lock-state/process-lock/locked-writer/sync/rollback/super-journal clusters, WAL checkpoint/savepoint byte application, JSON table source/cursor/constraint work, B-tree page/freelist clusters, and SQL text executor slices. The new surface is same-source freshness detection after sibling-handle file-control mutation.

## Dependency Closure

No new support component is needed. The slice reuses the lane-local `SQLiteFileUri` parser and current-source VFS open/lock/file-control model.
