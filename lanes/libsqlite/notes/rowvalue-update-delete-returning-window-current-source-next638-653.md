# Row-value UPDATE/DELETE RETURNING window current-source next638-653

Next638-653 extends the merged next622-637 row-value UPDATE/DELETE RETURNING
window current-source continuation on the same canonical source class.

- Added entrypoints: `executeNext638()` through `executeNext653()`
- Canonical source: `SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php`
- Focused test: `SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext638653Test.php`
- Example self-test: `wordpress-rowvalue-returning-window-current-source-next638-653.php`
- New numbered source class: not created; the local pattern already consolidates these current-source continuation slices in the canonical row-value window plan class.
- Non-overlap: this slice only adds next638-653 continuation handoff, source-audit, preflight, and ready seals after next622-637; it does not touch row-value DML execution, WAL/VFS, JSON table, planner, B-tree, PRAGMA, trigger, coordination files, lane status, or supervisor state.

Validation:

```bash
php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext638653Test.php
php -l lanes/libsqlite/examples/wordpress-rowvalue-returning-window-current-source-next638-653.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext638653Test.php
php lanes/libsqlite/examples/wordpress-rowvalue-returning-window-current-source-next638-653.php --self-test
git diff --check
```
