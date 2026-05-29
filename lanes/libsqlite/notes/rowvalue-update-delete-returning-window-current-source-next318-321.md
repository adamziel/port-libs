# Rowvalue UPDATE/DELETE RETURNING Window Current-Source Next318-321

This slice prepares the independent current-source preflight continuation after the ready next314-317 handoff.

- `next318` records the handoff from the next317 seal.
- `next319` records current-source and next-source receipts plus phase window ids.
- `next320` preflights phase change, RETURNING, and window throughput counters.
- `next321` seals the next318-320 receipts for supervisor integration.

Validation targets:

- `php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/examples/wordpress-rowvalue-returning-window-current-source-next318-321.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext318321Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext318321Test.php`
- `php lanes/libsqlite/examples/wordpress-rowvalue-returning-window-current-source-next318-321.php --self-test`
