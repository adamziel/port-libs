# full-run-parity-application-wal-rollback-json-dynamic-20260531T113124Z-0

Implemented an additive generic application WAL rollback/JSON dynamic parity
slice on base `c46d51851f90edc636ae7332660f056b95f53fd6`.

Behavior:

- Added `SQLiteJsonImportRollbackWalPlan::dynamicRollbackDisabledPostRecoveryRecoveryScenarios()`.
- The new chain starts after a rollback-disabled partial failure, successful
  follow-up, malformed tail rollback, corrected recovery commit, and later
  malformed post-recovery tail rollback.
- The corrected post-recovery recovery batch starts from the preserved
  post-failure database/WAL prefix, appends two contiguous WAL frames, commits
  only the final frame, keeps JSON text/JSONB parity, and proves both earlier
  rolled-back tail inserts stay absent.
- The generator stores compact hashes/statuses for previous chain stages so
  the focused test remains under the existing 1024M memory envelope.

Upstream parity source:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/wal3.test`, especially
  `wal3-1.*`, for WAL rollback/savepoint consistency after WAL-index frame
  removal.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/savepoint.test`,
  especially `savepoint-1.*` and `savepoint-2.*`, for `SAVEPOINT`,
  `ROLLBACK TO`, and release behavior.

Focused evidence:

- Before: `php -d memory_limit=1024M tools/run-tests.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonDynamicParityTest.php`
  => `1 test files, 9809 assertions, 0 failures`.
- After: `php -d memory_limit=1024M tools/run-tests.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonDynamicParityTest.php`
  => `1 test files, 10466 assertions, 0 failures`.
- Delta: `+657` focused assertions / selected PASS movement. Mapped coverage
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
  rollback-disabled follow-up success/failure/recovery, or rollback-disabled
  post-recovery failure.
- This slice owns the corrected recovery commit after the accepted
  post-recovery tail failure.
- No domain-specific libsqlite API, class, method, fixture API, or example was
  added.

Dependency closure:

- No new support component is needed. This reuses existing bounded JSON
  mutation, savepoint image/WAL frame tracking, WAL byte truncation, and
  materialized WAL frame helpers.

Root harness:

- Not run - isolated micro-slice.
