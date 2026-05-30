# SQLite VFS current-source next178-181

Slice: `vfs-current-source-next178-181`.

This layer follows the next174-177 access/delete/random/sleep handoff and adds current-source coverage for write durability and file-size state:

- xWrite extends the selected source size and tracks dirty bytes.
- xSync records the selected source sync mode and flushes dirty bytes.
- xTruncate shrinks or resets the selected source size.
- Reserve-byte changes update usable-size accounting for the current source.

Validation:

```sh
php -l lanes/libsqlite/src/SQLiteVfsCurrentSourceNextPlan.php
php -l lanes/libsqlite/src/SQLiteVfsCurrentSourceNextPlan.php
php -l lanes/libsqlite/src/SQLiteVfsCurrentSourceNextPlan.php
php -l lanes/libsqlite/src/SQLiteVfsCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteVfsCurrentSourceNextTest.php
php -l lanes/libsqlite/examples/application-vfs-current-source-next178-181.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsCurrentSourceNextTest.php
php lanes/libsqlite/examples/application-vfs-current-source-next178-181.php --self-test
git diff --check
```
