# full-run-parity-application-wal-rollback-json-dynamic-20260531T072720Z-0

Implemented an additive generic application WAL/JSON rollback parity slice.

- Behavior: `SQLiteJsonImportRollbackWalPlan::dynamicCommittedPrefixFailureScenarios()` now builds deterministic fixtures where a failed JSON import rolls back, a retry materializes committed WAL frames, and a later failed JSON batch rolls back only its own tail while preserving the committed retry WAL prefix.
- Focused coverage: `SQLiteApplicationWalRollbackJsonDynamicParityTest.php` gains 224 focused PASS cases and 440 assertions over committed-prefix frame boundaries, WAL byte truncation offsets, restored page images, failed statement diagnostics, preserved retry database bytes, and savepoint boundary page tracking.
- Example smoke: `application-wal-rollback-json-dynamic-parity.php --self-test` now reports committed-prefix failure summaries.
- Non-overlap: extends the accepted application WAL JSON parity family beyond the prior full-run successful follow-up path. It does not repeat the accepted app-WAL basic rollback, preexisting-WAL rollback, malformed WAL, successful materialization, or full-run successful follow-up scenarios.
- Dependency closure: no new support component is needed. The slice reuses lane-local JSON mutation, savepoint statement journal, WAL checksum, and WAL byte materialization helpers.

Verification:

- `php -d memory_limit=1024M tools/run-tests.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonDynamicParityTest.php`
- `php -l lanes/libsqlite/src/SQLiteJsonImportRollbackWalPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonDynamicParityTest.php`
- `php -l lanes/libsqlite/examples/application-wal-rollback-json-dynamic-parity.php`
- `php lanes/libsqlite/examples/application-wal-rollback-json-dynamic-parity.php --self-test`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoWordPressSpecificApiTest.php`
- `git diff --check -- lanes/libsqlite`
