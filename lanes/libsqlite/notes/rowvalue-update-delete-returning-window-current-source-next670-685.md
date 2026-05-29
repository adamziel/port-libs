# Row-value UPDATE/DELETE RETURNING window current-source next670-685

Next670-685 extends the merged next654-669 row-value UPDATE/DELETE RETURNING
window current-source continuation on the same canonical source class.

- Added entrypoints: `executeNext670()` through `executeNext685()`
- Canonical source: `SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php`
- Focused test: `SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext670685Test.php`
- Example self-test: `wordpress-rowvalue-returning-window-current-source-next670-685.php`
- New numbered source class: not created; the local pattern already consolidates these current-source continuation slices in the canonical row-value window plan class.
- Non-overlap: this slice only adds next670-685 continuation handoff, source-audit, preflight, and ready seals after next654-669; it does not touch row-value DML execution, WAL/VFS, JSON table, planner, B-tree, PRAGMA, trigger, coordination files, lane status, or supervisor state.

Validation:

```bash
php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext670685Test.php
php -l lanes/libsqlite/examples/wordpress-rowvalue-returning-window-current-source-next670-685.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext670685Test.php
php lanes/libsqlite/examples/wordpress-rowvalue-returning-window-current-source-next670-685.php --self-test
git diff --check
```
