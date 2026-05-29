## WAL/VFS Numbered Cleanup Thirty-Third Pass

Consolidated the VFS WAL SHM lock-byte current-source helper references away
from the generated numbered marker. The production helper now reports stable
unsuffixed diagnostics and dependency metadata, and its direct focused test and
WordPress smoke were renamed to stable unsuffixed filenames.

Verification:

- `php -l lanes/libsqlite/src/SQLiteVfsWalShmLockByteCurrentSourceNext.php`
- `php -l lanes/libsqlite/tests/SQLiteVfsWalShmLockByteCurrentSourceTest.php`
- `php -l lanes/libsqlite/examples/wordpress-vfs-wal-shm-lock-byte-current-source.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsWalShmLockByteCurrentSourceTest.php`
- `php lanes/libsqlite/examples/wordpress-vfs-wal-shm-lock-byte-current-source.php --self-test`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component is needed; this is a naming
consolidation over the existing VFS lock-byte and WAL SHM lock helpers.
