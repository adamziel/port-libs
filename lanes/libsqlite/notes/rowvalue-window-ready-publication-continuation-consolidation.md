# Row-value Window Ready-publication Continuation Consolidation

Consolidated the generated production entrypoint methods
`executeNext1006()` through `executeNext1181()` in
`SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan` into the
stable descriptive method `executeReadyPublicationContinuation()`. The
publication step is now explicit data passed to the canonical method, while
the existing continuation payload keys and ready seals are preserved for the
direct tests and WordPress examples.

Direct WordPress examples for these ranges call canonical descriptive methods
instead of constructing generated numbered method names.

Verification:

- `php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php`
- `php -l` for changed row-value window WordPress examples
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowReadyPublicationFinalTest.php` => `1 test files, 50 assertions, 0 failures`
- Changed descriptive example `--self-test` run passed
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component needed; this reuses the existing
row-value/window continuation implementation and removes generated production
method wrappers only.
