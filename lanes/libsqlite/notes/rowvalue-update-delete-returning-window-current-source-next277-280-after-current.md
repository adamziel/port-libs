# Rowvalue UPDATE DELETE RETURNING current-source next277-280 after-current

Prepared the next277-280 after-current row-value `UPDATE`/`DELETE ... RETURNING` slice on top of next273-276 handoff coverage.

- `next277` attests the sealed next276 handoff against retry RETURNING counts and current-source row counts.
- `next278` records a returning manifest over yielded, attempted, and retry change counts.
- `next279` bridges the manifest to the next-source package with current-source and retry-window row counts.
- `next280` seals the next277-279 receipts for final after-current readiness.

Validation:

- `php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/examples/application-rowvalue-returning-window-current-source-next277-280-after-current.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext277280AfterCurrentTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext277280AfterCurrentTest.php`
- `php lanes/libsqlite/examples/application-rowvalue-returning-window-current-source-next277-280-after-current.php --self-test`
- `git diff --check`
