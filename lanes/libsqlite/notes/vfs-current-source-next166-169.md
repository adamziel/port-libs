# SQLite VFS current-source next166-169

Slice: `vfs-current-source-next166-169`.

This layer follows the next162-165 VFS environment handoff and adds current-source coverage for the remaining environment-style VFS callbacks:

- xCurrentTime and xCurrentTimeInt64 are recorded on the selected source.
- xGetLastError stores the most recent source-scoped VFS error code and message.
- xSetSystemCall, xGetSystemCall, and xNextSystemCall track source-local syscall override state.
- Open, close, and source switching preserve the current-source owner generation model from the earlier VFS slices.

Validation:

```sh
php -l lanes/libsqlite/src/SQLiteVfsCurrentSourceNext166169Plan.php
php -l lanes/libsqlite/tests/SQLiteVfsCurrentSourceNext166169Test.php
php -l lanes/libsqlite/examples/wordpress-vfs-current-source-next166-169.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsCurrentSourceNext166169Test.php
php lanes/libsqlite/examples/wordpress-vfs-current-source-next166-169.php --self-test
git diff --check
```
