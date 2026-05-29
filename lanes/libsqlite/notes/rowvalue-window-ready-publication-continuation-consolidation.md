# Row-value Window Ready-publication Continuation Consolidation

Consolidated the generated production entrypoint methods
`executeNext1006()` through `executeNext1181()` in
`SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan` into the
stable descriptive method `executeReadyPublicationContinuation()`. The
publication step is now explicit data passed to the canonical method, while
the existing continuation payload keys and ready seals are preserved for the
direct tests and WordPress examples.

Direct WordPress examples for ranges 1006-1181 now call the canonical method
instead of constructing generated `executeNextNNN` method names.

Verification:

- `php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php`
- `php -l` for the 11 changed row-value window WordPress examples
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext10061021Test.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext10221037Test.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext10381053Test.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext10541069Test.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext10701085Test.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext10861101Test.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext11021117Test.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext11181133Test.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext11341149Test.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext11501165Test.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext11661181Test.php` => `11 test files, 187 assertions, 0 failures`
- Changed example `--self-test` runs for all 11 ranges passed
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component needed; this reuses the existing
row-value/window continuation implementation and removes generated production
method wrappers only.
