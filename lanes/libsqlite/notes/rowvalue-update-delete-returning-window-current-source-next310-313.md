# Rowvalue UPDATE/DELETE RETURNING Window Current-Source Next310-313

This slice prepares the independent current-source preflight continuation after the ready next306-309 handoff.

- `next310` records the handoff from the next309 seal.
- `next311` records current-source and next-source receipts plus retry window ranks.
- `next312` preflights statement and RETURNING throughput counters.
- `next313` seals the next310-312 receipts for supervisor integration.

Validation targets:

- `php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/examples/application-rowvalue-returning-window-current-source-next310-313.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext310313Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext310313Test.php`
- `php lanes/libsqlite/examples/application-rowvalue-returning-window-current-source-next310-313.php --self-test`
