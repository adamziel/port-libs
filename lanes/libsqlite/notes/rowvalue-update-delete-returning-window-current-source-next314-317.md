# Rowvalue UPDATE/DELETE RETURNING Window Current-Source Next314-317

This slice prepares the independent current-source preflight continuation after the ready next310-313 handoff.

- `next314` records the handoff from the next313 seal.
- `next315` records current-source and next-source receipts plus phase window ids.
- `next316` preflights phase change, RETURNING, and window throughput counters.
- `next317` seals the next314-316 receipts for supervisor integration.

Validation targets:

- `php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/examples/wordpress-rowvalue-returning-window-current-source-next314-317.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext314317Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext314317Test.php`
- `php lanes/libsqlite/examples/wordpress-rowvalue-returning-window-current-source-next314-317.php --self-test`
