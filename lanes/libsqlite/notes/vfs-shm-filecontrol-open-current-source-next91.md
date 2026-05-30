# VFS SHM File-Control Open Current Source Next91

- Added `SQLiteVfsShmOpenFileControlCurrentSourcePlan::currentSourceNext91()` for WAL/SHM sidecar xOpen ordering where the current source can be `-shm` or `-wal` before the main database handle is opened.
- The behavior canonicalizes `-wal`/`-shm` filenames back to the owning database for xFileControl persistence, rehydrates later main/WAL opens from the owner controls, keeps memory sources private, and prevents write controls on readonly owners while allowing readonly metadata such as `mmap_size`.
- Added focused TestRunner coverage and the Application smoke `application-vfs-shm-filecontrol-open-current-source-next91.php`.

Verification:

```sh
php -l lanes/libsqlite/src/SQLiteVfsShmOpenFileControlCurrentSourcePlan.php
php -l lanes/libsqlite/tests/SQLiteVfsShmFileControlOpenCurrentSourceNext91Test.php
php -l lanes/libsqlite/examples/application-vfs-shm-filecontrol-open-current-source-next91.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsShmFileControlOpenCurrentSourceNext91Test.php
php lanes/libsqlite/examples/application-vfs-shm-filecontrol-open-current-source-next91.php --self-test
git diff --check -- lanes/libsqlite
```

Focused delta: `62` PASS lines in one new focused test file. `lane-status.json` `phpPass` moves from `35300` to `35362`; mapped upstream coverage remains `517 / 1589` because this is focused PHP VFS current-source behavior over already mapped VFS/open inventory.

Non-overlap: this avoids accepted VFS URI/SHM/file-control locking, SHM lock-gated xFileControl, main-database open/reopen file-control persistence, VFS lock state/process locks, locked writer/sync/rollback clusters, WAL checkpoint/savepoint byte paths, JSON table source/cursor/constraint work, B-tree page/freelist/overflow clusters, and SELECT SQL text/subquery/group/order clusters. The new surface is sidecar-first xOpen current-source canonicalization for xFileControl owner persistence.

Dependency closure: no new support component is needed. The slice reuses the lane-local file URI parser and bounded VFS/open/file-control state models.
