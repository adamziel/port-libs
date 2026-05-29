# Rowvalue Window Ready-publication Final Consolidation

## Scope

- Replaced the final numbered row-value/window ready-publication caller files for the 1006-1149 covered ranges with one descriptive focused test and one descriptive WordPress smoke.
- The new smoke uses the canonical `executeReadyPublicationRange()` API in bounded range batches so it stays within the focused runner memory limit.
- Removed direct numbered test/example filenames for the covered final ranges while preserving the same ready-seal handoff, source-audit, preflight, and final-seal assertions through the consolidated test.

## Verification

- `php -l lanes/libsqlite/examples/wordpress-rowvalue-returning-window-ready-publication-final.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowReadyPublicationFinalTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowReadyPublicationFinalTest.php` - `1 test files, 50 assertions, 0 failures`
- `php lanes/libsqlite/examples/wordpress-rowvalue-returning-window-ready-publication-final.php --self-test`
- `git diff --check -- lanes/libsqlite`

## Dependency Closure

No new support component is needed. This is a caller/test/example consolidation over the existing canonical row-value RETURNING window continuation helpers.
