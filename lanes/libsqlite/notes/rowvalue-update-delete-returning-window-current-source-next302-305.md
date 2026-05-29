# Rowvalue UPDATE/DELETE RETURNING Window Current-Source Next302-305

This slice prepares the isolated current-source continuation after the ready next298-301 handoff.

- `next302` records the source-window continuation from the sealed next297 image.
- `next303` audits retry RETURNING throughput without changing DML execution.
- `next304` records the owned source/test/example/notes scope and excluded coordination files.
- `next305` seals the next302-304 receipts for independent follow-on row-value RETURNING window slices.

Validation targets:

- `php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/examples/wordpress-rowvalue-returning-window-current-source-next302-305.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext302305Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext302305Test.php`
- `php lanes/libsqlite/examples/wordpress-rowvalue-returning-window-current-source-next302-305.php --self-test`
