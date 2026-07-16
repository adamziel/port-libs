# VFS URI Open Lock-Byte Current Source Next84

## Behavior

- Added `SQLiteVfsUriOpenLockByteCurrentSourceNext` to track each SQLite
  `file:` URI open as a named current source and derive byte-range lock plans
  from that source's decoded `SQLiteOpenPlan`.
- The slice preserves URI input/path metadata, shared/private cache and VFS
  dependencies, immutable read-only opens, `nolock=1` lock suppression, busy
  open blockers, and SQLite lock-byte constants per current source.
- Application smoke:
  `lanes/libsqlite/examples/application-vfs-uri-open-lock-byte-current-source-next84.php`
  shows copied `wp-content/database` main/archive URI opens with independent
  lock-byte holder state.

## Verification

Focused command:

```bash
php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsUriOpenLockByteCurrentSourceNextTest.php
```

Result:

```text
Focused test run: 1 selected test files (root lock skipped)
57 PASS lines
1 test files, 57 assertions, 0 failures
```

Syntax/example checks:

```bash
php -l lanes/libsqlite/src/SQLiteVfsUriOpenLockByteCurrentSourceNext.php
php -l lanes/libsqlite/tests/SQLiteVfsUriOpenLockByteCurrentSourceNextTest.php
php -l lanes/libsqlite/examples/application-vfs-uri-open-lock-byte-current-source-next84.php
php lanes/libsqlite/examples/application-vfs-uri-open-lock-byte-current-source-next84.php
```

## Non-Overlap

This avoids accepted VFS lock byte-range-only, VFS lock-state, process file
lock, locked writer, file-control persistence, and rollback/sync apply
clusters. The new evidence is the current-source handoff between decoded URI
opens and source-derived lock-byte planning.

## Dependency Closure

No new support component is needed. The slice reuses existing lane-local
`SQLiteFileUri`, `SQLiteOpenPlan`, `SQLiteLockByteRangePlan`, and
`SQLiteVfsLockState` primitives.
