## SQLite VFS current-source next182-185

Slice: `vfs-current-source-next182-185`.

This adds a lane-local VFS current-source preparation plan for temp-name placement, current-source directory sync tracking, readonly unlink blocking, and same-owner unlink handoff behavior. It stays in the VFS source/test/example/notes surface and records the next182-185 dependency marker independently from JSON, PRAGMA, WAL, pager, attach, and btree lanes.

Validation:

```sh
php -l lanes/libsqlite/src/SQLiteVfsCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteVfsCurrentSourceNextTest.php
php -l lanes/libsqlite/examples/application-vfs-current-source-next182-185.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsCurrentSourceNextTest.php
php lanes/libsqlite/examples/application-vfs-current-source-next182-185.php --self-test
git diff --check
```
