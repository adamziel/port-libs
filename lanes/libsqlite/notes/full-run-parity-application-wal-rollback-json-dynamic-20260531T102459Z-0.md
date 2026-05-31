# full-run-parity-application-wal-rollback-json-dynamic-20260531T102459Z-0

Implemented an additive generic application WAL rollback/JSON dynamic parity slice on base `abe349fe4c5a6f978b53aa40c7bbfdcb020ef0a8`.

Behavior:
- Added `SQLiteJsonImportRollbackWalPlan::dynamicRollbackDisabledFollowupRecoveryScenarios()`.
- The new recovery chain starts from a rollback-disabled partial batch, commits a follow-up batch, rolls back a later malformed tail batch, then commits a corrected recovery batch from the preserved committed WAL prefix.
- Focused assertions verify recovery transaction/savepoint names, restored database/WAL inputs, committed-prefix byte preservation, recovery WAL frame order, chained checksums, final commit marker, inserted recovery row, and absence of the rolled-back tail insert.
- Updated the application WAL dynamic parity example self-test to expose recovery statuses, WAL frame counts, applied pages, inserted keys, and rejected-tail key retention.

Focused evidence:
- Before: `php -d memory_limit=1024M tools/run-tests.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonDynamicParityTest.php` => `1 test files, 8351 assertions, 0 failures`.
- After: `php -d memory_limit=1024M tools/run-tests.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonDynamicParityTest.php` => `1 test files, 8972 assertions, 0 failures`.
- Delta: +621 focused assertions / selected PASS movement; mapped coverage remains `1589 / 1589`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` => `1 test files, 3 assertions, 0 failures`.
- `php -d memory_limit=512M lanes/libsqlite/examples/application-wal-rollback-json-dynamic-parity.php --self-test` => `application-wal-rollback-json-dynamic-parity self-test passed`.
- `php -l lanes/libsqlite/src/SQLiteJsonImportRollbackWalPlan.php` => no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonDynamicParityTest.php` => no syntax errors.
- `php -l lanes/libsqlite/examples/application-wal-rollback-json-dynamic-parity.php` => no syntax errors.
- `git diff --check -- lanes/libsqlite` => passed.

Non-overlap:
- Does not repeat accepted basic app-WAL rollback, preexisting rollback, inserted-setting rollback, retry, full-run successful follow-up, committed-prefix failure, rollback-disabled materialized WAL, rollback-disabled follow-up success, or rollback-disabled follow-up failure.
- This slice covers the next recovery commit after the accepted rollback-disabled follow-up failure tail rollback.
- No WordPress-specific libsqlite API, class, method, fixture API, or example was added.

Dependency closure:
- No new support component is needed. This reuses the existing bounded JSON mutation, savepoint image/WAL frame tracking, WAL byte truncation, and materialized WAL frame helpers.

Root harness:
- Not run - isolated micro-slice.
