# full-run-parity-application-wal-rollback-json-dynamic-20260531T100020Z-0

Scope: app-WAL rollback JSON dynamic parity follow-up on accepted base
`633d868181ed471ba314711c0ee3aff27a79b97e`.

Behavior:

- Added `SQLiteJsonImportRollbackWalPlan::dynamicRollbackDisabledFollowupFailureScenarios()`.
- The new fixtures start with a rollback-disabled malformed JSON import that
  leaves its successful frames materialized, then a successful follow-up batch
  commits additional WAL frames.
- A later malformed tail batch starts from that committed prefix, appends two
  valid tail frames and one malformed tail frame, then rolls back only the tail
  batch.
- The final plan preserves the rollback-disabled partial WAL prefix and the
  successful follow-up WAL frames, truncates WAL bytes back to the committed
  prefix boundary, restores only tail pages, and keeps JSON text/JSONB mode
  parity across the generated scenarios.

Upstream parity source:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/wal3.test`, especially
  `wal3-1.*`, covers WAL rollback/savepoint rollback preserving external
  consistency after WAL-index frame removal.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/savepoint.test`,
  especially `savepoint-1.*` and `savepoint-2.*`, covers `SAVEPOINT`,
  `ROLLBACK TO`, and release behavior.

Focused movement:

- Before: `php -d memory_limit=1024M tools/run-tests.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonDynamicParityTest.php`
  reported `1 test files, 7568 assertions, 0 failures`.
- After: same command reports `1 test files, 8351 assertions, 0 failures`.
- Delta: `+783` focused assertions/PASS lines in the existing app-WAL rollback
  JSON dynamic parity file.
- `lane-status.json` selected `phpPass` moves from `2853307` to `2854090`;
  mapped coverage remains `1589 / 1589`.

Verification:

- `php -l lanes/libsqlite/src/SQLiteJsonImportRollbackWalPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonDynamicParityTest.php`
- `php -l lanes/libsqlite/examples/application-wal-rollback-json-dynamic-parity.php`
- `php -d memory_limit=1024M tools/run-tests.php lanes/libsqlite/tests/SQLiteApplicationWalRollbackJsonDynamicParityTest.php`
  -> `1 test files, 8351 assertions, 0 failures`
- `php -d memory_limit=1024M lanes/libsqlite/examples/application-wal-rollback-json-dynamic-parity.php --self-test`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  -> `1 test files, 3 assertions, 0 failures`
- `git diff --check -- lanes/libsqlite`

Dependency closure:

- No new support component is needed. The patch reuses existing native JSON
  mutation, savepoint, WAL checksum, WAL frame materialization, and generic
  application row primitives.

Non-overlap:

- This does not repeat accepted app-WAL basic rollback, preexisting WAL
  rollback, inserted-setting rollback, malformed WAL admission, successful
  materialized WAL, full-run retry/follow-up success, committed-prefix tail
  failure, rollback-disabled materialized failure, or rollback-disabled
  follow-up success coverage.
- The new owned behavior is a tail failure after a rollback-disabled partial
  failure and a successful continuation have already formed a committed WAL
  prefix.
