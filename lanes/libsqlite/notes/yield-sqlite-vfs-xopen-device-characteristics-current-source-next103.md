# VFS xOpen Device Characteristics Current-Source Next103

Slice: `vfs-xopen-device-characteristics-current-source-next103`

## Behavior

- Adds `SQLiteVfsOpenLockFileControlCurrentSource::currentSourceNext103()` for xOpen-time device-characteristics snapshots on URI-normalized VFS handles.
- Tracks `xOpen` flags, sector size, `xDeviceCharacteristics()` bitmasks, and decoded flag names for copied Application database opens.
- Keeps device-characteristic reads non-mutating while allowing locked `powersafe_overwrite` file-control changes to update the handle bitmask and source generation.
- Preserves current-source freshness: sibling handles opened after a device-policy change see the current source generation; older handles detect staleness through `data_version`.
- Handles read-only/immutable, memory, and `nolock` opens without creating duplicate persistent state or invalid lock/device claims.

## Focused Evidence

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsXOpenDeviceCharacteristicsCurrentSourceNext103Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 58 assertions, 0 failures
58 PASS lines
```

## Application Smoke

```text
php lanes/libsqlite/examples/application-vfs-xopen-device-characteristics-current-source-next103.php --self-test
application-vfs-xopen-device-characteristics-current-source-next103 self-test passed
```

The smoke models copied `wp_options` database handles opened through shared and private URI forms, then verifies the native VFS reports device-characteristic bits and current-source generation after powersafe-overwrite policy changes.

## Non-Overlap

This avoids accepted `SQLiteVfsCapabilityPlan` sector/device flag planning, file-control persistence/state-transition slices, VFS open/lock current-source next99, VFS process/locked writers, sync/apply paths, WAL checkpoint/savepoint/rollback application, B-tree page/freelist clusters, JSON table source/cursor/constraint work, and SQL text executor slices. The new surface is xOpen-time device-characteristic reporting tied to current-source freshness.

## Dependency Closure

No new support component is needed. The slice reuses the lane-local `SQLiteFileUri`, `SQLiteVfsCapabilityPlan` flag map, and current-source VFS open/lock/file-control model.
