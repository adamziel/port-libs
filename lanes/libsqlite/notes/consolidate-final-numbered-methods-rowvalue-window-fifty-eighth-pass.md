# Row-Value Window Numbered Method Consolidation Fifty-Eighth Pass

- Consolidated `executeNext306()` through `executeNext341()` in `SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan` into the descriptive `executeCurrentSourcePrePublicationStep()` range dispatcher.
- Migrated the direct row-value window examples for the 306-341 range to the stable dispatcher and updated the 306-309 focused test for the shared continuation engine's source-audit/preflight field names.
- Dependency closure: no new support component needed; this is production method/helper consolidation only.

Verification:

- `php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext306309Test.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext310313Test.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext314317Test.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext318321Test.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext322325Test.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext326333Test.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext334341Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext306309Test.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext310313Test.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext314317Test.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext318321Test.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext322325Test.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext326333Test.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext334341Test.php`
- `php lanes/libsqlite/examples/application-rowvalue-returning-window-current-source-next306-309.php --self-test`
- `php lanes/libsqlite/examples/application-rowvalue-returning-window-current-source-next310-313.php --self-test`
- `php lanes/libsqlite/examples/application-rowvalue-returning-window-current-source-next314-317.php --self-test`
- `php lanes/libsqlite/examples/application-rowvalue-returning-window-current-source-next318-321.php --self-test`
- `php lanes/libsqlite/examples/application-rowvalue-returning-window-current-source-next322-325.php --self-test`
- `php lanes/libsqlite/examples/application-rowvalue-returning-window-current-source-next326-333.php --self-test`
- `php lanes/libsqlite/examples/application-rowvalue-returning-window-current-source-next334-341.php --self-test`
- `git diff --check -- lanes/libsqlite`
