# rowvalue-update-delete-returning-window-current-source-next294-297

- Added `executeNext294()` through `executeNext297()` to prepare the row-value `UPDATE`/`DELETE ... RETURNING` window current-source next294-297 handoff after the accepted next285-288 after-current seal.
- Focused behavior: next294 records the retry handoff, next295 audits retry window rows, next296 verifies the released current-source image matches the next-source image, and next297 seals the integration-ready slice.
- WordPress smoke: `examples/wordpress-rowvalue-returning-window-current-source-next294-297-after-current.php` models copied `wp_options` retry publication metadata.
- Dependency closure: no new support component needed; this reuses native PHP row-value UPDATE/DELETE RETURNING execution, savepoint rollback/release images, and existing RETURNING window metadata.
- Non-overlap: avoids row-value DML execution changes, savepoint semantics, trigger RETURNING, WAL/VFS, JSON table, planner, B-tree, PRAGMA, encoding, suite-runner, and unrelated private state.

Validation:

- `php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext294297AfterCurrentTest.php`
- `php -l lanes/libsqlite/examples/wordpress-rowvalue-returning-window-current-source-next294-297-after-current.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext294297AfterCurrentTest.php`
- `php lanes/libsqlite/examples/wordpress-rowvalue-returning-window-current-source-next294-297-after-current.php --self-test`
- `git diff --check`
