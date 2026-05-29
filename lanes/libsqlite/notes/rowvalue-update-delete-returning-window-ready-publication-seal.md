# Row-value UPDATE/DELETE RETURNING Window Ready Publication Seal

## Summary

- Renames the direct numbered ready-publication test, WordPress example, and note to stable descriptive names.
- Keeps the direct callers on the canonical `executeReadyPublicationContinuation()` dispatcher instead of reintroducing numbered production methods.
- Preserves historical `next990` through `next1005` receipt fields as data returned by the canonical dispatcher so downstream assertions remain behavior-compatible.
- Dependency closure: no new support component needed; this is row-value window consolidation only.

## Evidence

- `php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/examples/wordpress-rowvalue-returning-window-ready-publication-seal.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowReadyPublicationSealTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext974989Test.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowReadyPublicationSealTest.php`
- `php lanes/libsqlite/examples/wordpress-rowvalue-returning-window-current-source-next974-989.php --self-test`
- `php lanes/libsqlite/examples/wordpress-rowvalue-returning-window-ready-publication-seal.php --self-test`
- `git diff --check -- lanes/libsqlite`
