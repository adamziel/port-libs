# SQLite JSON Table Generated Path Rowid Cost Current Source Next745-760

Implemented `SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext745()` through `currentSourceGeneratedPathRowidCostCurrentSourceNext760()` as additive aliases over the existing next236 generated-path rowid current-source cost-selection planner.

- Scope: `SQLiteJsonTablePlan` alias helpers only; no new support component.
- Focused test: `SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext745760Test.php`.
- Boundary: updated next729-744 coverage so next744 hands off to next745.

Validation:

```sh
php -l lanes/libsqlite/src/SQLiteJsonTablePlan.php
php -l lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext729744Test.php
php -l lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext745760Test.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext729744Test.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext745760Test.php
git diff --check
```
