# Rowvalue UPDATE/DELETE RETURNING Window Current-Source Next326-333

This slice prepares the independent current-source follow-on after the ready next322-325 handoff.

- `next326` records the handoff from the next325 seal.
- `next327` records current-source and next-source receipts plus phase window ids.
- `next328` preflights phase change, RETURNING, and window throughput counters.
- `next329` seals the next326-328 receipts for supervisor integration.
- `next330` records the second handoff from the next329 seal.
- `next331` records the second current-source receipt audit.
- `next332` preflights the second throughput counter set.
- `next333` seals the larger next326-333 follow-on.

Validation targets:

- `php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/examples/wordpress-rowvalue-returning-window-current-source-next326-333.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext326333Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext326333Test.php`
- `php lanes/libsqlite/examples/wordpress-rowvalue-returning-window-current-source-next326-333.php --self-test`
