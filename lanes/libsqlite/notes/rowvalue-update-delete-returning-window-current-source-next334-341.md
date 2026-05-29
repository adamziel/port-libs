# Rowvalue UPDATE/DELETE RETURNING Window Current-Source Next334-341

This slice prepares the independent current-source follow-on after the ready next326-333 handoff.

- `next334` records the handoff from the next333 seal.
- `next335` records current-source and next-source receipts plus phase window ids.
- `next336` preflights phase change, RETURNING, and window throughput counters.
- `next337` seals the next334-336 receipts for supervisor integration.
- `next338` records the second handoff from the next337 seal.
- `next339` records the second current-source receipt audit.
- `next340` preflights the second throughput counter set.
- `next341` seals the larger next334-341 follow-on.

Validation targets:

- `php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/examples/wordpress-rowvalue-returning-window-current-source-next334-341.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext334341Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext334341Test.php`
- `php lanes/libsqlite/examples/wordpress-rowvalue-returning-window-current-source-next334-341.php --self-test`
