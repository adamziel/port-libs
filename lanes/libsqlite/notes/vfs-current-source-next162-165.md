# SQLite VFS current-source next162-165

Slice: `vfs-current-source-next162-165`.

This layer follows the next158-161 mmap/shared-memory handoff and adds focused VFS environment coverage for the selected current source:

- xAccess and xDelete are constrained to the selected source owner.
- xDelete requires directory sync for persistent database siblings, while temporary journal files can be removed without it.
- xFullPathname is resolved relative to the current-source owner directory.
- xSectorSize and xDeviceCharacteristics are tracked per source for write-path planning.
- xRandomness and xSleep calls remain scoped to the current source.

Validation:

```sh
php -l lanes/libsqlite/src/SQLiteVfsCurrentSourceNext162165Plan.php
php -l lanes/libsqlite/tests/SQLiteVfsCurrentSourceNext162165Test.php
php -l lanes/libsqlite/examples/wordpress-vfs-current-source-next162-165.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsCurrentSourceNext162165Test.php
php lanes/libsqlite/examples/wordpress-vfs-current-source-next162-165.php --self-test
git diff --check
```
