# SQLite JSON Table Generated Path Rowid Cost Current Source Next761-776

Implemented `SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext761()` through `currentSourceGeneratedPathRowidCostCurrentSourceNext776()` as additive aliases over the existing next236 generated-path rowid current-source cost-selection planner.

- Scope: `SQLiteJsonTablePlan` alias helpers only; no new support component.
- Focused test: `SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext761776Test.php`.
- Boundary: updated next745-760 coverage so next760 hands off to next761.

Validation:

```sh
php -l lanes/libsqlite/src/SQLiteJsonTablePlan.php
php -l lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext745760Test.php
php -l lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext761776Test.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext745760Test.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext761776Test.php
git diff --check
```
