# SQLite VFS Temp Lock File-Control Current/Next76

## Status delta

- Added `SQLiteVfsTempLockFileControlPersistence::currentNext76()` for temp-file handles that combine xOpen temp lifecycle, per-handle xFileControl state, and lock-state release/persistence.
- Added delete-on-close versus persistent temp-journal behavior: DELETEONCLOSE temp handles clear file-control and lock state on close, while non-delete temp lock files retain file-control state and release locks to `unlocked`.
- Added memory temp-store handling so in-memory temp handles never persist file-control or lock side effects after close.
- Added `SQLiteVfsTempLockFileControlPersistenceCurrentNext76Test.php` with 53 focused PASS cases and 53 assertions.
- Added the Application smoke `application-vfs-temp-lock-filecontrol-current-next76.php` for copied `wp_options` temp statement-journal xFileControl state and lock release across close boundaries without ext/sqlite.

## Verification

```text
php -l lanes/libsqlite/src/SQLiteVfsTempLockFileControlPersistence.php
No syntax errors detected in lanes/libsqlite/src/SQLiteVfsTempLockFileControlPersistence.php

php -l lanes/libsqlite/tests/SQLiteVfsTempLockFileControlPersistenceCurrentNext76Test.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteVfsTempLockFileControlPersistenceCurrentNext76Test.php

php -l lanes/libsqlite/examples/application-vfs-temp-lock-filecontrol-current-next76.php
No syntax errors detected in lanes/libsqlite/examples/application-vfs-temp-lock-filecontrol-current-next76.php

php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsTempLockFileControlPersistenceCurrentNext76Test.php
Focused test run: 1 selected test files (root lock skipped)
53 PASS lines
1 test files, 53 assertions, 0 failures

php lanes/libsqlite/examples/application-vfs-temp-lock-filecontrol-current-next76.php
status closed, persistentControlCount 1, persistentLockCount 0, pendingDeleteCount 0
```

## Non-overlap

This slice avoids accepted VFS file-control state transitions, current/next69 SQL parsing, temp-file lifecycle open/close behavior, VFS lock byte ranges, process file locks, VFS lock state, locked writer, file writer, sync apply, rollback-journal apply/commit, and savepoint rollback apply. It only adds the narrower temp-handle persistence rule for file-control state and lock release across close boundaries.

## Dependency closure

No new support component is required. The slice reuses existing bounded temp-file lifecycle semantics and lane-local VFS file-control concepts; it adds only a small native PHP state combiner for temp lock/file-control persistence.
