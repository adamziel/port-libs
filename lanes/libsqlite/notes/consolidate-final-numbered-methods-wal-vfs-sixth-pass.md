# Consolidate Final Numbered Methods WAL/VFS Sixth Pass

## Scope

- Consolidated the VFS temp-directory sidecar lock entrypoint from `currentSourceNext107()` to `planTempDirectorySidecarLock()`.
- Consolidated the VFS temp locking file-control entrypoint from `currentSourceNext83()` to `planTempLockingFileControl()`.
- Consolidated the VFS temp lock/data-version file-control entrypoint from `currentSourceNext102()` to `planTempLockDataVersionFileControl()`.
- Replaced the matching numbered production dependency markers with stable descriptive markers and migrated the direct tests/examples to the canonical method names.

## Verification

- `php -l lanes/libsqlite/src/SQLiteVfsTempDirectorySidecarLockCurrentSourcePlan.php`
- `php -l lanes/libsqlite/src/SQLiteVfsTempLockingFileControlCurrentSourcePlan.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsTempDirectorySidecarLockCurrentSourceNext107Test.php lanes/libsqlite/tests/SQLiteVfsTempLockingFileControlCurrentSourceNext83Test.php lanes/libsqlite/tests/SQLiteVfsTempLockFileControlCurrentSourceNext102Test.php`
- `php lanes/libsqlite/examples/wordpress-vfs-temp-directory-sidecar-lock-current-source-next107.php --self-test`
- `php lanes/libsqlite/examples/wordpress-vfs-temp-locking-filecontrol-current-source-next83.php --self-test`
- `php lanes/libsqlite/examples/wordpress-vfs-temp-lock-filecontrol-current-source-next102.php --self-test`
- `git diff --check -- lanes/libsqlite`

## Dependency Closure

No new support component is needed. This pass only renames existing native VFS planning entrypoints and dependency markers to non-numbered canonical names while preserving the same focused coverage.
