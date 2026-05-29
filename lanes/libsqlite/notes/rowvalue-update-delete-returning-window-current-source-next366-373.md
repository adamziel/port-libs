# Rowvalue UPDATE DELETE RETURNING current-source next366-373

Prepared the next366-373 row-value `UPDATE`/`DELETE ... RETURNING` window current-source continuation directly after merged next358-365.

- `next366` admits the ready next358-365 seal for the next366-369 continuation.
- `next367` records current-source table hashes and phase window ids.
- `next368` preflights row-value RETURNING throughput counters before sealing.
- `next369` seals next366-369 readiness.
- `next370` admits the ready next366-369 seal for the next370-373 continuation.
- `next371` records the second current-source source-audit receipt.
- `next372` preflights the second throughput checkpoint.
- `next373` seals next366-373 readiness.

Validation:

- `php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/examples/wordpress-rowvalue-returning-window-current-source-next366-373.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext366373Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext366373Test.php`
- `php lanes/libsqlite/examples/wordpress-rowvalue-returning-window-current-source-next366-373.php --self-test`
- `git diff --check`
