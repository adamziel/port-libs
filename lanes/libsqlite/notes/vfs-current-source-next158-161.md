# SQLite VFS current-source next158-161

Slice: `vfs-current-source-next158-161`.

This layer builds on next154-157 current-source I/O method state. It adds focused coverage for VFS mmap and shared-memory surfaces that must remain attached to the selected current source:

- mmap limit tracking and xFetch/xUnfetch range accounting
- xFetch rejection when a request exceeds the current-source mmap window
- xShmMap page tracking, including readonly blocking for extension
- xShmLock mode tracking, including readonly blocking for exclusive locks
- xShmUnmap and close release behavior without losing the active source

Validation:

```sh
php -l lanes/libsqlite/src/SQLiteVfsCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteVfsCurrentSourceNextTest.php
php -l lanes/libsqlite/examples/wordpress-vfs-current-source-next158-161.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsCurrentSourceNextTest.php
php lanes/libsqlite/examples/wordpress-vfs-current-source-next158-161.php --self-test
git diff --check
```
