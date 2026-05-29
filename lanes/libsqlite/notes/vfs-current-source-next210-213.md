# SQLite VFS current-source next210-213

Slice: `vfs-current-source-next210-213`.

This layer prepares the follow-on after the next206-209 snapshot/reuse handoff. It records clean current-source snapshot capture, admits reuse only when the source stays clean at the same data version, and publishes a receipt only after a reuse receipt exists. Dirty current-source pages block reuse and publication, preserving the next198-201 dirty flush/checkpoint boundary.

Validation:

```sh
php -l lanes/libsqlite/src/SQLiteVfsCurrentSourceNext210213Plan.php
php -l lanes/libsqlite/tests/SQLiteVfsCurrentSourceNext210213Test.php
php -l lanes/libsqlite/examples/wordpress-vfs-current-source-next210-213.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsCurrentSourceNext210213Test.php
php lanes/libsqlite/examples/wordpress-vfs-current-source-next210-213.php --self-test
git diff --check
```

Non-overlap: avoids progress/status/supervisor/private files and avoids pager, WAL, B-tree, JSON, PRAGMA, planner, attach, and suite-runner surfaces. This slice only covers VFS current-source snapshot reuse publication receipts.

Dependency closure: no new support component is needed; next210-213 reuses the current VFS source shape, durable receipt state, dirty page map, and expected next206-209 snapshot/reuse readiness marker.
