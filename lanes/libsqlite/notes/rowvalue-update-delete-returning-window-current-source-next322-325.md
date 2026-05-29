# Rowvalue UPDATE/DELETE RETURNING Window Current-Source Next322-325

This slice prepares the independent current-source preflight continuation after the ready next318-321 handoff.

- `next322` records the handoff from the next321 seal.
- `next323` records current-source and next-source receipts plus phase window ids.
- `next324` preflights phase change, RETURNING, and window throughput counters.
- `next325` seals the next322-324 receipts for supervisor integration.

Validation targets:

- `php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/examples/wordpress-rowvalue-returning-window-current-source-next322-325.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext322325Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext322325Test.php`
- `php lanes/libsqlite/examples/wordpress-rowvalue-returning-window-current-source-next322-325.php --self-test`
