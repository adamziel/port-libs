# SQLite VFS current-source next170-173

Slice: `vfs-current-source-next170-173`.

This layer follows the next166-169 time/error/syscall handoff and adds current-source coverage for path and file-control state that must stay attached to the selected SQLite VFS source:

- xFileControl records selected source controls such as chunk size, size hint, persistent WAL, powersafe overwrite, and generated temporary filename.
- Pathname derivation builds WAL/SHM/journal sibling names from the selected source and verifies they stay on the same owner database.
- Temporary-name generation records deterministic source-local names beside the active database path.
- Open, close, and source switching preserve the owner generation model from the earlier current-source slices.

Validation:

```sh
php -l lanes/libsqlite/src/SQLiteVfsCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteVfsCurrentSourceNextTest.php
php -l lanes/libsqlite/examples/wordpress-vfs-current-source-next170-173.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsCurrentSourceNextTest.php
php lanes/libsqlite/examples/wordpress-vfs-current-source-next170-173.php --self-test
git diff --check
```
