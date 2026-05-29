# Rowvalue UPDATE DELETE RETURNING current-source next273-276 after-current

Prepared the next273-276 after-current row-value `UPDATE`/`DELETE ... RETURNING` slice on top of accepted next269-272 closure coverage.

- `next273` admits the sealed next272 after-current receipt for current-source publication.
- `next274` records UPDATE/DELETE RETURNING balance over the admitted ledger.
- `next275` packages the balanced current-source rows for next-source handoff.
- `next276` seals the after-current handoff across admission, balance, and package receipts.

Validation:

- `php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/examples/wordpress-rowvalue-returning-window-current-source-next273-276-after-current.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext273276AfterCurrentTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext273276AfterCurrentTest.php`
- `php lanes/libsqlite/examples/wordpress-rowvalue-returning-window-current-source-next273-276-after-current.php --self-test`
- `git diff --check`
