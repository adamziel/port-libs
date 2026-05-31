# full-run-parity-application-wal-rollback-json-dynamic-20260531T104649Z-0

Implemented an additive generic application WAL rollback/JSON dynamic parity
slice on base `229ee6ac6ba54ebcac89b65db02638641eecef2d`.

Behavior:

- Added `SQLiteJsonImportRollbackWalPlan::dynamicRollbackDisabledPostRecoveryFailureScenarios()`.
- The new chain starts from a rollback-disabled partial failure, a successful
  follow-up, a malformed tail rollback, and a corrected recovery commit.
- A later malformed post-recovery tail batch appends two valid frames plus one
  malformed statement frame, then rolls back only that tail batch.
- Focused assertions verify that the corrected recovery database/WAL prefix is
  preserved, WAL bytes truncate to the recovery committed-prefix boundary, only
  the post-recovery tail pages are restored, the previous rolled-back tail
  insert is not revived, and JSON text/JSONB parity holds.

Upstream parity source:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/wal3.test`, especially
  `wal3-1.*`, for WAL rollback/savepoint consistency after WAL-index frame
  removal.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/savepoint.test`,
  especially `savepoint-1.*` and `savepoint-2.*`, for `SAVEPOINT`,
  `ROLLBACK TO`, and release behavior.

Focused evidence:

- Before: `php -d memory_limit=1024M tools/run-tests.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonDynamicParityTest.php`
  => `1 test files, 8972 assertions, 0 failures`.
- After: `php -d memory_limit=1024M tools/run-tests.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonDynamicParityTest.php`
  => `1 test files, 9809 assertions, 0 failures`.
- Delta: `+837` focused assertions / selected PASS movement. Mapped coverage
  remains `1589 / 1589`.
- `php -d memory_limit=1024M lanes/libsqlite/examples/application-wal-rollback-json-dynamic-parity.php --self-test`
  => `application-wal-rollback-json-dynamic-parity self-test passed`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  => `1 test files, 3 assertions, 0 failures`.
- `php -l lanes/libsqlite/src/SQLiteJsonImportRollbackWalPlan.php`
  => no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonDynamicParityTest.php`
  => no syntax errors.
- `php -l lanes/libsqlite/examples/application-wal-rollback-json-dynamic-parity.php`
  => no syntax errors.
- `git diff --check -- lanes/libsqlite` => passed.

Non-overlap:

- Does not repeat accepted basic app-WAL rollback, preexisting rollback,
  inserted-setting rollback, retry, full-run successful follow-up,
  committed-prefix failure, rollback-disabled materialized WAL,
  rollback-disabled follow-up success, rollback-disabled follow-up failure, or
  rollback-disabled follow-up recovery.
- This slice owns the next failed tail batch after a corrected recovery commit.
- No WordPress-specific libsqlite API, class, method, fixture API, or example
  was added.

Dependency closure:

- No new support component is needed. This reuses existing bounded JSON
  mutation, savepoint image/WAL frame tracking, WAL byte truncation, and
  materialized WAL frame helpers.

Root harness:

- Not run - isolated micro-slice.
