# full-run-parity-application-wal-rollback-json-dynamic-20260531T074909Z-0

- Base accepted HEAD: `9d7a6158784515939dbe96138a460121fe325c71`.
- Behavior: extends `SQLiteJsonImportRollbackWalPlan` with `dynamicRollbackDisabledMaterializedWalScenarios()`, deterministic generic application fixtures where a JSON batch has two successful mutations, a malformed third mutation, `rollback_on_error=false`, and `materialize_success_wal_frames=true`.
- New focused coverage: 18 dynamic scenarios x 13 behavior checks, plus 4 corpus-shape checks, one zero-count guard, and one deterministic small-batch guard for +240 focused PASS/assertion lines in `SQLiteApplicationWalRollbackJsonDynamicParityTest.php`.
- Non-overlap: this does not repeat the accepted all-or-nothing rollback, preexisting-prefix rollback, retry-after-rollback, missing/partial WAL tail, checksum/header rejection, successful materialized WAL, full-run materialized WAL, or committed-prefix failure clusters. It owns the partial failure path that preserves successful database changes and materializes only successful WAL frames when outer rollback is disabled.
- Dependency closure: no new support component needed. The slice reuses the existing JSON import savepoint planner, WAL byte validation, and successful WAL frame materialization helper.

Verification:

- `php -l lanes/libsqlite/src/SQLiteJsonImportRollbackWalPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonDynamicParityTest.php`
- `php -l lanes/libsqlite/examples/application-wal-rollback-json-dynamic-parity.php`
- `php -d memory_limit=512M tools/run-tests.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonDynamicParityTest.php`
  - `1 test files, 7019 assertions, 0 failures`
- `php -d memory_limit=512M lanes/libsqlite/examples/application-wal-rollback-json-dynamic-parity.php --self-test`
  - `application-wal-rollback-json-dynamic-parity self-test passed`

The default `php tools/run-tests.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonDynamicParityTest.php` command exhausted PHP's default 128M memory while constructing preexisting full-run scenarios before reaching the new assertions; the focused file passes with an explicit 512M limit.
