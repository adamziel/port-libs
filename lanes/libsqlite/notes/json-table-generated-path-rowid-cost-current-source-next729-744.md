# SQLite JSON Table Generated Path Rowid Cost Current Source Next729-744

Implemented `SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext729()` through `currentSourceGeneratedPathRowidCostCurrentSourceNext744()` as additive aliases over the existing next236 generated-path rowid current-source cost-selection planner.

- Scope: `SQLiteJsonTablePlan` alias helpers only; no new support component.
- Focused test: `SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext729744Test.php`.
- Boundary: updated next713-728 coverage so next728 hands off to next729.

Validation:

```sh
php -l lanes/libsqlite/src/SQLiteJsonTablePlan.php
php -l lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext713728Test.php
php -l lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext729744Test.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext713728Test.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext729744Test.php
git diff --check
```
