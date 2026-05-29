# Row-value Window Ready-publication Final Handoff

## Scope
- Extends the existing consolidated `SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan` continuation pattern.
- Uses the canonical `executeReadyPublicationContinuation()` entry point as the direct continuation from the prior ready seal.
- Keeps the domain narrowly on row-value UPDATE/DELETE RETURNING window current-source metadata; no executor, WAL/VFS, JSON, planner, B-tree, PRAGMA, trigger, or coordination file changes.
- Validates that the final-handoff example consumes the prior ready seal and publishes the expected follow-on ready seals.

## Validation
- `php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/examples/wordpress-rowvalue-returning-window-ready-publication-final-handoff.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowReadyPublicationFinalHandoffTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowReadyPublicationFinalHandoffTest.php`
- `php lanes/libsqlite/examples/wordpress-rowvalue-returning-window-ready-publication-final-handoff.php --self-test`
- `git diff --check`
