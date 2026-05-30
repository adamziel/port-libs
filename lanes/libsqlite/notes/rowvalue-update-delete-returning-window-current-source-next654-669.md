# Row-value UPDATE/DELETE RETURNING window current-source next654-669

Next654-669 extends the merged next638-653 row-value UPDATE/DELETE RETURNING
window current-source continuation on the same canonical source class.

- Added entrypoints: `executeNext654()` through `executeNext669()`
- Canonical source: `SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php`
- Focused test: `SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext654669Test.php`
- Example self-test: `application-rowvalue-returning-window-current-source-next654-669.php`
- New numbered source class: not created; the local pattern already consolidates these current-source continuation slices in the canonical row-value window plan class.
- Non-overlap: this slice only adds next654-669 continuation handoff, source-audit, preflight, and ready seals after next638-653; it does not touch row-value DML execution, WAL/VFS, JSON table, planner, B-tree, PRAGMA, trigger, coordination files, lane status, or supervisor state.

Validation:

```bash
php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext654669Test.php
php -l lanes/libsqlite/examples/application-rowvalue-returning-window-current-source-next654-669.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext654669Test.php
php lanes/libsqlite/examples/application-rowvalue-returning-window-current-source-next654-669.php --self-test
git diff --check
```
