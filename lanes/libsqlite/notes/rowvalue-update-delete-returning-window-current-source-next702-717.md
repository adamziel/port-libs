# Row-value UPDATE/DELETE RETURNING window current-source next702-717

Next702-717 extends the integrated next686-701 row-value UPDATE/DELETE
RETURNING window current-source continuation on the canonical source class.

- Added entrypoints: `executeNext702()` through `executeNext717()`
- Canonical source: `SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php`
- Focused test: `SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext702717Test.php`
- Example self-test: `wordpress-rowvalue-returning-window-current-source-next702-717.php`
- New numbered source class: not created; the local pattern already consolidates these current-source continuation slices in the canonical row-value window plan class.
- Non-overlap: this slice only adds next702-717 continuation handoff, source-audit, preflight, and ready seals after integrated next686-701; it does not touch row-value DML execution, WAL/VFS, JSON table, planner, B-tree, PRAGMA, trigger, coordination files, lane status, or supervisor state.

Validation:

```bash
php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext702717Test.php
php -l lanes/libsqlite/examples/wordpress-rowvalue-returning-window-current-source-next702-717.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext702717Test.php
php lanes/libsqlite/examples/wordpress-rowvalue-returning-window-current-source-next702-717.php --self-test
git diff --check
```
