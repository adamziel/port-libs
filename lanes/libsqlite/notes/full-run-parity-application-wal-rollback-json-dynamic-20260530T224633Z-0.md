# full-run-parity-application-wal-rollback-json-dynamic-20260530T224633Z-0

This slice widens the accepted generic application WAL rollback JSON dynamic
parity matrix from 16 to 24 deterministic scenarios. It stays on the existing
`SQLiteJsonImportRollbackWalPlan::dynamicParityScenarios()` path and adds
coverage for the larger generated tenant stream, both supported page sizes,
JSON text and JSONB rows, unique tenant ids, and a smaller deterministic batch
check.

The behavior remains source-neutral and does not add WordPress-specific
libsqlite APIs or fixture names. It does not repeat the static current-next38
rollback fixture; the new coverage is the wider dynamic parity matrix over
tenant ids, page sizes, page numbers, WAL frame counts, JSON text, and JSONB
payloads.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonDynamicParityTest.php`
  passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonDynamicParityTest.php`
  passed with `1 test files, 608 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteApplicationImportRollbackWalJsonCurrentNext38Test.php lanes/libsqlite/tests/SQLiteApplicationJsonImportSavepointCurrentNext48Test.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  passed with `3 test files, 108 assertions, 0 failures`.
- `php lanes/libsqlite/examples/application-wal-rollback-json-dynamic-parity.php --self-test`
  passed.
- `git diff --check -- lanes/libsqlite` passed.

Dependency closure: no new support component is needed. This reuses the
existing native PHP JSON, JSONB, savepoint rollback, WAL-header, and dynamic
TestRunner support. Full SQLite release/all runner parity remains unclaimed.
