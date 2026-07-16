# VFS Open URI Lock Current Source Next86

## Behavior

- Adds `SQLiteVfsOpenLockFileControlCurrentSource::currentSourceNext86()` for URI-aware VFS open current-source handling.
- Uses `SQLiteFileUri::parse()` during open so percent-encoded paths and `localhost` authority share the same decoded source key.
- Treats `immutable=1` URI opens as read-only and suppresses byte-range lock attempts with an explicit immutable lock blocker, while still allowing read-only controls such as `mmap_size`.
- Keeps memory databases unique per open and non-persistent.

## Focused Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsOpenUriLockCurrentSourceNext86Test.php`
- Result: `1 test files, 51 assertions, 0 failures`
- PASS lines: 51

## Application Smoke

- `php lanes/libsqlite/examples/application-vfs-open-uri-lock-current-source-next86.php`
- Result: JSON summary emitted for copied `wp_options` database URI reopen behavior.

## Non-Overlap

This does not repeat accepted batch82 VFS open/lock/file-control persistence, VFS file-control state transitions, lock byte ranges, lock-state/process-lock application, file writer, sync apply, or rollback/commit application. The new behavior is the URI source-identity and immutable lock admission edge for current-source reopen state.

## Dependency Closure

No new support component is needed. This reuses the existing bounded `SQLiteFileUri` parser and VFS current-source helper.
