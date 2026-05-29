# Rowvalue UPDATE/DELETE RETURNING Window Current-Source Next306-309

This slice prepares the independent current-source preflight continuation after the ready next302-305 handoff.

- `next306` records the handoff from the next305 seal.
- `next307` records current-source and next-source receipts plus retry window ordinals.
- `next308` preflights statement and mutation throughput counters.
- `next309` seals the next306-308 receipts for supervisor integration.

Validation targets:

- `php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/examples/wordpress-rowvalue-returning-window-current-source-next306-309.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext306309Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext306309Test.php`
- `php lanes/libsqlite/examples/wordpress-rowvalue-returning-window-current-source-next306-309.php --self-test`
