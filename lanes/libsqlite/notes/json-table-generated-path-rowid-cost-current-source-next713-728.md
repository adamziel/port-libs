# SQLite JSON Table Generated Path Rowid Cost Current Source Next713-728

Implemented `SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext713()` through `currentSourceGeneratedPathRowidCostCurrentSourceNext728()` as additive aliases over the existing next236 generated-path rowid current-source cost-selection planner.

- Scope: `SQLiteJsonTablePlan` alias helpers only; no new support component.
- Focused test: `SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext713728Test.php`.
- Boundary: updated next697-712 coverage so next712 hands off to next713.

Validation:

```sh
php -l lanes/libsqlite/src/SQLiteJsonTablePlan.php
php -l lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext697712Test.php
php -l lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext713728Test.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext697712Test.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext713728Test.php
git diff --check
```
