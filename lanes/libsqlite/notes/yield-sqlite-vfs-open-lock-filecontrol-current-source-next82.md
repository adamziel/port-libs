# VFS Open Lock File-Control Current-Source Next82

- Added `SQLiteVfsOpenLockFileControlCurrentSource::currentSourceNext82()` for bounded main-database open/reopen current-source behavior: durable xFileControl controls survive close and rehydrate the next open, close releases persisted locks, memory/delete-on-close sources do not persist controls, readonly sources ignore write-only controls, and `nolock=1` blocks lock escalation while still allowing harmless control metadata.
- Added 57 focused `TestRunner` PASS cases in `SQLiteVfsOpenLockFileControlCurrentSourceNext82Test.php`.
- Added `application-vfs-open-lock-filecontrol-current-source-next82.php` smoke for copied `wp_options` database open/reopen control handoff without requiring `ext/sqlite`.
- Non-overlap: avoids accepted VFS file writer, temp lock/file-control persistence, VFS lock state/process lock, rollback-journal apply, sync apply, file-control current-next64/68/69/74/75, and batch76 temp VFS surfaces. This slice only models the main database current-source handoff across close/reopen.
- Dependency closure: reuses existing native PHP VFS/open/file-control and lock-state concepts; no new support component is required.

Verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsOpenLockFileControlCurrentSourceNext82Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 57 assertions, 0 failures

php -l lanes/libsqlite/src/SQLiteVfsOpenLockFileControlCurrentSource.php
No syntax errors detected in lanes/libsqlite/src/SQLiteVfsOpenLockFileControlCurrentSource.php

php -l lanes/libsqlite/tests/SQLiteVfsOpenLockFileControlCurrentSourceNext82Test.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteVfsOpenLockFileControlCurrentSourceNext82Test.php

php -l lanes/libsqlite/examples/application-vfs-open-lock-filecontrol-current-source-next82.php
No syntax errors detected in lanes/libsqlite/examples/application-vfs-open-lock-filecontrol-current-source-next82.php

php lanes/libsqlite/examples/application-vfs-open-lock-filecontrol-current-source-next82.php
application-vfs-open-lock-filecontrol-current-source-next82 self-test passed
```
