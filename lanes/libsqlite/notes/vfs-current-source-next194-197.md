# SQLite VFS current-source next194-197

Slice: `vfs-current-source-next194-197`.

This layer follows the ready next190-193 handoff marker and adds a lane-local current-source durability receipt model for write admission, sync publication, and barrier tokens. It keeps the VFS slice independent from pager, WAL, PRAGMA, btree, JSON, and progress/status files.

Validation:

```sh
php -l lanes/libsqlite/src/SQLiteVfsCurrentSourceNext194197Plan.php
php -l lanes/libsqlite/tests/SQLiteVfsCurrentSourceNext194197Test.php
php -l lanes/libsqlite/examples/wordpress-vfs-current-source-next194-197.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsCurrentSourceNext194197Test.php
php lanes/libsqlite/examples/wordpress-vfs-current-source-next194-197.php --self-test
git diff --check
```
