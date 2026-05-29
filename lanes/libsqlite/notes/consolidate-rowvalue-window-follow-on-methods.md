# Row-value Window Follow-on Method Consolidation

Consolidates the numbered `executeNext382()` through `executeNext445()`
production wrappers on `SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan`
into the stable `executeCurrentSourceFollowOnStep()` entry point.

Focused verification:

- `php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/examples/wordpress-rowvalue-returning-window-current-source-next382-397.php`
- `php -l lanes/libsqlite/examples/wordpress-rowvalue-returning-window-current-source-next398-413.php`
- `php -l lanes/libsqlite/examples/wordpress-rowvalue-returning-window-current-source-next414-429.php`
- `php -l lanes/libsqlite/examples/wordpress-rowvalue-returning-window-current-source-next430-445.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext382397Test.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext398413Test.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext414429Test.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext430445Test.php`
- `php lanes/libsqlite/examples/wordpress-rowvalue-returning-window-current-source-next382-397.php --self-test`
- `php lanes/libsqlite/examples/wordpress-rowvalue-returning-window-current-source-next398-413.php --self-test`
- `php lanes/libsqlite/examples/wordpress-rowvalue-returning-window-current-source-next414-429.php --self-test`
- `php lanes/libsqlite/examples/wordpress-rowvalue-returning-window-current-source-next430-445.php --self-test`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component is needed; this reuses the
existing row-value/window continuation implementation.
