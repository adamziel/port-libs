# SQLite JSON Table Generated Path Rowid Cost Current Source Next777-792

Implemented `SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext777()` through `currentSourceGeneratedPathRowidCostCurrentSourceNext792()` as additive aliases over the existing next236 generated-path rowid current-source cost-selection planner.

- Scope: `SQLiteJsonTablePlan` alias helpers only; no new support component.
- Focused test: `SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext777792Test.php`.
- Boundary: updated next761-776 coverage so next776 hands off to next777.

Validation:

```sh
php -l lanes/libsqlite/src/SQLiteJsonTablePlan.php
php -l lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext761776Test.php
php -l lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext777792Test.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext761776Test.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext777792Test.php
git diff --check
```
