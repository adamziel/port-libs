# Row-value Window Consolidation Sixty-fourth Pass

## Scope

- Renamed the direct final ready-publication row-value/window Application smokes and tests from generated range filenames to stable final-handoff/final-seal names.
- Replaced their outward result keys and test labels with descriptive unsuffixed names while preserving the canonical production dispatcher and existing continuation payloads.
- Kept production behavior unchanged; this is a direct caller/test/example cleanup for the already consolidated `executeReadyPublicationContinuation()` and `executeReadyPublicationRange()` APIs.

## Verification

- `php -l lanes/libsqlite/examples/application-rowvalue-returning-window-ready-publication-final-handoff.php`
- `php -l lanes/libsqlite/examples/application-rowvalue-returning-window-ready-publication-final-seal.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowReadyPublicationFinalHandoffTest.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowReadyPublicationFinalSealTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowReadyPublicationFinalHandoffTest.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowReadyPublicationFinalSealTest.php`
- `php lanes/libsqlite/examples/application-rowvalue-returning-window-ready-publication-final-handoff.php --self-test`
- `php lanes/libsqlite/examples/application-rowvalue-returning-window-ready-publication-final-seal.php --self-test`
- `git diff --check -- lanes/libsqlite`

## Dependency Closure

No new support component is needed. The slice reuses existing row-value UPDATE/DELETE RETURNING window continuation behavior and only removes generated direct test/example surfaces.
