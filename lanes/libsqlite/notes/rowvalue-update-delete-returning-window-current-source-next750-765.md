# Rowvalue update/delete returning window current-source next750-765

This slice extends `SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan`
from integrated next734-749 through next750-765 using the existing continuation
factory. It adds four handoff/source-audit/preflight/final seal blocks:
next750-753, next754-757, next758-761, and next762-765.

Focused coverage:

- Canonical source: `SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php`
- Example: `application-rowvalue-returning-window-current-source-next750-765.php`
- Test: `SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext750765Test.php`

Validation:

```sh
php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php
php -l lanes/libsqlite/examples/application-rowvalue-returning-window-current-source-next750-765.php
php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext750765Test.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext750765Test.php
php lanes/libsqlite/examples/application-rowvalue-returning-window-current-source-next750-765.php --self-test
git diff --check
```
