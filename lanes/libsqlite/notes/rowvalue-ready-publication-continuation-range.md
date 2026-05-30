# Row-value Ready-publication Continuation Range

## Scope

- Extends the existing consolidated `SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan` class.
- Migrates the direct range test and Application smoke to stable descriptive file names.
- Uses canonical `executeReadyPublicationRange()` instead of per-step generated entrypoints.
- Validates that the range consumes `next1085_ready` and that the expected final ready seals still publish.

## Validation

- `php -l lanes/libsqlite/examples/application-rowvalue-ready-publication-continuation-range.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueReadyPublicationContinuationRangeTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueReadyPublicationContinuationRangeTest.php`
- `php lanes/libsqlite/examples/application-rowvalue-ready-publication-continuation-range.php --self-test`
- `git diff --check -- lanes/libsqlite`
