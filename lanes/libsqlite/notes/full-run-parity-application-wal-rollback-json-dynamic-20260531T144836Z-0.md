# full-run-parity-application-wal-rollback-json-dynamic-20260531T144836Z-0

Implemented an additive generic application WAL rollback/JSON dynamic parity
slice on base `a187757827b58c999a1fc6cda2f4be5e163b73e9`.

Behavior:

- Added `SQLiteJsonImportRollbackWalPlan::dynamicRollbackDisabledReopenedPrefixSuccessScenarios()`.
- Added `dynamicRollbackDisabledReopenedPrefixSuccessScenariosFrom()` so the
  large focused test reuses the already-built post-recovery recovery chain.
- The new branch starts after rollback-disabled partial import, follow-up
  commit, malformed tail rollback, corrected recovery commit, post-recovery
  tail rollback, and corrected post-recovery recovery commit.
- A reopened savepoint then commits a catalog JSON update, a new inserted JSON
  setting row, and a marker update on the prior corrected recovery row.
- The tests assert preserved WAL prefix hashes, contiguous appended WAL frames,
  checksum continuation, final commit frame semantics, retained prior recovery
  data, and continued absence of earlier rolled-back tail inserts.

Upstream parity source:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/wal3.test`, especially
  `wal3-1.*`, for WAL rollback/savepoint consistency after WAL-index frame
  removal and subsequent writer continuation.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/savepoint.test`,
  especially `savepoint-1.*` and `savepoint-2.*`, for `SAVEPOINT`,
  `ROLLBACK TO`, and continued commit behavior after rollback boundaries.

Focused evidence:

- Before:
  `php -d memory_limit=1024M tools/run-tests.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonDynamicParityTest.php`
  => `1 test files, 10466 assertions, 0 failures`.
- After:
  `php -d memory_limit=1024M tools/run-tests.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonDynamicParityTest.php`
  => `1 test files, 11466 assertions, 0 failures`.
- Delta: `+1000` focused assertions / selected PASS movement. Mapped coverage
  remains `1589 / 1589`.

Additional verification:

- `php -l lanes/libsqlite/src/SQLiteJsonImportRollbackWalPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonDynamicParityTest.php`
- `php -l lanes/libsqlite/examples/application-wal-rollback-json-dynamic-parity.php`
- `php lanes/libsqlite/examples/application-wal-rollback-json-dynamic-parity.php --self-test`
  => `application-wal-rollback-json-dynamic-parity self-test passed`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  => `1 test files, 3 assertions, 0 failures`.

Non-overlap:

- Does not repeat accepted app-WAL rollback basics, preexisting rollback,
  inserted-setting rollback, retry, full-run materialized WAL, committed-prefix
  failure, rollback-disabled materialized WAL, rollback-disabled follow-up
  success/failure/recovery, rollback-disabled post-recovery failure, or
  rollback-disabled post-recovery recovery.
- Does not repeat the just-ready reopened-prefix failure branch. This slice
  owns the successful reopened commit after the corrected post-recovery
  recovery prefix.
- No domain-specific libsqlite API, class, method, fixture API, or example was
  added.

Dependency closure:

- No new support component is needed. This reuses existing bounded JSON
  mutation, savepoint image/WAL frame tracking, WAL byte materialization,
  checksum, and savepoint rollback helpers.

Root harness:

- Not run - isolated micro-slice.
