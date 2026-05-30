# Attach Schema Consolidation Fifty-Sixth Pass

## Change

- Renamed production `SQLiteAttachTempWalSchemaCookieCurrentSourceNextPlan` to stable `SQLiteAttachTempWalSchemaCookiePlan`.
- Removed the generated production file name instead of leaving a compatibility shim.
- Migrated the direct root-signature test and Application smoke to the canonical class.

## Verification

- `php -l lanes/libsqlite/src/SQLiteAttachTempWalSchemaCookiePlan.php && php -l lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCookieRootSignatureTest.php && php -l lanes/libsqlite/examples/application-attach-temp-wal-schema-cookie-root-signature.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCookieRootSignatureTest.php` -> `1 test files, 65 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-attach-temp-wal-schema-cookie-root-signature.php --self-test` -> self-test passed

## Dependency Closure

No new support component is needed. This pass reuses existing attach/WAL schema-cookie source planning and only removes a generated production suffix.
