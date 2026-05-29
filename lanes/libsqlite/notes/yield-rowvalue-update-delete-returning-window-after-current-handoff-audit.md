# rowvalue-update-delete-returning-window-after-current-handoff-audit

- Consolidated four generated production wrappers into descriptive after-current handoff/audit/seal methods.
- Focused behavior: the source handoff records retry metadata, the window audit checks retry rows, the source audit verifies released current-source and next-source images match, and the integration seal confirms the slice is ready.
- WordPress smoke: `examples/wordpress-rowvalue-returning-window-after-current-handoff-audit.php` models copied `wp_options` retry publication metadata.
- Dependency closure: no new support component needed; this reuses native PHP row-value UPDATE/DELETE RETURNING execution, savepoint rollback/release images, and existing RETURNING window metadata.
- Non-overlap: avoids row-value DML execution changes, savepoint semantics, trigger RETURNING, WAL/VFS, JSON table, planner, B-tree, PRAGMA, encoding, suite-runner, and unrelated private state.

Validation:

- `php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowAfterCurrentHandoffAuditTest.php`
- `php -l lanes/libsqlite/examples/wordpress-rowvalue-returning-window-after-current-handoff-audit.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowAfterCurrentHandoffAuditTest.php`
- `php lanes/libsqlite/examples/wordpress-rowvalue-returning-window-after-current-handoff-audit.php --self-test`
- `git diff --check`
