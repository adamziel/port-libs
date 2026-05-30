# VFS SHM File-Control Lock Current Source Next87

- Added `SQLiteVfsShmFileControlLockCurrentSourcePlan::currentSourceNext87()` for bounded WAL-index sidecar behavior where `xFileControl` remains routed to the owning database file while `xShmLock` state is tracked on the `-shm` source.
- Added focused `TestRunner` coverage for main/WAL/SHM current-source switching, database-control persistence, SHM read/write/checkpoint lock state, readonly and `nolock=1` blocking, busy upgrade handling, explicit current-source hydration, and malformed operation guards.
- Added the Application smoke `application-vfs-shm-filecontrol-lock-current-source-next87.php` for copied `wp_options` WAL-index imports that must not misroute mmap/chunk/persist-WAL controls to the SHM sidecar.

Verification:

```sh
php -l lanes/libsqlite/src/SQLiteVfsShmFileControlLockCurrentSourcePlan.php
php -l lanes/libsqlite/tests/SQLiteVfsShmFileControlLockCurrentSourceNext87Test.php
php -l lanes/libsqlite/examples/application-vfs-shm-filecontrol-lock-current-source-next87.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsShmFileControlLockCurrentSourceNext87Test.php
php lanes/libsqlite/examples/application-vfs-shm-filecontrol-lock-current-source-next87.php --self-test
git diff --check -- lanes/libsqlite
```

Non-overlap:

This avoids accepted VFS file-control state transitions, main-database open/reopen file-control persistence, temp lock/file-control routing, URI open lock-byte handling, VFS lock state, process locks, locked writer, sync apply, rollback-journal apply/commit, savepoint rollback, WAL SHM readmark/checkpoint recovery, and WAL reader-pin checkpoint handoff. The new surface is the SHM sidecar current-source split between database `xFileControl` state and SHM byte-range lock state.

Dependency closure:

No new support component is required. The slice reuses bounded native PHP VFS/open/file-control and lock-state concepts and adds only lane-local SHM current-source state needed by later pager/WAL consumers.
