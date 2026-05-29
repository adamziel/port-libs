# SQLite VFS current-source next174-177

Slice: `vfs-current-source-next174-177`.

This layer follows the next170-173 path/control handoff and adds current-source coverage for source-local xAccess, xDelete, xRandomness, and xSleep behavior:

- xAccess checks selected-source paths and reports whether WAL/SHM/journal sidecars are present.
- xDelete removes only paths that belong to the selected source owner and blocks cross-owner deletes.
- xRandomness records deterministic hex output sized like SQLite's requested byte count.
- xSleep accumulates microsecond delays on the active source without sleeping in tests or examples.

Validation:

```sh
php -l lanes/libsqlite/src/SQLiteVfsCurrentSourceNext166169Plan.php
php -l lanes/libsqlite/src/SQLiteVfsCurrentSourceNext170173Plan.php
php -l lanes/libsqlite/src/SQLiteVfsCurrentSourceNext174177Plan.php
php -l lanes/libsqlite/tests/SQLiteVfsCurrentSourceNext174177Test.php
php -l lanes/libsqlite/examples/wordpress-vfs-current-source-next174-177.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsCurrentSourceNext174177Test.php
php lanes/libsqlite/examples/wordpress-vfs-current-source-next174-177.php --self-test
git diff --check
```
