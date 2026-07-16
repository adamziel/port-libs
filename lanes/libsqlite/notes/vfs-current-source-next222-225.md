# SQLite VFS current-source next222-225

Slice: `vfs-current-source-next222-225`.

This follow-on prepares the current-source snapshot/reuse/publish chain after next218-221 readiness and the prior next210-213 publication receipt. It admits a new after-ready reuse only when a prior publish receipt exists, the source remains clean, and the data version stays stable; publication is blocked until that after-ready reuse receipt is recorded.

Validation:

```sh
php -l lanes/libsqlite/src/SQLiteVfsCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteVfsCurrentSourceNextTest.php
php -l lanes/libsqlite/examples/application-vfs-current-source-next222-225.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsCurrentSourceNextTest.php
php lanes/libsqlite/examples/application-vfs-current-source-next222-225.php --self-test
git diff --check
```

Non-overlap: avoids progress/status/supervisor/private files and avoids pager, WAL, B-tree, JSON, PRAGMA, planner, attach, and suite-runner surfaces. This slice only covers VFS current-source after-ready reuse and publication receipts.

Dependency closure: no new support component is required; next222-225 reuses the current VFS source shape, prior publish receipt list, dirty page map, and next210-213 snapshot/reuse publication marker.
