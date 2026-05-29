# Rowvalue UPDATE/DELETE RETURNING Window Current-Source Continuation

This consolidation removes the direct production numbered wrapper quartet from
`SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan` and folds their
behavior into the stable `executeCurrentSourceContinuationSeal()` entry point.

- The canonical method records the source-window continuation from the sealed
  current-source image.
- It audits retry RETURNING throughput without changing DML execution.
- It records the owned source/test/example/notes scope and excluded coordination files.
- It seals the continuation receipts for independent follow-on row-value
  RETURNING window slices.

Validation targets:

- `php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/examples/wordpress-rowvalue-returning-window-source-continuation-seal.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowSourceContinuationSealTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowSourceContinuationSealTest.php`
- `php lanes/libsqlite/examples/wordpress-rowvalue-returning-window-source-continuation-seal.php --self-test`
