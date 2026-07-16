# Row-value UPDATE/DELETE RETURNING window current-source next686-701

Next686-701 extends the merged next670-685 row-value UPDATE/DELETE RETURNING
window current-source continuation on the canonical source class.

- Added entrypoints: `executeNext686()` through `executeNext701()`
- Canonical source: `SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php`
- Focused test: `SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext686701Test.php`
- Example self-test: `application-rowvalue-returning-window-current-source-next686-701.php`
- New numbered source class: not created; the local pattern already consolidates these current-source continuation slices in the canonical row-value window plan class.
- Non-overlap: this slice only adds next686-701 continuation handoff, source-audit, preflight, and ready seals after next670-685; it does not touch row-value DML execution, WAL/VFS, JSON table, planner, B-tree, PRAGMA, trigger, coordination files, lane status, or supervisor state.

Validation:

```bash
php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext686701Test.php
php -l lanes/libsqlite/examples/application-rowvalue-returning-window-current-source-next686-701.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext686701Test.php
php lanes/libsqlite/examples/application-rowvalue-returning-window-current-source-next686-701.php --self-test
git diff --check
```
