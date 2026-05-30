# Rowvalue Window Final Numbered Methods Forty-Fourth Pass

Consolidated the row-value UPDATE/DELETE RETURNING window `executeNext374()` through `executeNext381()` production wrappers into the stable `executeCurrentSourceReadySealStep()` range entry point on `SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan`.

The direct Application example now walks the same 374-381 statuses through the unsuffixed canonical method. No compatibility shim methods were left for this range.

Verification:

- `php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/examples/application-rowvalue-returning-window-current-source-next374-381.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext374381Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext374381Test.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext382397Test.php`
- `php lanes/libsqlite/examples/application-rowvalue-returning-window-current-source-next374-381.php --self-test`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component needed; this is a production helper consolidation over existing row-value/window metadata.
