# SQLite VFS current-source next186-189

Slice: `vfs-current-source-next186-189`.

This layer follows the next182-185 temp-directory and readonly handoff and adds current-source coverage for lock and metadata paths:

- xLock and xUnlock update the selected source lock level.
- xCheckReservedLock reports whether the selected source is at or beyond a reserved lock.
- xFileControl records per-source control values such as chunk-size hints.
- xSectorSize and xDeviceCharacteristics report stable source metadata used by pager decisions.

Validation:

```sh
php -l lanes/libsqlite/src/SQLiteVfsCurrentSourceNext186189Plan.php
php -l lanes/libsqlite/tests/SQLiteVfsCurrentSourceNext186189Test.php
php -l lanes/libsqlite/examples/wordpress-vfs-current-source-next186-189.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsCurrentSourceNext186189Test.php
php lanes/libsqlite/examples/wordpress-vfs-current-source-next186-189.php --self-test
git diff --check
```
