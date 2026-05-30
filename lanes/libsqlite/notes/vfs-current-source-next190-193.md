# SQLite VFS current-source next190-193

Slice: `vfs-current-source-next190-193`.

This layer follows the next186-189 lock and metadata handoff and adds current-source coverage for access, delete, truncate, and sync paths:

- xAccess reports whether the selected source owner still has a live file.
- xTruncate records the next source size used by pager write planning.
- xSync increments per-source durability receipts without reopening handles.
- xDelete clears file existence and size, but reserved-or-stronger locks block deletion.
- Opened rollback-journal sidecars inherit owner generation tracking from next186-189.

Validation:

```sh
php -l lanes/libsqlite/src/SQLiteVfsCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteVfsCurrentSourceNextTest.php
php -l lanes/libsqlite/examples/application-vfs-current-source-next190-193.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsCurrentSourceNextTest.php
php lanes/libsqlite/examples/application-vfs-current-source-next190-193.php --self-test
git diff --check
```
