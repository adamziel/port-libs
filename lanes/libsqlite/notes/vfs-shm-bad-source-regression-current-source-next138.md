# VFS SHM bad-source regression current-source next138

Status: focused regression repair for `vfs-shm-bad-source-regression-current-source-next138`.

This slice adds `SQLiteVfsShmFileControlLockCurrentSourcePlan::currentSourceNext138()` and tightens array-operation validation in the shared SHM/file-control planner. Unsupported array operations now throw the same `InvalidArgumentException` as unsupported string operations instead of falling through to an empty event list or partial current-source state. The regression test also covers bad `source` values for `open`, `source`, `close`, `filecontrol`/`xFileControl`, and `shmlock`/`xShmLock`, both at the first operation and after a valid main/SHM prefix.

Application smoke: `application-vfs-shm-bad-source-regression-current-source-next138.php` covers copied SQLite database import preflight rejecting a malformed SHM source before a bad WAL-index lock can be recorded.

Verification:

```sh
php -l lanes/libsqlite/src/SQLiteVfsShmFileControlLockCurrentSourcePlan.php
php -l lanes/libsqlite/tests/SQLiteVfsShmBadSourceRegressionCurrentSourceNext138Test.php
php -l lanes/libsqlite/examples/application-vfs-shm-bad-source-regression-current-source-next138.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsShmBadSourceRegressionCurrentSourceNext138Test.php lanes/libsqlite/tests/SQLiteVfsShmFileControlLockCurrentSourceNext87Test.php
php lanes/libsqlite/examples/application-vfs-shm-bad-source-regression-current-source-next138.php --self-test
git diff --check -- lanes/libsqlite
```

Focused delta: `+43` new PASS lines from `SQLiteVfsShmBadSourceRegressionCurrentSourceNext138Test.php`. `lane-status.json` `phpPass` moves from `59517` to `59560`; mapped upstream coverage remains `606 / 1589` because this is a lane-local regression/blocker repair over already mapped VFS SHM current-source behavior.

Non-overlap: this does not repeat accepted VFS SHM lock-byte/file-control next112, URI/SHM owner routing next92/next104/next126/next131, VFS lock-state/process-lock/locked-writer/sync/file-writer clusters, WAL checkpoint/savepoint/rollback clusters, or new VFS behavior. It removes the named next87 bad-source regression blocker by making malformed current-source operation arrays fail before state publication.

Dependency closure: no new support component is needed. The patch reuses the existing lane-local SHM/file-control current-source planner and only tightens its input validation.
