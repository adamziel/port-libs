# Row-value UPDATE/DELETE RETURNING window current-source next718-733

Next718-733 extends the integrated next702-717 row-value UPDATE/DELETE
RETURNING window current-source continuation on the canonical source class.

- Added entrypoints: `executeNext718()` through `executeNext733()`
- Canonical source: `SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php`
- Focused test: `SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext718733Test.php`
- Example self-test: `wordpress-rowvalue-returning-window-current-source-next718-733.php`
- New numbered source class: not created; the local pattern already consolidates these current-source continuation slices in the canonical row-value window plan class.
- Non-overlap: this slice only adds next718-733 continuation handoff, source-audit, preflight, and ready seals after integrated next702-717; it does not touch row-value DML execution, WAL/VFS, JSON table, planner, B-tree, PRAGMA, trigger, coordination files, lane status, or supervisor state.

Validation:

```bash
php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext718733Test.php
php -l lanes/libsqlite/examples/wordpress-rowvalue-returning-window-current-source-next718-733.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext718733Test.php
php lanes/libsqlite/examples/wordpress-rowvalue-returning-window-current-source-next718-733.php --self-test
git diff --check
```
