# SQLite VFS current-source next198-201

Slice: `vfs-current-source-next198-201`.

This layer follows the next194-197 durable receipt handoff and adds lane-local dirty page flush tracking for the current VFS source. It models dirty write admission, flush publication into durable receipts, checkpoint gating while dirty, clean close handoff, and readonly write blocking without touching pager, WAL, PRAGMA, btree, JSON, progress/status, or private team state.

Validation:

```sh
php -l lanes/libsqlite/src/SQLiteVfsCurrentSourceNext198201Plan.php
php -l lanes/libsqlite/tests/SQLiteVfsCurrentSourceNext198201Test.php
php -l lanes/libsqlite/examples/wordpress-vfs-current-source-next198-201.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsCurrentSourceNext198201Test.php
php lanes/libsqlite/examples/wordpress-vfs-current-source-next198-201.php --self-test
git diff --check
```
